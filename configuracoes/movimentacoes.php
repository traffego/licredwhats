<?php
// Iniciar buffer de saída para evitar erros de "headers already sent"
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/head.php';

// Verificar se é um investidor acessando suas próprias movimentações
$is_investidor = isset($_SESSION['nivel_autoridade']) && $_SESSION['nivel_autoridade'] === 'investidor';
$is_admin = temPermissao('admin');
$meus_aportes = isset($_GET['meus_aportes']) && $_GET['meus_aportes'] == 1;
$usuario_id = $_SESSION['usuario_id'] ?? 0;

// Verificar permissões
if (!$is_admin && !$is_investidor) {
    header("Location: ../index.php");
    exit;
}

// Processar ações
$mensagem = '';
$tipo_alerta = '';

// Verificar se um ID de conta foi fornecido
if ($is_investidor && $meus_aportes) {
    // Se for investidor acessando "meus aportes", buscar automaticamente a conta do investidor
    $stmt = $conn->prepare("SELECT id FROM contas WHERE usuario_id = ? AND status = 'ativo' LIMIT 1");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conta_id = $result->fetch_assoc()['id'];
    } else {
        // Investidor não tem conta, redirecionar usando JavaScript
        $redirect_url = "../investidor.php?erro=1&msg=" . urlencode("Você não possui uma conta ativa.");
        echo "<script>window.location.href = '$redirect_url';</script>";
        echo '<meta http-equiv="refresh" content="0;url='.$redirect_url.'">';
        exit;
    }
} elseif (!isset($_GET['conta_id']) || empty($_GET['conta_id'])) {
    // Não é o caso especial de "meus aportes" e não tem conta_id
    $redirect_url = $is_admin ? "contas.php" : "../investidor.php";
    echo "<script>window.location.href = '$redirect_url';</script>";
    echo '<meta http-equiv="refresh" content="0;url='.$redirect_url.'">';
    exit;
} else {
    $conta_id = intval($_GET['conta_id']);
}

// Obter detalhes da conta
$stmt = $conn->prepare("SELECT c.*, u.nome as usuario_nome FROM contas c 
                        JOIN usuarios u ON c.usuario_id = u.id 
                        WHERE c.id = ?");
$stmt->bind_param("i", $conta_id);
$stmt->execute();
$conta = $stmt->get_result()->fetch_assoc();

if (!$conta) {
    $redirect_url = $is_admin ? "contas.php" : "../investidor.php";
    echo "<script>window.location.href = '$redirect_url';</script>";
    echo '<meta http-equiv="refresh" content="0;url='.$redirect_url.'">';
    exit;
}

// Verificar se o investidor está tentando acessar sua própria conta
if ($is_investidor && !$is_admin && $conta['usuario_id'] != $usuario_id) {
    // Tentativa de acesso a conta de outro investidor
    $_SESSION['acesso_negado'] = true;
    $_SESSION['acesso_negado_mensagem'] = "Você só pode visualizar movimentações da sua própria conta!";
    $_SESSION['pagina_tentativa'] = $_SERVER['REQUEST_URI'];
    $redirect_url = "../investidor.php?erro=acesso_negado";
    echo "<script>window.location.href = '$redirect_url';</script>";
    echo '<meta http-equiv="refresh" content="0;url='.$redirect_url.'">';
    exit;
}

// Processar formulário de adição de movimentação
if (isset($_POST['adicionar_movimentacao'])) {
    // Verificar se conta_id foi enviado e não está vazio
    if (!isset($_POST['conta_id']) || empty($_POST['conta_id'])) {
        // Tentar usar o valor do campo oculto como fallback
        if (isset($_POST['conta_id_hidden']) && !empty($_POST['conta_id_hidden'])) {
            $conta_id = $_POST['conta_id_hidden'];
        } else {
            $mensagem = "ID da conta não especificado.";
            $tipo_alerta = "danger";
        }
    } else {
        $conta_id = $_POST['conta_id'];
        $tipo = $_POST['tipo'] ?? '';
        $valor = $_POST['valor'] ?? '';
        // Converter valor do formato brasileiro (1.234,56) para formato numérico (1234.56)
        $valor = str_replace('.', '', $valor); // Remove pontos
        $valor = str_replace(',', '.', $valor); // Substitui vírgula por ponto
        $descricao = $_POST['descricao'] ?? '';
    
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
                
                $saldo_atual = floatval($conta_info['saldo_inicial']) + floatval($total_movimentacoes);
                $valor_movimentacao = floatval($valor);
                
                // Se for saque (saída), verificar se há saldo suficiente
                if ($tipo === 'saida' && $valor_movimentacao > $saldo_atual) {
                    $mensagem = "Saldo insuficiente para realizar esta operação. Saldo atual: R$ " . number_format($saldo_atual, 2, ',', '.');
                    $tipo_alerta = "danger";
                } else {
                    // Iniciar transação
                    $conn->begin_transaction();
                    
                    try {
                        // Inserir movimentação
                        $sql_movimentacao = "INSERT INTO movimentacoes_contas (conta_id, tipo, valor, descricao, data_movimentacao) 
                                            VALUES (?, ?, ?, ?, NOW())";
                        $stmt_mov = $conn->prepare($sql_movimentacao);
                        $stmt_mov->bind_param("isds", $conta_id, $tipo, $valor_movimentacao, $descricao);
                        
                        if ($stmt_mov->execute()) {
                            // Commit a transação primeiro
                            $conn->commit();
                            
                            // Recalcular o saldo atual após a inserção e commit
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
                            
                            // Atualizar também a conta para exibição
                            $conta['saldo_inicial'] = $conta_info['saldo_inicial']; 
                            
                            $mensagem = "Movimentação " . ($tipo === 'entrada' ? "de entrada" : "de saída") . " registrada com sucesso!";
                            $tipo_alerta = "success";
                            
                            // Se a movimentação foi bem-sucedida, registrar detalhes adicionais para saques
                            if ($tipo === 'saida' && stripos($descricao, 'saque') !== false) {
                                // Registrar informações adicionais do saque (se necessário)
                                $nome_usuario = obterNomeUsuario($conta_info['usuario_id'], $conn);
                                
                                // Registrar log do saque
                                registrarLog("Saque realizado - Conta: " . $conta_info['nome'] . 
                                          " (ID: " . $conta_id . ") - Valor: R$ " . 
                                          number_format($valor_movimentacao, 2, ',', '.') . 
                                          " - Usuário: " . $nome_usuario . 
                                          " - Descrição: " . $descricao, $conn);
                            }
                        } else {
                            throw new Exception("Erro ao registrar movimentação: " . $conn->error);
                        }
                    } catch (Exception $e) {
                        // Rollback em caso de erro
                        $conn->rollback();
                        $mensagem = "Erro: " . $e->getMessage();
                        $tipo_alerta = "danger";
                        
                        // Registrar erro no log
                        registrarLog("Erro ao processar movimentação: " . $e->getMessage(), $conn, true);
                    }
                }
            } else {
                $mensagem = "Conta não encontrada ou inativa.";
            $tipo_alerta = "danger";
            }
        }
    }
}

// Excluir movimentação (apenas admin)
if ($is_admin && isset($_POST['excluir_movimentacao']) && isset($_POST['movimentacao_id'])) {
    $movimentacao_id = intval($_POST['movimentacao_id']);
    
    $stmt = $conn->prepare("DELETE FROM movimentacoes_contas WHERE id = ? AND conta_id = ?");
    $stmt->bind_param("ii", $movimentacao_id, $conta_id);
    
    if ($stmt->execute()) {
        $mensagem = "Movimentação excluída com sucesso!";
        $tipo_alerta = "success";
    } else {
        $mensagem = "Erro ao excluir movimentação: " . $conn->error;
        $tipo_alerta = "danger";
    }
}

// Paginação
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Total de movimentações
$sql_total = "SELECT COUNT(*) as total FROM movimentacoes_contas mc
              WHERE mc.conta_id = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $conta_id);
$stmt_total->execute();
$total_movimentacoes = $stmt_total->get_result()->fetch_assoc()['total'];

// Calcular total de páginas
$total_pages = ceil($total_movimentacoes / $per_page);

// Consulta das movimentações
$sql = "SELECT mc.* FROM movimentacoes_contas mc
        WHERE mc.conta_id = ?
        ORDER BY mc.data_movimentacao DESC, mc.id DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $conta_id, $offset, $per_page);
$stmt->execute();
$movimentacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

// Definir o título da página
$titulo_pagina = $is_investidor && $meus_aportes ? "Histórico de Aportes" : "Movimentações da Conta";
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><?= $titulo_pagina ?></h2>
            <p class="text-muted mb-0">
                <i class="bi bi-person-fill"></i> <?= htmlspecialchars($conta['usuario_nome']) ?> - 
                <i class="bi bi-wallet2"></i> <?= htmlspecialchars($conta['nome']) ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= $is_investidor && !$is_admin ? '../investidor.php' : 'contas.php' ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
            <?php if ($is_admin): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adicionarMovimentacaoModal">
                <i class="bi bi-plus-circle"></i> Nova Movimentação
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    
    <!-- Card de resumo -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">Saldo Inicial</h6>
                    <h4 class="mb-0">R$ <?= number_format($conta['saldo_inicial'], 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total de Movimentações</h6>
                    <h4 class="mb-0 <?= $total_movimentacoes < 0 ? 'text-danger' : 'text-success' ?>">
                        R$ <?= number_format($total_movimentacoes, 2, ',', '.') ?>
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted">Saldo Atual</h6>
                    <h4 class="mb-0 <?= $saldo_atual < 0 ? 'text-danger' : 'text-success' ?>">
                        R$ <?= number_format($saldo_atual, 2, ',', '.') ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabela de movimentações -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($movimentacoes)): ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i> Nenhuma movimentação encontrada para esta conta.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Descrição</th>
                                <?php if ($is_admin): ?>
                                <th>Ações</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $saldo_calculado = $conta['saldo_inicial'];
                            $movimentacoes_ordenadas = array_reverse($movimentacoes); // Ordem cronológica
                            
                            foreach ($movimentacoes_ordenadas as $mov): 
                                $valor = floatval($mov['valor']);
                                if ($mov['tipo'] === 'entrada') {
                                    $saldo_calculado += $valor;
                                } else {
                                    $saldo_calculado -= $valor;
                                }
                                
                                $tipo_class = $mov['tipo'] === 'entrada' ? 'text-success' : 'text-danger';
                                $tipo_texto = $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída';
                                $tipo_icone = $mov['tipo'] === 'entrada' ? 'arrow-up-circle-fill' : 'arrow-down-circle-fill';
                            ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($mov['data_movimentacao'])) ?></td>
                                    <td>
                                        <span class="<?= $tipo_class ?>">
                                            <i class="bi bi-<?= $tipo_icone ?>"></i> <?= $tipo_texto ?>
                                        </span>
                                    </td>
                                    <td class="<?= $tipo_class ?> fw-bold">
                                        R$ <?= number_format($valor, 2, ',', '.') ?>
                                    </td>
                                    <td><?= htmlspecialchars($mov['descricao']) ?></td>
                                    <?php if ($is_admin): ?>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger excluir-movimentacao" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#excluirMovimentacaoModal"
                                                data-id="<?= $mov['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Navegação de movimentações">
                        <ul class="pagination justify-content-center mt-3">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= $meus_aportes ? 'meus_aportes=1' : "conta_id=$conta_id" ?>&page=<?= $page-1 ?>">Anterior</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= $meus_aportes ? 'meus_aportes=1' : "conta_id=$conta_id" ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= $meus_aportes ? 'meus_aportes=1' : "conta_id=$conta_id" ?>&page=<?= $page+1 ?>">Próxima</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($is_admin): ?>
<!-- Modal para Adicionar Movimentação -->
<div class="modal fade" id="adicionarMovimentacaoModal" tabindex="-1" aria-labelledby="adicionarMovimentacaoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Movimentação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <!-- Campo oculto para garantir que o conta_id seja sempre enviado -->
                    <input type="hidden" name="conta_id_hidden" value="<?= $conta_id ?>">
                    
                    <div class="mb-3">
                        <label for="conta_id" class="form-label">Conta</label>
                        <select class="form-select" id="conta_id" name="conta_id" required>
                            <?php
                            $stmt = $conn->prepare("SELECT id, nome FROM contas WHERE status = 'ativo'");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()):
                                $selected = ($row['id'] == $conta_id) ? 'selected' : '';
                            ?>
                                <option value="<?= $row['id'] ?>" <?= $selected ?>><?= htmlspecialchars($row['nome']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="entrada">Entrada</option>
                            <option value="saida">Saída</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valor" class="form-label">Valor (R$)</label>
                        <input type="text" class="form-control money" id="valor" name="valor" value="0,00" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="adicionar_movimentacao" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="excluirMovimentacaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir esta movimentação?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Esta ação não poderá ser desfeita.
                </div>
            </div>
            <div class="modal-footer">
                <form method="post">
                    <input type="hidden" name="movimentacao_id" id="movimentacao_id_excluir">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="excluir_movimentacao" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para campos monetários
    document.querySelectorAll('.money').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (parseInt(value) / 100).toFixed(2).replace('.', ',');
            e.target.value = value;
        });
    });
    
    // Inicializar campos de valor
    document.querySelectorAll('.money').forEach(function(input) {
        const event = new Event('input', { bubbles: true });
        input.dispatchEvent(event);
    });
    
    // Configurar modal de exclusão
    document.querySelectorAll('.excluir-movimentacao').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('movimentacao_id_excluir').value = this.getAttribute('data-id');
        });
    });
    
    // Configurar modal de adição para selecionar conta atual
    const addModal = document.getElementById('adicionarMovimentacaoModal');
    if (addModal) {
        addModal.addEventListener('show.bs.modal', function() {
            // Garantir que a conta atual esteja selecionada
            const contaSelect = document.getElementById('conta_id');
            if (contaSelect) {
                const urlParams = new URLSearchParams(window.location.search);
                const contaId = urlParams.get('conta_id');
                if (contaId) {
                    // Tentar selecionar a opção com o valor igual ao conta_id
                    const opcao = Array.from(contaSelect.options).find(option => option.value === contaId);
                    if (opcao) {
                        opcao.selected = true;
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>

<?php
// Função auxiliar para obter nome do usuário
function obterNomeUsuario($id, $conn) {
    $stmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['nome'];
    }
    
    return "Usuário #" . $id;
}

// Função auxiliar para registrar log
function registrarLog($mensagem, $conn, $is_error = false) {
    $tipo = $is_error ? 'erro' : 'info';
    $usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0;
    
    $sql = "INSERT INTO log_sistema (tipo, mensagem, usuario_id, data_registro) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $tipo, $mensagem, $usuario_id);
    $stmt->execute();
} 