<?php
// Iniciar buffer de saída para evitar erros de "headers already sent"
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/autenticacao.php';

// Verificar permissões administrativas
apenasAdmin();

require_once __DIR__ . '/../includes/head.php';

// Inicializar variáveis
$mensagem = '';
$tipo_alerta = '';
$conta = null;
$movimentacoes = [];

// Verificar se um ID de conta foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: contas.php");
    exit;
}

$conta_id = intval($_GET['id']);

// Processar edição da conta
if (isset($_POST['salvar_conta'])) {
    $usuario_id = intval($_POST['usuario_id']);
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $comissao = floatval(str_replace(',', '.', $_POST['comissao']));
    $status = $_POST['status'];
    $saldo_inicial = floatval(str_replace(['.', ','], ['', '.'], $_POST['saldo_inicial']));
    
    if (empty($usuario_id) || empty($nome)) {
        $mensagem = "Por favor, preencha todos os campos obrigatórios.";
        $tipo_alerta = "danger";
    } else {
        // Verificar se o saldo inicial foi alterado
        $stmt = $conn->prepare("SELECT saldo_inicial FROM contas WHERE id = ?");
        $stmt->bind_param("i", $conta_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo_anterior = $result->fetch_assoc()['saldo_inicial'];
        
        // Iniciar transação
        $conn->begin_transaction();
        
        try {
            // Atualizar dados da conta
            $stmt = $conn->prepare("UPDATE contas SET 
                                    usuario_id = ?, 
                                    nome = ?, 
                                    descricao = ?, 
                                    comissao = ?, 
                                    status = ?, 
                                    saldo_inicial = ?,
                                    atualizado_em = NOW() 
                                    WHERE id = ?");
            
            $stmt->bind_param("issdsdi", $usuario_id, $nome, $descricao, $comissao, $status, $saldo_inicial, $conta_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar conta: " . $conn->error);
            }
            
            // Se o saldo inicial mudou, inserir uma movimentação de ajuste
            if ($saldo_inicial != $saldo_anterior) {
                $diferenca = $saldo_inicial - $saldo_anterior;
                $tipo_movimentacao = $diferenca > 0 ? 'entrada' : 'saida';
                $valor_ajuste = abs($diferenca);
                $descricao_ajuste = "Ajuste de saldo inicial";
                
                $stmt_mov = $conn->prepare("INSERT INTO movimentacoes_contas 
                                          (conta_id, tipo, valor, descricao, data_movimentacao) 
                                          VALUES (?, ?, ?, ?, NOW())");
                $stmt_mov->bind_param("isds", $conta_id, $tipo_movimentacao, $valor_ajuste, $descricao_ajuste);
                
                if (!$stmt_mov->execute()) {
                    throw new Exception("Erro ao registrar movimentação de ajuste: " . $conn->error);
                }
            }
            
            $conn->commit();
            $mensagem = "Conta atualizada com sucesso!";
            $tipo_alerta = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensagem = $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// Processar formulário de adição de movimentação
if (isset($_POST['adicionar_movimentacao'])) {
    $conta_id = intval($_POST['conta_id']);
    $tipo = $_POST['tipo'] ?? '';
    $valor = $_POST['valor'] ?? '';
    // Converter valor do formato brasileiro para formato numérico
    $valor = str_replace('.', '', $valor); // Remove pontos
    $valor = str_replace(',', '.', $valor); // Substitui vírgula por ponto
    $descricao = $_POST['descricao'] ?? '';
    $data_movimentacao = $_POST['data_movimentacao'] ?? date('Y-m-d');
    
    // Validação básica
    if (empty($tipo) || empty($valor) || !is_numeric($valor) || floatval($valor) <= 0) {
        $mensagem = "Por favor, preencha todos os campos corretamente.";
        $tipo_alerta = "danger";
    } else {
        // Verificar se a conta existe
        $sql_verificar_conta = "SELECT id, nome, usuario_id, saldo_inicial FROM contas WHERE id = ? AND status = 'ativo'";
        $stmt_verificar = $conn->prepare($sql_verificar_conta);
        $stmt_verificar->bind_param("i", $conta_id);
        $stmt_verificar->execute();
        $result_verificar = $stmt_verificar->get_result();
        
        if ($result_verificar && $result_verificar->num_rows > 0) {
            // Obter informações da conta
            $conta_info = $result_verificar->fetch_assoc();
            
            // Calcular o saldo atual com base no saldo inicial e movimentações
            $sql_movimentacoes = "SELECT COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0) as total_movimentacoes FROM movimentacoes_contas WHERE conta_id = ?";
            $stmt_movimentacoes = $conn->prepare($sql_movimentacoes);
            $stmt_movimentacoes->bind_param("i", $conta_id);
            $stmt_movimentacoes->execute();
            $result_movimentacoes = $stmt_movimentacoes->get_result();
            $total_movimentacoes = $result_movimentacoes->fetch_assoc()['total_movimentacoes'];
            
            $saldo_atual_conta = floatval($conta_info['saldo_inicial']) + floatval($total_movimentacoes);
            $valor_movimentacao = floatval($valor);
            
            // Se for saque (saída), verificar se há saldo suficiente
            if ($tipo === 'saida' && $valor_movimentacao > $saldo_atual_conta) {
                $mensagem = "Saldo insuficiente para realizar esta operação. Saldo atual: R$ " . number_format($saldo_atual_conta, 2, ',', '.');
                $tipo_alerta = "danger";
            } else {
                // Iniciar transação
                $conn->begin_transaction();
                
                try {
                    // Inserir movimentação
                    $sql_movimentacao = "INSERT INTO movimentacoes_contas (conta_id, tipo, valor, descricao, data_movimentacao) 
                                        VALUES (?, ?, ?, ?, ?)";
                    $stmt_mov = $conn->prepare($sql_movimentacao);
                    $stmt_mov->bind_param("isdss", $conta_id, $tipo, $valor_movimentacao, $descricao, $data_movimentacao);
                    
                    if ($stmt_mov->execute()) {
                        // Commit a transação
                        $conn->commit();
                        
                        // Recalcular o saldo atual para exibição
                        $stmt_recalc = $conn->prepare("SELECT 
                                                     COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0) as total_movimentacoes
                                                    FROM movimentacoes_contas 
                                                    WHERE conta_id = ?");
                        $stmt_recalc->bind_param("i", $conta_id);
                        $stmt_recalc->execute();
                        $result_recalc = $stmt_recalc->get_result();
                        $row_recalc = $result_recalc->fetch_assoc();
                        $total_movimentacoes = $row_recalc['total_movimentacoes'];
                        $saldo_atual = $conta_info['saldo_inicial'] + floatval($total_movimentacoes);
                        
                        $mensagem = "Movimentação " . ($tipo === 'entrada' ? "de entrada" : "de saída") . " registrada com sucesso!";
                        $tipo_alerta = "success";
                        
                        // Recarregar dados da conta e movimentações para exibição
                        $stmt = $conn->prepare("SELECT c.*, u.nome as usuario_nome FROM contas c 
                                              LEFT JOIN usuarios u ON c.usuario_id = u.id 
                                              WHERE c.id = ?");
                        $stmt->bind_param("i", $conta_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result && $result->num_rows > 0) {
                            $conta = $result->fetch_assoc();
                        }
                        
                        // Recarregar movimentações recentes
                        $stmt = $conn->prepare("SELECT * FROM movimentacoes_contas 
                                             WHERE conta_id = ? 
                                             ORDER BY data_movimentacao DESC, id DESC 
                                             LIMIT 10");
                        $stmt->bind_param("i", $conta_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $movimentacoes = [];
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $movimentacoes[] = $row;
                            }
                        }
                        
                        // Atualizar estatísticas
                        $stmt = $conn->prepare("SELECT 
                                             COUNT(*) as total_movimentacoes,
                                             SUM(CASE WHEN tipo = 'entrada' THEN 1 ELSE 0 END) as total_entradas,
                                             SUM(CASE WHEN tipo = 'saida' THEN 1 ELSE 0 END) as total_saidas,
                                             SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as valor_entradas,
                                             SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as valor_saidas,
                                             MAX(data_movimentacao) as ultima_movimentacao
                                            FROM movimentacoes_contas 
                                            WHERE conta_id = ?");
                        $stmt->bind_param("i", $conta_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $estatisticas = $result->fetch_assoc();
                    } else {
                        throw new Exception("Erro ao registrar movimentação: " . $conn->error);
                    }
                } catch (Exception $e) {
                    // Rollback em caso de erro
                    $conn->rollback();
                    $mensagem = "Erro: " . $e->getMessage();
                    $tipo_alerta = "danger";
                }
            }
        } else {
            $mensagem = "Conta não encontrada ou inativa.";
            $tipo_alerta = "danger";
        }
    }
}

// Buscar dados da conta
$stmt = $conn->prepare("SELECT c.*, u.nome as usuario_nome FROM contas c 
                        LEFT JOIN usuarios u ON c.usuario_id = u.id 
                        WHERE c.id = ?");
$stmt->bind_param("i", $conta_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $conta = $result->fetch_assoc();
} else {
    // Conta não encontrada
    header("Location: contas.php?erro=1&msg=" . urlencode("Conta não encontrada."));
    exit;
}

// Calcular saldo atual
$stmt = $conn->prepare("SELECT 
                       COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0) as total_movimentacoes
                      FROM movimentacoes_contas 
                      WHERE conta_id = ?");
$stmt->bind_param("i", $conta_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_movimentacoes = $row['total_movimentacoes'];
$saldo_atual = $conta['saldo_inicial'] + $total_movimentacoes;

// Buscar movimentações recentes
$stmt = $conn->prepare("SELECT * FROM movimentacoes_contas 
                       WHERE conta_id = ? 
                       ORDER BY data_movimentacao DESC, id DESC 
                       LIMIT 10");
$stmt->bind_param("i", $conta_id);
$stmt->execute();
$result = $stmt->get_result();
$movimentacoes = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $movimentacoes[] = $row;
    }
}

// Buscar todos os investidores e usuários
$sql_usuarios = "SELECT id, nome, nivel_autoridade FROM usuarios ORDER BY nome";
$result_usuarios = $conn->query($sql_usuarios);
$usuarios = [];

if ($result_usuarios && $result_usuarios->num_rows > 0) {
    while ($row = $result_usuarios->fetch_assoc()) {
        // Marcar o tipo de usuário
        if ($row['nivel_autoridade'] == 'administrador') {
            $row['nome'] = $row['nome'] . ' (Administrador)';
        } elseif ($row['nivel_autoridade'] == 'investidor') {
            $row['nome'] = $row['nome'] . ' (Investidor)';
        }
        $usuarios[] = $row;
    }
}

// Buscar estatísticas da conta
$stmt = $conn->prepare("SELECT 
                     COUNT(*) as total_movimentacoes,
                     SUM(CASE WHEN tipo = 'entrada' THEN 1 ELSE 0 END) as total_entradas,
                     SUM(CASE WHEN tipo = 'saida' THEN 1 ELSE 0 END) as total_saidas,
                     SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as valor_entradas,
                     SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as valor_saidas,
                     MAX(data_movimentacao) as ultima_movimentacao
                    FROM movimentacoes_contas 
                    WHERE conta_id = ?");
$stmt->bind_param("i", $conta_id);
$stmt->execute();
$result = $stmt->get_result();
$estatisticas = $result->fetch_assoc();

// Buscar número de empréstimos associados (apenas se for conta de investidor)
$sql_emprestimos = "SELECT COUNT(*) as total_emprestimos 
                  FROM emprestimos e 
                  INNER JOIN usuarios u ON e.investidor_id = u.id
                  WHERE u.id = ?";
$stmt = $conn->prepare($sql_emprestimos);
$stmt->bind_param("i", $conta['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_emprestimos = $row['total_emprestimos'];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Editar Conta</h2>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($conta['nome']) ?> • 
                ID: <?= $conta_id ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="contas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
            <a href="movimentacoes.php?conta_id=<?= $conta_id ?>" class="btn btn-info text-white">
                <i class="bi bi-cash-coin"></i> Movimentações
            </a>
        </div>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Coluna de Edição de Conta -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Dados da Conta</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="usuario_id" class="form-label">Proprietário da Conta</label>
                                <select class="form-select" id="usuario_id" name="usuario_id" required>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?= $usuario['id'] ?>" <?= $usuario['id'] == $conta['usuario_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($usuario['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="ativo" <?= $conta['status'] == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="inativo" <?= $conta['status'] == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="nome" class="form-label">Nome da Conta</label>
                                <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($conta['nome']) ?>" required>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="descricao" class="form-label">Descrição</label>
                                <textarea class="form-control" id="descricao" name="descricao" rows="2"><?= htmlspecialchars($conta['descricao']) ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="comissao" class="form-label">Comissão do Investidor (%)</label>
                                <input type="text" class="form-control" id="comissao" name="comissao" value="<?= number_format($conta['comissao'], 2, ',', '.') ?>" required>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> Percentual do lucro que o investidor receberá.
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="saldo_inicial" class="form-label">Saldo Inicial (R$)</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" class="form-control money" id="saldo_inicial" name="saldo_inicial" value="<?= number_format($conta['saldo_inicial'], 2, ',', '.') ?>" required>
                                </div>
                                <div class="form-text text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Alterar este valor gerará uma movimentação de ajuste.
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="alert alert-info d-flex justify-content-between">
                                    <div>
                                        <strong>Saldo Atual:</strong> 
                                        <span class="fs-5 <?= $saldo_atual < 0 ? 'text-danger' : 'text-success' ?>">
                                            R$ <?= number_format($saldo_atual, 2, ',', '.') ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-muted">Criado em: <?= date('d/m/Y H:i', strtotime($conta['criado_em'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" name="salvar_conta" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salvar Alterações
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Card de Movimentações Recentes -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Movimentações Recentes</h5>
                        <a href="movimentacoes.php?conta_id=<?= $conta_id ?>" class="btn btn-sm btn-outline-primary">
                            Ver Todas
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($movimentacoes)): ?>
                        <div class="p-3 text-center text-muted">
                            <i class="bi bi-info-circle"></i> Nenhuma movimentação registrada.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Descrição</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimentacoes as $mov): 
                                        $tipo_class = $mov['tipo'] === 'entrada' ? 'text-success' : 'text-danger';
                                        $tipo_icone = $mov['tipo'] === 'entrada' ? 'arrow-up-circle-fill' : 'arrow-down-circle-fill';
                                    ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($mov['data_movimentacao'])) ?></td>
                                            <td>
                                                <span class="<?= $tipo_class ?>">
                                                    <i class="bi bi-<?= $tipo_icone ?>"></i> 
                                                    <?= $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída' ?>
                                                </span>
                                            </td>
                                            <td class="<?= $tipo_class ?> fw-bold">
                                                R$ <?= number_format($mov['valor'], 2, ',', '.') ?>
                                            </td>
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
        
        <!-- Coluna de Resumo e Ações -->
        <div class="col-lg-4">
            <!-- Card de Ações -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Ações</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#adicionarMovimentacaoModal">
                            <i class="bi bi-plus-circle me-2"></i> Nova Movimentação
                        </button>
                        <a href="movimentacoes.php?conta_id=<?= $conta_id ?>" class="btn btn-info text-white">
                            <i class="bi bi-cash-coin me-2"></i> Todas as Movimentações
                        </a>
                        <?php if ($total_emprestimos > 0): ?>
                        <a href="../emprestimos/index.php?investidor_id=<?= $conta['usuario_id'] ?>" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-text me-2"></i> Ver Empréstimos (<?= $total_emprestimos ?>)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Card de Estatísticas -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Estatísticas</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Total de Movimentações:</span>
                            <strong><?= $estatisticas['total_movimentacoes'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Entradas:</span>
                            <strong class="text-success"><?= $estatisticas['total_entradas'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Saídas:</span>
                            <strong class="text-danger"><?= $estatisticas['total_saidas'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Total Recebido:</span>
                            <strong class="text-success">R$ <?= number_format($estatisticas['valor_entradas'] ?? 0, 2, ',', '.') ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Total Retirado:</span>
                            <strong class="text-danger">R$ <?= number_format($estatisticas['valor_saidas'] ?? 0, 2, ',', '.') ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Última Movimentação:</span>
                            <strong><?= $estatisticas['ultima_movimentacao'] ? date('d/m/Y', strtotime($estatisticas['ultima_movimentacao'])) : 'Nenhuma' ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Adicionar Movimentação -->
<div class="modal fade" id="adicionarMovimentacaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Movimentação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="conta_id" value="<?= $conta_id ?>">
                    
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="entrada">Entrada (Aporte / Crédito)</option>
                            <option value="saida">Saída (Saque / Débito)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valor" class="form-label">Valor (R$)</label>
                        <input type="text" class="form-control money" id="valor" name="valor" value="0,00" required>
                        <div id="valor_aviso_saldo" class="form-text text-danger d-none">
                            <i class="bi bi-exclamation-triangle"></i> O valor do saque excede o saldo disponível.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="data_movimentacao" class="form-label">Data</label>
                        <input type="date" class="form-control" id="data_movimentacao" name="data_movimentacao" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btn_adicionar" name="adicionar_movimentacao" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para campos monetários
    document.querySelectorAll('.money').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value === '') {
                value = '0';
            }
            value = (parseInt(value) / 100).toFixed(2).replace('.', ',');
            e.target.value = value;
        });
    });
    
    // Formatação para campo de comissão
    document.getElementById('comissao').addEventListener('input', function(e) {
        let value = e.target.value.replace(/[^\d,]/g, '');
        
        // Se tiver mais de uma vírgula, mantém só a primeira
        value = value.replace(/,/g, function(match, offset, string) {
            return offset === string.indexOf(',') ? match : '';
        });
        
        // Limita a 100%
        let numValue = parseFloat(value.replace(',', '.')) || 0;
        if (numValue > 100) {
            numValue = 100;
        }
        
        // Formata com 2 casas decimais
        e.target.value = numValue.toFixed(2).replace('.', ',');
    });
    
    // Verificação de saldo ao selecionar saída
    const tipoSelect = document.getElementById('tipo');
    const valorInput = document.getElementById('valor');
    const valorAviso = document.getElementById('valor_aviso_saldo');
    const btnAdicionar = document.getElementById('btn_adicionar');
    const saldoAtual = <?= $saldo_atual ?>;
    
    function verificarSaldo() {
        if (tipoSelect.value === 'saida') {
            const valor = parseFloat(valorInput.value.replace('.', '').replace(',', '.')) || 0;
            
            if (valor > saldoAtual) {
                valorAviso.classList.remove('d-none');
                btnAdicionar.disabled = true;
            } else {
                valorAviso.classList.add('d-none');
                btnAdicionar.disabled = false;
            }
        } else {
            valorAviso.classList.add('d-none');
            btnAdicionar.disabled = false;
        }
    }
    
    tipoSelect.addEventListener('change', verificarSaldo);
    valorInput.addEventListener('input', verificarSaldo);
    
    // Verificar ao abrir o modal
    const adicionarModal = document.getElementById('adicionarMovimentacaoModal');
    adicionarModal.addEventListener('show.bs.modal', function() {
        setTimeout(verificarSaldo, 300);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; 
// Liberar o buffer no final
ob_end_flush();
?> 