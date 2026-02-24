<?php
// Iniciar buffer de saída para evitar erros de "headers already sent"
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/funcoes.php';

// Verificar permissões administrativas
apenasAdmin();

// Inicializar variáveis
$mensagem = '';
$tipo_alerta = '';
$emprestimo = null;
$parcelas = [];

// Verificar se um ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$emprestimo_id = intval($_GET['id']);

// Processar o formulário de edição
if (isset($_POST['salvar'])) {
    // Obter dados do formulário
    $cliente_id = intval($_POST['cliente_id']);
    $investidor_id = intval($_POST['investidor_id']);
    $valor_emprestado = floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_emprestado']));
    $juros_percentual = floatval(str_replace(['.', ','], ['', '.'], $_POST['juros_percentual']));
    $parcelas = intval($_POST['parcelas']);
    $data_inicio = $_POST['data_inicio'];
    $status = $_POST['status'];
    $observacao = trim($_POST['observacao']);
    
    // Validar dados
    if (empty($cliente_id) || empty($investidor_id) || $valor_emprestado <= 0 || $parcelas <= 0) {
        $mensagem = "Por favor, preencha todos os campos obrigatórios corretamente.";
        $tipo_alerta = "danger";
    } else {
        $conn->begin_transaction();
        
        try {
            // Calcular o valor da parcela (principal + juros)
            $valor_parcela = calcularValorParcela($valor_emprestado, $parcelas, $juros_percentual);
            
            // Atualizar o empréstimo
            $sql = "UPDATE emprestimos SET 
                    cliente_id = ?, 
                    investidor_id = ?, 
                    valor_emprestado = ?, 
                    parcelas = ?, 
                    valor_parcela = ?, 
                    juros_percentual = ?,
                    data_inicio = ?, 
                    status = ?, 
                    observacao = ?, 
                    atualizado_em = NOW() 
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iididdsssi", 
                $cliente_id, 
                $investidor_id, 
                $valor_emprestado, 
                $parcelas, 
                $valor_parcela, 
                $juros_percentual,
                $data_inicio, 
                $status, 
                $observacao, 
                $emprestimo_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar empréstimo: " . $conn->error);
            }
            
            // Se houve mudança no número de parcelas, precisamos ajustar as parcelas
            if (isset($_POST['ajustar_parcelas']) && $_POST['ajustar_parcelas'] == 1) {
                // Primeiro, vamos verificar as parcelas existentes
                $sql_parcelas = "SELECT id, numero, status FROM parcelas WHERE emprestimo_id = ? ORDER BY numero";
                $stmt_parcelas = $conn->prepare($sql_parcelas);
                $stmt_parcelas->bind_param("i", $emprestimo_id);
                $stmt_parcelas->execute();
                $result_parcelas = $stmt_parcelas->get_result();
                $parcelas_existentes = [];
                
                while ($row = $result_parcelas->fetch_assoc()) {
                    $parcelas_existentes[$row['numero']] = $row;
                }
                
                // Manter parcelas pagas/parciais intactas, atualizar pendentes, adicionar/remover conforme necessário
                for ($i = 1; $i <= $parcelas; $i++) {
                    $data_vencimento = calcularDataVencimento($data_inicio, $i);
                    
                    if (isset($parcelas_existentes[$i])) {
                        // Se a parcela existe e não está paga, atualizar valor e data
                        if ($parcelas_existentes[$i]['status'] == 'pendente') {
                            $sql_update = "UPDATE parcelas SET 
                                          valor = ?, 
                                          data_vencimento = ? 
                                          WHERE id = ?";
                            $stmt_update = $conn->prepare($sql_update);
                            $stmt_update->bind_param("dsi", $valor_parcela, $data_vencimento, $parcelas_existentes[$i]['id']);
                            
                            if (!$stmt_update->execute()) {
                                throw new Exception("Erro ao atualizar parcela: " . $conn->error);
                            }
                        }
                        // Se estiver paga ou parcial, deixamos como está para não perder o histórico
                    } else {
                        // Se a parcela não existe, criá-la
                        $sql_insert = "INSERT INTO parcelas (emprestimo_id, numero, valor, data_vencimento, status) 
                                      VALUES (?, ?, ?, ?, 'pendente')";
                        $stmt_insert = $conn->prepare($sql_insert);
                        $stmt_insert->bind_param("iids", $emprestimo_id, $i, $valor_parcela, $data_vencimento);
                        
                        if (!$stmt_insert->execute()) {
                            throw new Exception("Erro ao criar nova parcela: " . $conn->error);
                        }
                    }
                }
                
                // Se o número de parcelas diminuiu, remover parcelas excedentes (apenas pendentes)
                if (count($parcelas_existentes) > $parcelas) {
                    for ($i = $parcelas + 1; $i <= count($parcelas_existentes); $i++) {
                        if (isset($parcelas_existentes[$i]) && $parcelas_existentes[$i]['status'] == 'pendente') {
                            $sql_delete = "DELETE FROM parcelas WHERE id = ?";
                            $stmt_delete = $conn->prepare($sql_delete);
                            $stmt_delete->bind_param("i", $parcelas_existentes[$i]['id']);
                            
                            if (!$stmt_delete->execute()) {
                                throw new Exception("Erro ao remover parcela excedente: " . $conn->error);
                            }
                        }
                    }
                }
            }
            
            $conn->commit();
            $mensagem = "Empréstimo atualizado com sucesso!";
            $tipo_alerta = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensagem = "Erro: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// Buscar dados do empréstimo
$stmt = $conn->prepare("SELECT e.*, 
                      c.nome as cliente_nome, 
                      u.nome as investidor_nome 
                      FROM emprestimos e 
                      INNER JOIN clientes c ON e.cliente_id = c.id 
                      INNER JOIN usuarios u ON e.investidor_id = u.id 
                      WHERE e.id = ?");
$stmt->bind_param("i", $emprestimo_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $emprestimo = $result->fetch_assoc();
} else {
    // Empréstimo não encontrado
    header("Location: index.php?erro=1&msg=" . urlencode("Empréstimo não encontrado."));
    exit;
}

// Buscar parcelas do empréstimo
$stmt = $conn->prepare("SELECT * FROM parcelas WHERE emprestimo_id = ? ORDER BY numero");
$stmt->bind_param("i", $emprestimo_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $parcelas[] = $row;
    }
}

// Contar parcelas pagas/parciais para alertar sobre alterações
$parcelas_pagas = 0;
$parcelas_parciais = 0;

foreach ($parcelas as $parcela) {
    if ($parcela['status'] == 'pago') {
        $parcelas_pagas++;
    } elseif ($parcela['status'] == 'parcial') {
        $parcelas_parciais++;
    }
}

// Buscar todos os clientes
$clientes = [];
$result = $conn->query("SELECT id, nome FROM clientes WHERE status = 'ativo' ORDER BY nome");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
}

// Buscar todos os investidores
$investidores = [];
$result = $conn->query("SELECT id, nome FROM usuarios WHERE nivel_autoridade = 'investidor' OR nivel_autoridade = 'administrador' ORDER BY nome");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Adicionar indicador de admin para facilitar identificação
        if ($row['id'] == 1) {
            $row['nome'] .= " (Admin)";
        }
        $investidores[] = $row;
    }
}

require_once __DIR__ . '/../includes/head.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Editar Empréstimo</h2>
            <p class="text-muted mb-0">
                #<?= $emprestimo_id ?> - Cliente: <?= htmlspecialchars($emprestimo['cliente_nome']) ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
            <a href="visualizar.php?id=<?= $emprestimo_id ?>" class="btn btn-info text-white">
                <i class="bi bi-eye"></i> Visualizar
            </a>
        </div>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($parcelas_pagas > 0 || $parcelas_parciais > 0): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Atenção:</strong> Este empréstimo já possui 
            <?= $parcelas_pagas ?> parcela(s) paga(s) e 
            <?= $parcelas_parciais ?> parcela(s) com pagamento parcial. 
            Alterações em valores, juros e datas podem afetar o controle financeiro.
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Formulário de Edição -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Dados do Empréstimo</h5>
                </div>
                
                <div class="card-body">
                    <form method="post" id="formEmprestimo">
                        <div class="row g-3">
                            <!-- Cliente -->
                            <div class="col-md-6">
                                <label for="cliente_id" class="form-label">Cliente</label>
                                <select class="form-select" id="cliente_id" name="cliente_id" required>
                                    <option value="">Selecione o cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>" <?= $cliente['id'] == $emprestimo['cliente_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cliente['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Investidor -->
                            <div class="col-md-6">
                                <label for="investidor_id" class="form-label">Investidor</label>
                                <select class="form-select" id="investidor_id" name="investidor_id" required>
                                    <option value="">Selecione o investidor</option>
                                    <?php foreach ($investidores as $investidor): ?>
                                        <option value="<?= $investidor['id'] ?>" <?= $investidor['id'] == $emprestimo['investidor_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($investidor['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Valor Emprestado -->
                            <div class="col-md-4">
                                <label for="valor_emprestado" class="form-label">Valor Emprestado (R$)</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" class="form-control money" id="valor_emprestado" name="valor_emprestado" 
                                          value="<?= number_format($emprestimo['valor_emprestado'], 2, ',', '.') ?>" required>
                                </div>
                            </div>
                            
                            <!-- Juros -->
                            <div class="col-md-4">
                                <label for="juros_percentual" class="form-label">Juros (%)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control percentage" id="juros_percentual" name="juros_percentual" 
                                          value="<?= number_format($emprestimo['juros_percentual'], 2, ',', '.') ?>" required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">Taxa de juros total do empréstimo</div>
                            </div>
                            
                            <!-- Parcelas -->
                            <div class="col-md-4">
                                <label for="parcelas" class="form-label">Número de Parcelas</label>
                                <input type="number" class="form-control" id="parcelas" name="parcelas" 
                                       min="1" max="72" value="<?= $emprestimo['parcelas'] ?>" required>
                                <?php if ($parcelas_pagas > 0 || $parcelas_parciais > 0): ?>
                                <div class="form-text text-danger">
                                    <i class="bi bi-exclamation-triangle"></i> Alterar o número de parcelas pode afetar pagamentos já realizados
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Data de Início -->
                            <div class="col-md-4">
                                <label for="data_inicio" class="form-label">Data de Início</label>
                                <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                                       value="<?= $emprestimo['data_inicio'] ?>" required>
                            </div>
                            
                            <!-- Status -->
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="ativo" <?= $emprestimo['status'] == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="quitado" <?= $emprestimo['status'] == 'quitado' ? 'selected' : '' ?>>Quitado</option>
                                    <option value="cancelado" <?= $emprestimo['status'] == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                    <option value="inativo" <?= $emprestimo['status'] == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </div>
                            
                            <!-- Opção de ajustar parcelas -->
                            <div class="col-md-4">
                                <label class="form-label">Parcelas</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ajustar_parcelas" name="ajustar_parcelas" value="1" 
                                        <?= ($parcelas_pagas > 0 || $parcelas_parciais > 0) ? '' : 'checked' ?>>
                                    <label class="form-check-label" for="ajustar_parcelas">
                                        Atualizar parcelas pendentes
                                    </label>
                                </div>
                                <?php if ($parcelas_pagas > 0 || $parcelas_parciais > 0): ?>
                                <div class="form-text">
                                    Parcelas já pagas não serão alteradas
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Observação -->
                            <div class="col-md-12">
                                <label for="observacao" class="form-label">Observação</label>
                                <textarea class="form-control" id="observacao" name="observacao" rows="3"><?= htmlspecialchars($emprestimo['observacao'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Simulação do Valor da Parcela -->
                            <div class="col-md-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p class="mb-0"><strong>Valor da Parcela:</strong></p>
                                                <h4 class="text-primary" id="valor_parcela_simulado">
                                                    R$ <?= number_format($emprestimo['valor_parcela'], 2, ',', '.') ?>
                                                </h4>
                                            </div>
                                            <div>
                                                <p class="mb-0"><strong>Valor Total:</strong></p>
                                                <h4 class="text-success" id="valor_total_simulado">
                                                    R$ <?= number_format($emprestimo['valor_parcela'] * $emprestimo['parcelas'], 2, ',', '.') ?>
                                                </h4>
                                            </div>
                                            <div>
                                                <p class="mb-0"><strong>Lucro Estimado:</strong></p>
                                                <h4 class="text-info" id="lucro_estimado">
                                                    R$ <?= number_format(($emprestimo['valor_parcela'] * $emprestimo['parcelas']) - $emprestimo['valor_emprestado'], 2, ',', '.') ?>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Botões de Ação -->
                            <div class="col-12 mt-4">
                                <button type="submit" name="salvar" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salvar Alterações
                                </button>
                                <a href="visualizar.php?id=<?= $emprestimo_id ?>" class="btn btn-outline-secondary">
                                    Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Informações das Parcelas -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Parcelas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($parcelas)): ?>
                            <div class="text-center p-3">
                                <p class="mb-0 text-muted">Nenhuma parcela cadastrada</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($parcelas as $parcela): 
                                $status_class = '';
                                $status_icon = '';
                                
                                switch ($parcela['status']) {
                                    case 'pago':
                                        $status_class = 'bg-success text-white';
                                        $status_icon = 'check-circle-fill';
                                        break;
                                    case 'parcial':
                                        $status_class = 'bg-warning';
                                        $status_icon = 'exclamation-circle-fill';
                                        break;
                                    case 'atrasado':
                                        $status_class = 'bg-danger text-white';
                                        $status_icon = 'x-circle-fill';
                                        break;
                                    default:
                                        $status_class = '';
                                        $status_icon = 'calendar';
                                }
                                
                                $hoje = new DateTime();
                                $vencimento = new DateTime($parcela['data_vencimento']);
                                $atrasada = ($parcela['status'] == 'pendente' && $vencimento < $hoje);
                                
                                if ($atrasada) {
                                    $status_class = 'bg-danger text-white';
                                    $status_icon = 'exclamation-triangle-fill';
                                }
                            ?>
                                <div class="list-group-item list-group-item-action <?= $status_class ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="bi bi-<?= $status_icon ?> me-2"></i>
                                                Parcela <?= $parcela['numero'] ?> / <?= $emprestimo['parcelas'] ?>
                                            </h6>
                                            <p class="mb-0 small">
                                                Vencimento: <?= date('d/m/Y', strtotime($parcela['data_vencimento'])) ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <h6 class="mb-1">R$ <?= number_format($parcela['valor'], 2, ',', '.') ?></h6>
                                            <span class="badge rounded-pill 
                                                <?= $parcela['status'] == 'pago' ? 'bg-success' : 
                                                   ($parcela['status'] == 'parcial' ? 'bg-warning' : 
                                                   ($atrasada ? 'bg-danger' : 'bg-secondary')) ?>">
                                                <?= ucfirst($parcela['status']) ?>
                                                <?php if ($parcela['status'] == 'parcial'): ?>
                                                    (R$ <?= number_format($parcela['valor_pago'], 2, ',', '.') ?>)
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <span><strong>Total:</strong> <?= count($parcelas) ?> parcelas</span>
                        <span><strong>Pagas:</strong> <?= $parcelas_pagas ?></span>
                        <span><strong>Parciais:</strong> <?= $parcelas_parciais ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Formatação de campos monetários
    document.querySelectorAll('.money').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value === '') {
                value = '0';
            }
            value = parseInt(value) / 100;
            e.target.value = value.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            calcularParcela();
        });
    });
    
    // Formatação de percentuais
    document.querySelectorAll('.percentage').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d,]/g, '');
            value = value.replace(',', '.');
            let numValue = parseFloat(value);
            if (isNaN(numValue)) {
                numValue = 0;
            }
            e.target.value = numValue.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            calcularParcela();
        });
    });
    
    // Recalcular valor das parcelas ao alterar os campos relacionados
    document.getElementById('valor_emprestado').addEventListener('input', calcularParcela);
    document.getElementById('juros_percentual').addEventListener('input', calcularParcela);
    document.getElementById('parcelas').addEventListener('input', calcularParcela);
    
    function calcularParcela() {
        const valorEmprestado = parseFloat(document.getElementById('valor_emprestado').value.replace(/\./g, '').replace(',', '.')) || 0;
        const jurosPercentual = parseFloat(document.getElementById('juros_percentual').value.replace(/\./g, '').replace(',', '.')) || 0;
        const numParcelas = parseInt(document.getElementById('parcelas').value) || 1;
        
        // Cálculo do valor da parcela (juros simples)
        const jurosDecimal = jurosPercentual / 100;
        const valorComJuros = valorEmprestado * (1 + jurosDecimal);
        const valorParcela = valorComJuros / numParcelas;
        const valorTotal = valorParcela * numParcelas;
        const lucro = valorTotal - valorEmprestado;
        
        // Atualizar os elementos na tela
        document.getElementById('valor_parcela_simulado').textContent = 'R$ ' + valorParcela.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        document.getElementById('valor_total_simulado').textContent = 'R$ ' + valorTotal.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        document.getElementById('lucro_estimado').textContent = 'R$ ' + lucro.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Validações e confirmações antes do envio do formulário
    document.getElementById('formEmprestimo').addEventListener('submit', function(e) {
        if (<?= $parcelas_pagas > 0 || $parcelas_parciais > 0 ? 'true' : 'false' ?>) {
            const valorEmprestadoOriginal = <?= $emprestimo['valor_emprestado'] ?>;
            const parcelasOriginal = <?= $emprestimo['parcelas'] ?>;
            const jurosOriginal = <?= $emprestimo['juros_percentual'] ?>;
            const valorAtual = parseFloat(document.getElementById('valor_emprestado').value.replace(/\./g, '').replace(',', '.')) || 0;
            const parcelasAtual = parseInt(document.getElementById('parcelas').value) || 0;
            const jurosAtual = parseFloat(document.getElementById('juros_percentual').value.replace(/\./g, '').replace(',', '.')) || 0;
            
            if (valorAtual !== valorEmprestadoOriginal || parcelasAtual !== parcelasOriginal || jurosAtual !== jurosOriginal) {
                if (!document.getElementById('ajustar_parcelas').checked) {
                    if (!confirm('Você alterou dados financeiros mas não selecionou a opção para atualizar as parcelas. Deseja continuar assim mesmo?')) {
                        e.preventDefault();
                    }
                } else {
                    if (!confirm('Você está alterando dados financeiros e escolheu atualizar as parcelas. As parcelas pendentes serão recalculadas. Continuar?')) {
                        e.preventDefault();
                    }
                }
            }
        }
    });
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?> 