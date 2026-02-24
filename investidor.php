<?php
// Iniciar buffer de saída para evitar erros de "headers already sent"
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/autenticacao.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/funcoes_comissoes.php';

// Verificar se o usuário logado é um investidor
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Negar acesso a administradores (que devem usar o dashboard.php)
if (isset($_SESSION['nivel_autoridade']) && 
    ($_SESSION['nivel_autoridade'] === 'administrador' || 
     $_SESSION['nivel_autoridade'] === 'superadmin')) {
    header("Location: dashboard.php");
    exit;
}

// Verificar se o usuário é um investidor
if (!isset($_SESSION['nivel_autoridade']) || $_SESSION['nivel_autoridade'] !== 'investidor') {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar informações do investidor
$sql_usuario = "SELECT nome, email FROM usuarios WHERE id = ?";
$stmt_usuario = $conn->prepare($sql_usuario);
$stmt_usuario->bind_param("i", $usuario_id);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();
$usuario = $result_usuario->fetch_assoc();

// Buscar contas do investidor
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

// Atualizar o saldo da conta após todas as operações (adicionar no início do arquivo, logo após obter os dados das contas)
if (!empty($contas)) {
    // Recalcular o saldo atual para refletir todas as movimentações, incluindo retornos recentes
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

// Buscar empréstimos associados ao investidor
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
        $comissoes_calculadas = round($lucro_total_previsto * ($percentual_comissao / 100), 2);
    }
}

// Buscar últimas movimentações das contas do investidor
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

// Processar aporte (adição de saldo)
if (isset($_POST['realizar_aporte'])) {
    // Redirecionar para a página principal com mensagem informativa
    $mensagem = "Apenas administradores podem realizar aportes. Entre em contato com o administrador.";
    $tipo_alerta = "warning";
    header("Location: investidor.php?alerta=1&tipo=" . $tipo_alerta . "&msg=" . urlencode($mensagem));
    exit;
}

// Verificar empréstimos recém-quitados para retornar o capital ao investidor e processar comissões
$sql_emprestimos_quitados = "SELECT 
                                    e.id,
                                    e.cliente_id,
                                    e.valor_emprestado,
                                    e.parcelas as total_parcelas,
                                    c.nome as cliente_nome,
                                    (
                                        SELECT COUNT(*) 
                                        FROM parcelas 
                                        WHERE emprestimo_id = e.id
                                    ) as parcelas_existentes,
                                    (
                                        SELECT COUNT(*) 
                                        FROM parcelas 
                                        WHERE emprestimo_id = e.id 
                                        AND status = 'pago'
                                    ) as parcelas_pagas,
                                    (
                                        SELECT SUM(valor_pago) 
                                        FROM parcelas 
                                        WHERE emprestimo_id = e.id 
                                        AND status = 'pago'
                                    ) as total_pago,
                                    (
                                        SELECT COUNT(*) 
                                        FROM movimentacoes_contas 
                                        WHERE descricao LIKE CONCAT('Comissão total - Empréstimo #', e.id, '%')
                                        AND conta_id = ?
                                    ) as comissao_processada
                                FROM 
                                    emprestimos e
                                INNER JOIN
                                    clientes c ON e.cliente_id = c.id
                                WHERE 
                                    e.investidor_id = ?
                                    AND e.status = 'ativo'";

$stmt_quitados = $conn->prepare($sql_emprestimos_quitados);
$stmt_quitados->bind_param("ii", $contas[0]['id'], $usuario_id);
$stmt_quitados->execute();
$result_quitados = $stmt_quitados->get_result();

if ($result_quitados && $result_quitados->num_rows > 0) {
    $conn->begin_transaction();
    
    try {
        $total_capital_retornado = 0;
        $emprestimos_processados = 0;
        $total_comissoes_processadas = 0;
        
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
        $mensagem = "Erro ao processar empréstimos: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

// Verificar empréstimos ativos e adicionar movimentação de saída de capital se ainda não existir
if (!empty($contas)) {
    // Buscar empréstimos ativos que ainda não têm registro de saída de capital
    $sql_verificar_emprestimos = "SELECT 
                                    e.id,
                                    e.cliente_id,
                                    e.valor_emprestado,
                                    c.nome as cliente_nome,
                                    e.data_inicio
                                 FROM 
                                    emprestimos e
                                 INNER JOIN
                                    clientes c ON e.cliente_id = c.id
                                 WHERE 
                                    e.investidor_id = ?
                                    AND e.status = 'ativo'
                                    AND NOT EXISTS (
                                        SELECT 1 
                                        FROM movimentacoes_contas mc 
                                        WHERE mc.descricao LIKE CONCAT('Empréstimo #', e.id, '%')
                                        AND mc.tipo = 'saida'
                                    )";
    
    $stmt_verificar = $conn->prepare($sql_verificar_emprestimos);
    $stmt_verificar->bind_param("i", $usuario_id);
    $stmt_verificar->execute();
    $result_verificar = $stmt_verificar->get_result();
    
    if ($result_verificar && $result_verificar->num_rows > 0) {
        $conn->begin_transaction();
        
        try {
            $total_capital_emprestado = 0;
            $emprestimos_novos = 0;
            
            while ($emp_ativo = $result_verificar->fetch_assoc()) {
                $valor_capital = floatval($emp_ativo['valor_emprestado']);
                $emprestimo_id = $emp_ativo['id'];
                
                // Adicionar valor do capital como saída na conta
                $descricao = "Empréstimo #{$emprestimo_id} para {$emp_ativo['cliente_nome']} em " . date('d/m/Y', strtotime($emp_ativo['data_inicio']));
                
                $stmt_movimentacao = $conn->prepare("INSERT INTO movimentacoes_contas 
                                                   (conta_id, tipo, valor, descricao, data_movimentacao) 
                                                   VALUES (?, 'saida', ?, ?, ?)");
                $stmt_movimentacao->bind_param("idss", 
                                              $contas[0]['id'], 
                                              $valor_capital, 
                                              $descricao,
                                              $emp_ativo['data_inicio']);
                
                if (!$stmt_movimentacao->execute()) {
                    throw new Exception("Erro ao adicionar registro de empréstimo na conta: " . $conn->error);
                }
                
                $total_capital_emprestado += $valor_capital;
                $emprestimos_novos++;
            }
            
            $conn->commit();
            
            if ($emprestimos_novos > 0) {
                $mensagem_emprestimos = "Foram registrados {$emprestimos_novos} novos empréstimos com saída total de capital de R$ " . 
                                       number_format($total_capital_emprestado, 2, ',', '.');
                
                if (!isset($mensagem)) {
                    $mensagem = $mensagem_emprestimos;
                    $tipo_alerta = "info";
                } else {
                    $mensagem .= "<br>" . $mensagem_emprestimos;
                }
                
                // Recarregar dados da conta para atualizar o saldo exibido
                if (isset($contas[0])) {
                    $stmt_recarregar = $conn->prepare("SELECT 
                                                      c.id, 
                                                      c.saldo_inicial + COALESCE(SUM(CASE WHEN mc.tipo = 'entrada' THEN mc.valor ELSE -mc.valor END), 0) as saldo_atual 
                                                     FROM contas c 
                                                     LEFT JOIN movimentacoes_contas mc ON c.id = mc.conta_id 
                                                     WHERE c.id = ? 
                                                     GROUP BY c.id, c.saldo_inicial");
                    $stmt_recarregar->bind_param("i", $contas[0]['id']);
                    $stmt_recarregar->execute();
                    $result_recarregar = $stmt_recarregar->get_result();
                    
                    if ($result_recarregar && $result_recarregar->num_rows > 0) {
                        $saldo_atualizado = $result_recarregar->fetch_assoc();
                        $contas[0]['saldo_atual'] = $saldo_atualizado['saldo_atual'];
                    }
                }
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Erro ao registrar saídas de capital: " . $e->getMessage());
        }
    }
}

// Buscar status das comissões
$status_comissoes = buscarStatusComissoes($conn, $usuario_id);

// Exibir mensagem de alerta se enviada via GET
if (isset($_GET['alerta']) && isset($_GET['msg']) && isset($_GET['tipo'])) {
    $mensagem = $_GET['msg'];
    $tipo_alerta = $_GET['tipo'];
} else if (isset($_GET['sucesso']) && isset($_GET['msg'])) {
    $mensagem = $_GET['msg'];
    $tipo_alerta = ($_GET['sucesso'] == '1') ? "success" : "danger";
}

// Certifique-se de liberar o buffer no final do arquivo
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Dashboard do Investidor</h2>
    </div>

    <?php if (isset($mensagem)): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['acesso_negado']) && $_SESSION['acesso_negado']): ?>
        <div class="alert alert-danger alert-dismissible fade show border border-danger border-3" role="alert">
            <div class="d-flex align-items-center">
                <div class="fs-1 me-3"><i class="bi bi-shield-exclamation"></i></div>
                <div>
                    <h4 class="alert-heading">Acesso Negado!</h4>
                    <p class="mb-0"><?= htmlspecialchars($_SESSION['acesso_negado_mensagem']) ?></p>
                    <?php if(isset($_SESSION['pagina_tentativa'])): ?>
                    <small class="d-block mt-2">Tentativa de acesso a: <?= htmlspecialchars($_SESSION['pagina_tentativa']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="<?= isset($_SESSION['acesso_negado']) ? '$_SESSION[\'acesso_negado\'] = false;' : '' ?>"></button>
        </div>
        <?php 
        // Limpar a mensagem após exibição
        $_SESSION['acesso_negado'] = false;
        ?>
    <?php endif; ?>

    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'acesso_negado' && !isset($_SESSION['acesso_negado'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Acesso Negado!</strong> Você não tem permissão para acessar a área administrativa.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Cards Resumo e Conta Unificados -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0"><i class="bi bi-wallet2 me-2"></i>Minha Conta</h5>
        </div>
        <div class="card-body">
            <?php if (empty($contas)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Você não possui uma conta ativa. Entre em contato com o administrador para criar sua conta de investimento.
                </div>
            <?php else: 
                // Como agora é apenas uma conta por investidor, pegamos a primeira (e única)
                $conta = $contas[0]; 
            ?>
                <div class="row g-4">
                    <!-- Informações da Conta -->
                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Minha Conta de Investimento</h6>
                            </div>
                            <div class="card-body">
                                <p class="card-text text-muted"><?= htmlspecialchars(isset($conta['descricao']) ? $conta['descricao'] : 'Sem descrição') ?></p>
                                
                                <div class="mt-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Saldo Atual:</span>
                                        <span class="fw-bold <?= $conta['saldo_atual'] < 0 ? 'text-danger' : 'text-success' ?>">
                                            R$ <?= number_format($conta['saldo_atual'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Sua Comissão:</span>
                                        <span class="fw-bold"><?= number_format($conta['comissao'], 2, ',', '.') ?>%</span>
                                    </div>
                                    <small class="text-muted d-block text-end">(Administrador: <?= number_format(100 - $conta['comissao'], 2, ',', '.') ?>%)</small>
                                </div>
                                
                                <div class="mt-2">
                                    <a href="configuracoes/movimentacoes.php?conta_id=<?= $conta['id'] ?>" class="btn btn-outline-info w-100">
                                        <i class="bi bi-list-ul me-1"></i>Movimentações
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cards de Resumo -->
                    <div class="col-lg-6">
                        <div class="row row-cols-1 row-cols-md-2 g-3 h-100">
                            <div class="col">
                                <div class="card shadow-sm border-primary h-100">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">Total Emprestado</h6>
                                        <h4 class="card-text text-primary">R$ <?= number_format($total_emprestado, 2, ',', '.') ?></h4>
                                        <small class="text-muted">Em <?= count($emprestimos) ?> empréstimo(s) ativo(s)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm border-info h-100">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">Valor Recebido</h6>
                                        <h4 class="card-text text-info">R$ <?= number_format($total_recebido, 2, ',', '.') ?></h4>
                                        <small class="text-muted">De parcelas pagas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm border-warning h-100">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">Sua Comissão</h6>
                                        <h4 class="card-text text-warning">R$ <?= number_format($comissoes_calculadas, 2, ',', '.') ?></h4>
                                        <small class="text-muted">Baseado nas suas taxas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm border-success h-100">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">Parcelas Pagas</h6>
                                        <h4 class="card-text text-success"><?= $total_parcelas_pagas ?></h4>
                                        <small class="text-muted">De todos empréstimos</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Empréstimos Ativos -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0"><i class="bi bi-cash-stack me-2"></i>Empréstimos Ativos</h5>
        </div>
        <div class="card-body">
            <?php if (empty($emprestimos)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Você não possui empréstimos ativos.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($emprestimos as $emp): 
                        // Calcular comissão sobre o lucro previsto
                        $percentual_comissao = !empty($contas) ? floatval($contas[0]['comissao']) : 0;
                        $lucro_previsto = floatval($emp['lucro_previsto']);
                        $comissao = $lucro_previsto * ($percentual_comissao / 100);
                        
                        // Calcular progresso
                        $parcelas_pagas = intval($emp['parcelas_pagas']);
                        $total_parcelas = intval($emp['total_parcelas']);
                        $progresso = $total_parcelas > 0 ? ($parcelas_pagas / $total_parcelas) * 100 : 0;
                    ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?= htmlspecialchars($emp['cliente_nome']) ?></h6>
                                        <span class="badge bg-light text-dark"><?= $parcelas_pagas ?>/<?= $total_parcelas ?> parcelas</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Valor emprestado:</span>
                                            <span class="fw-bold">R$ <?= number_format($emp['valor_emprestado'], 2, ',', '.') ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Taxa de juros:</span>
                                            <span><?= number_format($emp['juros_percentual'], 2, ',', '.') ?>%</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Valor da parcela:</span>
                                            <span>R$ <?= number_format($emp['valor_parcela'], 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                    <div class="mb-3 pt-2 border-top">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Valor recebido:</span>
                                            <span class="text-info fw-bold">R$ <?= number_format($emp['total_recebido'], 2, ',', '.') ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Sua comissão (prevista):</span>
                                            <span class="text-warning fw-bold">R$ <?= number_format($comissao, 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Progresso do empréstimo -->
                                    <div class="mt-3">
                                        <span class="text-muted small">Progresso de pagamento:</span>
                                        <div class="progress mt-1" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                style="width: <?= $progresso ?>%;" 
                                                aria-valuenow="<?= $progresso ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <span class="text-muted small"><?= $parcelas_pagas ?> pagas</span>
                                            <span class="text-muted small"><?= number_format($progresso, 0) ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detalhes de Empréstimos -->
    <?php if (count($emprestimos) > 0): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Detalhes dos Empréstimos</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Valor Emprestado</th>
                            <th>Parcelas</th>
                            <th>Progresso</th>
                            <th>Recebido</th>
                            <th>Comissão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emprestimos as $emp): 
                            // Calcular comissão sobre o lucro previsto
                            $percentual_comissao = !empty($contas) ? floatval($contas[0]['comissao']) : 0;
                            $lucro_previsto = floatval($emp['lucro_previsto']);
                            $comissao = $lucro_previsto * ($percentual_comissao / 100);
                            
                            // Calcular progresso
                            $parcelas_pagas = intval($emp['parcelas_pagas']);
                            $total_parcelas = intval($emp['total_parcelas']);
                            $progresso = $total_parcelas > 0 ? ($parcelas_pagas / $total_parcelas) * 100 : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($emp['cliente_nome']) ?></td>
                            <td>R$ <?= number_format($emp['valor_emprestado'], 2, ',', '.') ?></td>
                            <td><?= $parcelas_pagas ?> / <?= $total_parcelas ?></td>
                            <td>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progresso ?>%;" 
                                         aria-valuenow="<?= $progresso ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td>R$ <?= number_format($emp['total_recebido'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($comissao, 2, ',', '.') ?> <small class="text-muted">(prevista)</small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th>Totais</th>
                            <th>R$ <?= number_format($total_emprestado, 2, ',', '.') ?></th>
                            <th><?= $total_parcelas_pagas ?> parcelas</th>
                            <th></th>
                            <th>R$ <?= number_format($total_recebido, 2, ',', '.') ?></th>
                            <th>R$ <?= number_format($comissoes_calculadas, 2, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Explicação do Cálculo de Comissões -->
            <div class="card bg-light mt-3">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Como funciona o sistema de comissões e retorno de capital</h6>
                    <p class="card-text small">
                        As comissões são calculadas com base no percentual de <?= !empty($contas) ? number_format($contas[0]['comissao'], 2, ',', '.') : '0,00' ?>% 
                        definido em sua conta de investimento. Esse percentual é aplicado sobre o lucro total previsto dos empréstimos.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="small fw-bold">Cálculo da Comissão</h6>
                            <ul class="mb-0 small">
                                <li><strong>Capital emprestado:</strong> R$ <?= number_format($total_emprestado, 2, ',', '.') ?></li>
                                <li><strong>Lucro total previsto:</strong> R$ <?= number_format($lucro_total_previsto, 2, ',', '.') ?></li>
                                <li><strong>Sua comissão prevista (<?= !empty($contas) ? number_format($contas[0]['comissao'], 2, ',', '.') : '0,00' ?>%):</strong> R$ <?= number_format($comissoes_calculadas, 2, ',', '.') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="small fw-bold">Fluxo de Pagamentos</h6>
                            <ol class="mb-0 small">
                                <li>A comissão é calculada sobre o lucro total previsto do empréstimo</li>
                                <li>O capital investido (valor principal) é devolvido ao seu saldo somente após a quitação total do empréstimo</li>
                                <li>O sistema identifica automaticamente empréstimos quitados e credita o capital em sua conta</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Após o card de Últimas Movimentações -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-percentage me-1"></i>
            Status das Comissões
        </div>
        <div class="card-body">
            <!-- Cards de Resumo das Comissões -->
            <div class="row mb-4">
                <div class="col-xl-4 col-md-6">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Comissão Total Prevista</div>
                                    <div class="text-lg fw-bold">
                                        R$ <?php echo number_format($status_comissoes['resumo']['total_comissao_prevista'], 2, ',', '.'); ?>
                                    </div>
                                </div>
                                <i class="fas fa-calculator fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Comissões Recebidas</div>
                                    <div class="text-lg fw-bold">
                                        R$ <?php echo number_format($status_comissoes['resumo']['total_comissao_processada'], 2, ',', '.'); ?>
                                    </div>
                                </div>
                                <i class="fas fa-check-circle fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Comissões Pendentes</div>
                                    <div class="text-lg fw-bold">
                                        R$ <?php echo number_format($status_comissoes['resumo']['total_comissao_pendente'], 2, ',', '.'); ?>
                                    </div>
                                </div>
                                <i class="fas fa-clock fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Empréstimos com Status de Comissões -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="tabelaComissoes">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Progresso</th>
                            <th>Comissão Prevista</th>
                            <th>Recebido</th>
                            <th>Pendente</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status_comissoes['emprestimos'] as $emp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($emp['emprestimo']['cliente_nome']); ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $emp['status']['progresso']; ?>%"
                                         aria-valuenow="<?php echo $emp['status']['progresso']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo number_format($emp['status']['progresso'], 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $emp['emprestimo']['parcelas_pagas']; ?> de <?php echo $emp['emprestimo']['total_parcelas']; ?> parcelas
                                </small>
                            </td>
                            <td>R$ <?php echo number_format($emp['comissoes']['prevista'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($emp['comissoes']['processada'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($emp['comissoes']['pendente'], 2, ',', '.'); ?></td>
                            <td class="text-center">
                                <?php if ($emp['status']['finalizado']): ?>
                                    <span class="badge bg-success">Finalizado</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Em Andamento</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips do Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<script>
$(document).ready(function() {
    // Inicializar DataTable para a tabela de comissões
    $('#tabelaComissoes').DataTable({
        order: [[1, 'desc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; 

// Liberar o buffer de saída no final
ob_end_flush();
?> 