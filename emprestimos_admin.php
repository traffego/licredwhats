<?php
// Iniciar buffer de saída para evitar erros de "headers already sent"
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/autenticacao.php';
require_once __DIR__ . '/includes/head.php';

// Verificar se o usuário logado é um administrador
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Permitir acesso apenas a administradores
if (!isset($_SESSION['nivel_autoridade']) || 
    ($_SESSION['nivel_autoridade'] !== 'admin' && 
     $_SESSION['nivel_autoridade'] !== 'superadmin')) {
    header("Location: dashboard.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar informações do administrador
$sql_usuario = "SELECT nome, email FROM usuarios WHERE id = ?";
$stmt_usuario = $conn->prepare($sql_usuario);
$stmt_usuario->bind_param("i", $usuario_id);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();
$usuario = $result_usuario->fetch_assoc();

// Buscar contas do administrador
$sql_contas = "SELECT 
                c.id, 
                c.nome, 
                c.descricao,
                c.comissao,
                c.saldo_inicial + COALESCE(SUM(CASE WHEN mc.tipo = 'entrada' THEN mc.valor ELSE -mc.valor END), 0) as saldo_atual 
               FROM contas c 
               LEFT JOIN movimentacoes_contas mc ON c.id = mc.conta_id 
               WHERE c.usuario_id = ? AND c.status = 'ativo' 
               GROUP BY c.id, c.nome, c.descricao, c.comissao";
               
$stmt_contas = $conn->prepare($sql_contas);
$stmt_contas->bind_param("i", $usuario_id);
$stmt_contas->execute();
$result_contas = $stmt_contas->get_result();
$contas = [];
$total_saldo = 0;

if ($result_contas && $result_contas->num_rows > 0) {
    while ($conta = $result_contas->fetch_assoc()) {
        $contas[] = $conta;
        $total_saldo += floatval($conta['saldo_atual']);
    }
}

// Atualizar o saldo da conta após todas as operações
if (!empty($contas)) {
    // Recalcular o saldo atual para refletir todas as movimentações
    $stmt_atualizar_saldo = $conn->prepare("SELECT 
                                           c.id, 
                                           c.saldo_inicial + COALESCE(SUM(CASE WHEN mc.tipo = 'entrada' THEN mc.valor ELSE -mc.valor END), 0) as saldo_atual 
                                          FROM contas c 
                                          LEFT JOIN movimentacoes_contas mc ON c.id = mc.conta_id 
                                          WHERE c.id = ? 
                                          GROUP BY c.id, c.saldo_inicial");
    
    foreach ($contas as $key => $conta) {
        $stmt_atualizar_saldo->bind_param("i", $conta['id']);
        $stmt_atualizar_saldo->execute();
        $result_saldo = $stmt_atualizar_saldo->get_result();
        
        if ($result_saldo && $result_saldo->num_rows > 0) {
            $saldo = $result_saldo->fetch_assoc();
            $contas[$key]['saldo_atual'] = $saldo['saldo_atual'];
        }
    }
    
    // Recalcular o total do saldo
    $total_saldo = 0;
    foreach ($contas as $conta) {
        $total_saldo += floatval($conta['saldo_atual']);
    }
}

// Buscar empréstimos associados ao administrador
$sql_emprestimos = "SELECT 
                    e.id, 
                    e.cliente_id,
                    e.valor_emprestado,
                    e.valor_parcela,
                    e.parcelas as total_parcelas,
                    e.juros_percentual,
                    c.nome as cliente_nome,
                    COUNT(CASE WHEN p.status = 'pago' THEN 1 END) as parcelas_pagas,
                    SUM(CASE WHEN p.status = 'pago' THEN p.valor_pago ELSE 0 END) as total_recebido
                FROM 
                    emprestimos e
                JOIN
                    clientes c ON e.cliente_id = c.id
                LEFT JOIN
                    parcelas p ON e.id = p.emprestimo_id
                WHERE 
                    e.investidor_id = ?
                GROUP BY
                    e.id
                ORDER BY
                    e.data_criacao DESC";

$stmt_emprestimos = $conn->prepare($sql_emprestimos);
$stmt_emprestimos->bind_param("i", $usuario_id);
$stmt_emprestimos->execute();
$result_emprestimos = $stmt_emprestimos->get_result();
$emprestimos = [];
$total_emprestado = 0;
$total_recebido = 0;
$total_parcelas_pagas = 0;
$comissoes_calculadas = 0;
$lucro_total_previsto = 0;

if ($result_emprestimos && $result_emprestimos->num_rows > 0) {
    while ($emprestimo = $result_emprestimos->fetch_assoc()) {
        // Calcular lucro total previsto para este empréstimo
        $valor_emprestado = floatval($emprestimo['valor_emprestado']);
        $valor_parcela = floatval($emprestimo['valor_parcela']);
        $total_parcelas = intval($emprestimo['total_parcelas']);
        
        // Valor total previsto a receber
        $valor_total_previsto = $valor_parcela * $total_parcelas;
        
        // Lucro previsto para este empréstimo
        $lucro_previsto = $valor_total_previsto - $valor_emprestado;
        
        // Adicionar ao lucro total previsto
        $lucro_total_previsto += $lucro_previsto;
        
        // Atualizar o empréstimo com o lucro previsto
        $emprestimo['lucro_previsto'] = $lucro_previsto;
        
        $emprestimos[] = $emprestimo;
        $total_emprestado += $valor_emprestado;
        $total_recebido += floatval($emprestimo['total_recebido']);
        $total_parcelas_pagas += intval($emprestimo['parcelas_pagas']);
    }
    
    // Calcular comissão total prevista (se houver conta com comissão configurada)
    if (!empty($contas) && floatval($contas[0]['comissao']) > 0) {
        $percentual_comissao = floatval($contas[0]['comissao']);
        
        // Calcular comissão sobre o lucro total previsto
        $comissoes_calculadas = $lucro_total_previsto * ($percentual_comissao / 100);
    }
}

// Buscar últimas movimentações das contas do administrador
$sql_movimentacoes = "SELECT 
                         mc.id, 
                         mc.conta_id, 
                         c.nome as conta_nome, 
                         mc.tipo, 
                         mc.valor, 
                         mc.descricao, 
                         mc.data_movimentacao
                     FROM 
                         movimentacoes_contas mc
                     INNER JOIN 
                         contas c ON mc.conta_id = c.id
                     WHERE 
                         c.usuario_id = ?
                     ORDER BY 
                         mc.data_movimentacao DESC
                     LIMIT 10";

$stmt_movimentacoes = $conn->prepare($sql_movimentacoes);
$stmt_movimentacoes->bind_param("i", $usuario_id);
$stmt_movimentacoes->execute();
$result_movimentacoes = $stmt_movimentacoes->get_result();
$movimentacoes = [];

if ($result_movimentacoes && $result_movimentacoes->num_rows > 0) {
    while ($mov = $result_movimentacoes->fetch_assoc()) {
        $movimentacoes[] = $mov;
    }
}

// Verificar empréstimos recém-quitados para retornar o capital
if (!empty($contas)) {
    // Buscar empréstimos que foram totalmente quitados mas ainda não retornaram o capital
    $sql_emprestimos_quitados = "SELECT 
                                    e.id,
                                    e.cliente_id,
                                    e.valor_emprestado,
                                    c.nome as cliente_nome,
                                    e.parcelas as total_parcelas,
                                    COUNT(p.id) as parcelas_existentes,
                                    COUNT(CASE WHEN p.status = 'pago' THEN 1 END) as parcelas_pagas,
                                    (SELECT COUNT(*) FROM movimentacoes_contas mc 
                                     WHERE mc.conta_id = ct.id 
                                     AND mc.tipo = 'entrada'
                                     AND mc.descricao LIKE CONCAT('Retorno de capital - Empréstimo #', e.id, '%')
                                    ) as tem_retorno_capital
                                 FROM 
                                    emprestimos e
                                 INNER JOIN
                                    clientes c ON e.cliente_id = c.id
                                 LEFT JOIN
                                    parcelas p ON e.id = p.emprestimo_id
                                 INNER JOIN
                                    contas ct ON e.investidor_id = ct.usuario_id
                                 WHERE 
                                    e.investidor_id = ?
                                    AND (SELECT COUNT(*) FROM movimentacoes_contas mc 
                                        WHERE mc.conta_id = ct.id 
                                        AND mc.tipo = 'entrada'
                                        AND mc.descricao LIKE CONCAT('Retorno de capital - Empréstimo #', e.id, '%')
                                       ) = 0
                                 GROUP BY
                                    e.id
                                 HAVING
                                    parcelas_existentes = total_parcelas
                                    AND parcelas_pagas = total_parcelas";
                                    
    // Processar empréstimos quitados
    $stmt_quitados = $conn->prepare($sql_emprestimos_quitados);
    $stmt_quitados->bind_param("i", $usuario_id);
    $stmt_quitados->execute();
    $result_quitados = $stmt_quitados->get_result();
    
    if ($result_quitados && $result_quitados->num_rows > 0) {
        $conn->begin_transaction();
        
        try {
            $emprestimos_processados = 0;
            
            while ($emp_quitado = $result_quitados->fetch_assoc()) {
                // Agora o processamento é feito pela função processarComissoesERetornos
                $resultado_processamento = processarComissoesERetornos($conn, $emp_quitado['id']);
                
                if ($resultado_processamento['success']) {
                    $emprestimos_processados++;
                }
            }
            
            $conn->commit();
            
            if ($emprestimos_processados > 0) {
                $mensagem = "Processados $emprestimos_processados empréstimo(s) quitado(s).";
                $tipo_alerta = "success";
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensagem = "Erro ao processar retorno de capital: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// Exibir mensagens de alerta se houver
if (isset($_GET['alerta']) && isset($_GET['tipo']) && isset($_GET['msg'])) {
    $mensagem = urldecode($_GET['msg']);
    $tipo_alerta = $_GET['tipo'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Meus Empréstimos - Administrador</title>
    <?php require_once __DIR__ . '/includes/head.php'; ?>
    <style>
        .card {
            height: 100%;
            margin-bottom: 1rem;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .card-title {
            margin-right: 60px;
        }
    </style>
</head>
<body class="bg-light">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <?php if (isset($mensagem)): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-12">
                <h2 class="mb-4">Meus Empréstimos</h2>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="row mb-4">
            <!-- Capital Total Emprestado -->
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Capital Emprestado</h6>
                        <h3 class="card-text">R$ <?= number_format($total_emprestado, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>

            <!-- Capital Ativo -->
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Capital Ativo</h6>
                        <h3 class="card-text">R$ <?= number_format($total_saldo, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>

            <!-- Lucro Realizado -->
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">Lucro Realizado</h6>
                        <h3 class="card-text">R$ <?= number_format($total_recebido - $total_emprestado, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>

            <!-- Comissões -->
            <div class="col-md-3">
                <div class="card bg-warning">
                    <div class="card-body">
                        <h6 class="card-title">Comissões</h6>
                        <h3 class="card-text">R$ <?= number_format($comissoes_calculadas, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Empréstimos -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Empréstimos Ativos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($emprestimos)): ?>
                            <p class="text-muted text-center">Nenhum empréstimo encontrado.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Valor</th>
                                            <th>Parcelas</th>
                                            <th>Juros</th>
                                            <th>Progresso</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emprestimos as $emp): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($emp['cliente_nome']) ?></td>
                                                <td>R$ <?= number_format($emp['valor_emprestado'], 2, ',', '.') ?></td>
                                                <td>
                                                    <?= $emp['parcelas_pagas'] ?>/<?= $emp['total_parcelas'] ?>
                                                    <div class="progress" style="height: 5px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?= ($emp['parcelas_pagas'] / $emp['total_parcelas']) * 100 ?>%">
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= number_format($emp['juros_percentual'], 1) ?>%</td>
                                                <td>
                                                    <?php
                                                        $percentual = ($emp['total_recebido'] / ($emp['valor_parcela'] * $emp['total_parcelas'])) * 100;
                                                    ?>
                                                    <div class="progress">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?= $percentual ?>%">
                                                            <?= number_format($percentual, 1) ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="emprestimos/visualizar.php?id=<?= $emp['id'] ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Movimentações -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Últimas Movimentações</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($movimentacoes)): ?>
                            <p class="text-muted text-center">Nenhuma movimentação encontrada.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Conta</th>
                                            <th>Tipo</th>
                                            <th>Valor</th>
                                            <th>Descrição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($movimentacoes as $mov): ?>
                                            <tr>
                                                <td><?= date('d/m/Y H:i', strtotime($mov['data_movimentacao'])) ?></td>
                                                <td><?= htmlspecialchars($mov['conta_nome']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $mov['tipo'] === 'entrada' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($mov['tipo']) ?>
                                                    </span>
                                                </td>
                                                <td>R$ <?= number_format($mov['valor'], 2, ',', '.') ?></td>
                                                <td><?= htmlspecialchars($mov['descricao']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fechar alertas automaticamente após 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html> 