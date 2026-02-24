<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Verificar se o usuário é administrador
apenasAdmin();

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/queries.php';

// Aceita ID tanto por GET quanto por POST
$emprestimo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$emprestimo_id) {
    $emprestimo_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

// Recebe o número da parcela, se existir
$parcela_numero = filter_input(INPUT_GET, 'parcela', FILTER_VALIDATE_INT);

if (!$emprestimo_id) {
    echo '<div class="container py-4"><div class="alert alert-danger">ID do empréstimo não recebido.</div></div>';
    exit;
}

// Buscar informações básicas do empréstimo
$stmt = $conn->prepare("SELECT e.*, c.nome AS cliente_nome, c.cpf_cnpj as cpf, c.telefone, 
                         u.nome AS investidor_nome 
                         FROM emprestimos e 
                         JOIN clientes c ON e.cliente_id = c.id 
                         LEFT JOIN usuarios u ON e.investidor_id = u.id 
                         WHERE e.id = ?");
$stmt->bind_param("i", $emprestimo_id);
$stmt->execute();
$emprestimo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emprestimo) {
    echo '<div class="container py-4"><div class="alert alert-danger">Empréstimo não encontrado.</div></div>';
    exit;
}

// Substitui a leitura do JSON por consulta à tabela de parcelas
$parcelas = [];
$stmt_parcelas = $conn->prepare("
    SELECT 
        id, 
        numero, 
        valor, 
        vencimento, 
        status, 
        valor_pago, 
        data_pagamento, 
        forma_pagamento, 
        observacao 
    FROM 
        parcelas 
    WHERE 
        emprestimo_id = ? 
    ORDER BY 
        numero
");
$stmt_parcelas->bind_param("i", $emprestimo_id);
$stmt_parcelas->execute();
$result_parcelas = $stmt_parcelas->get_result();

while ($p = $result_parcelas->fetch_assoc()) {
    $parcelas[] = $p;
}

// Configurações básicas
$configuracao = json_decode($emprestimo['configuracao'], true);
$hoje = new DateTime();

// Verifica e atualiza o status de cada parcela, se necessário
$parcelas_atualizadas = false;

foreach ($parcelas as &$parcela) {
    $data_vencimento = new DateTime($parcela['vencimento']);
    $hoje = new DateTime();
    
    // Zeramos as horas para comparar apenas as datas
    $data_vencimento->setTime(0, 0, 0);
    $hoje->setTime(0, 0, 0);
    
    // Verifica parcelas vencidas - considera atrasada se a data de vencimento já passou
    if ($parcela['status'] === 'pendente' && $data_vencimento < $hoje) {
        $parcela['status'] = 'atrasado';
        $parcelas_atualizadas = true;
    }
    
    // Verifica parcelas pagas parcialmente
    if (isset($parcela['valor_pago']) && $parcela['valor_pago'] > 0) {
        if (isset($parcela['valor']) && $parcela['valor_pago'] < $parcela['valor']) {
            if ($parcela['status'] !== 'parcial') {
                $parcela['status'] = 'parcial';
                $parcelas_atualizadas = true;
            }
        } elseif (isset($parcela['valor']) && $parcela['valor_pago'] >= $parcela['valor'] && $parcela['status'] !== 'pago') {
            $parcela['status'] = 'pago';
            $parcelas_atualizadas = true;
        }
    }
}
unset($parcela);

// Se houve alterações, atualiza o status das parcelas no banco
if ($parcelas_atualizadas) {
    foreach ($parcelas as $parcela) {
        $stmt_atualiza = $conn->prepare("
            UPDATE parcelas 
            SET status = ? 
            WHERE id = ?
        ");
        $stmt_atualiza->bind_param("si", $parcela['status'], $parcela['id']);
        $stmt_atualiza->execute();
    }
}

// Calcula os totais a partir das parcelas
$pagas = 0;
$parciais = 0;
$pendentes = 0;
$vencidas = 0;
$total_pago = 0;

foreach ($parcelas as $p) {
    if ($p['status'] === 'pago') {
        $pagas++;
        $total_pago += isset($p['valor']) ? floatval($p['valor']) : 0;
    } elseif ($p['status'] === 'parcial') {
        $parciais++;
        $total_pago += $p['valor_pago'] ?? 0;
    } else {
        $data_vencimento = new DateTime($p['vencimento']);
        $hoje = new DateTime();
        
        // Zeramos as horas para comparar apenas as datas
        $data_vencimento->setTime(0, 0, 0);
        $hoje->setTime(0, 0, 0);
        
        if ($data_vencimento < $hoje) {
            $vencidas++;
        } else {
            $pendentes++;
        }
    }
}

$total_previsto = $emprestimo['valor_parcela'] * $emprestimo['parcelas'];

// Verifica se o empréstimo está quitado
$emprestimo_quitado = true;
foreach ($parcelas as $p) {
    if ($p['status'] !== 'pago') {
        $emprestimo_quitado = false;
        break;
    }
}

?>

<div class="container py-4">
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($emprestimo_quitado): ?>
    <div class="alert alert-success text-center py-3 mb-4">
        <h4 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i>EMPRÉSTIMO QUITADO</h4>
        <p class="mb-0 mt-2">Todas as parcelas deste empréstimo foram pagas integralmente.</p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($emprestimo['status']) && $emprestimo['status'] === 'inativo'): ?>
    <div class="alert alert-warning text-center py-3 mb-4">
        <h4 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>EMPRÉSTIMO INATIVO</h4>
        <p class="mb-0 mt-2">Este empréstimo foi marcado como inativo e não aparece mais na listagem principal.</p>
    </div>
    <?php endif; ?>
    
    <div class="card mb-4 header-card">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center">
                    <div class="header-icon me-3">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 text-uppercase small">Empréstimo</h6>
                        <h2 class="mb-0">Detalhes do Empréstimo #<?= $emprestimo['id'] ?></h2>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="index.php" class="btn btn-outline-secondary px-3">
                        <i class="bi bi-arrow-left me-2"></i>Voltar
                    </a>
                    <button type="button" class="btn btn-danger px-3" onclick="abrirModalQuitacao()" <?= $emprestimo_quitado ? 'disabled' : '' ?>>
                        <i class="bi bi-check2-circle me-2"></i>Quitar Empréstimo
                    </button>
                    <a href="parcelas/recibo_quitacao.php?emprestimo_id=<?= $emprestimo['id'] ?>" 
                       class="btn btn-primary px-3" target="_blank">
                        <i class="bi bi-file-earmark-text me-2"></i>Recibo de Quitação
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Card Cliente -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header cliente-header py-2 text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Cliente</h5>
                    </div>
                </div>
                <div class="card-body py-2 d-flex flex-column justify-content-center">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-person-circle me-2" style="font-size: 2rem; color: #2c7744;"></i>
                        <div>
                            <h6 class="mb-0 fs-6"><?= htmlspecialchars($emprestimo['cliente_nome']) ?></h6>
                            <?php if (!empty($emprestimo['cpf'])): ?>
                                <small class="text-muted"><?= formatarCPF($emprestimo['cpf']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($emprestimo['telefone'])): ?>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2"><i class="bi bi-telephone"></i></div>
                            <a href="tel:<?= preg_replace('/\D/', '', $emprestimo['telefone']) ?>" class="text-decoration-none">
                                <?= formatarTelefone($emprestimo['telefone']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex mt-1">
                        <a href="../clientes/visualizar.php" class="btn btn-sm btn-outline-secondary w-100"
                           onclick="event.preventDefault(); document.getElementById('visualizar-cliente').submit();">
                            Ver Detalhes do Cliente
                        </a>
                    </div>
                    
                    <form id="visualizar-cliente" action="../clientes/visualizar.php" method="post" style="display: none;">
                        <input type="hidden" name="id" value="<?= $emprestimo['cliente_id'] ?>">
                    </form>
                </div>
            </div>
        </div>

        <!-- Card Valores e Investidor -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header valores-header py-2 text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Valores e Investidor</h5>
                    </div>
                </div>
                <div class="card-body py-2 d-flex flex-column justify-content-center">
                    <div class="mb-2">
                        <label class="text-muted small">Capital:</label>
                        <h6 class="mb-0 fs-6">R$ <?= number_format($emprestimo['valor_emprestado'], 2, ',', '.') ?></h6>
                    </div>
                    <?php if ($emprestimo['juros_percentual'] > 0): ?>
                    <div class="mb-2">
                        <label class="text-muted small">Juros:</label>
                        <h6 class="mb-0 fs-6"><?= number_format($emprestimo['juros_percentual'], 2, ',', '') ?>%</h6>
                    </div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="text-muted small">Total:</label>
                        <h6 class="mb-0 fs-6">R$ <?= number_format($total_previsto, 2, ',', '.') ?></h6>
                    </div>
                    <div class="border-top pt-2 mt-2">
                        <label class="text-muted small">Investidor:</label>
                        <?php if (!empty($emprestimo['investidor_nome'])): ?>
                            <h6 class="mb-0 fs-6"><?= htmlspecialchars($emprestimo['investidor_nome']) ?></h6>
                        <?php else: ?>
                            <h6 class="mb-0 fs-6 text-muted">Não definido</h6>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Status das Parcelas -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header status-header py-2 text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Status das Parcelas</h5>
                    </div>
                </div>
                <div class="card-body py-2 d-flex flex-column justify-content-center">
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="mb-2">
                                <label class="text-muted small d-block">Pagas:</label>
                                <h6 class="mb-0 fs-6"><?= $pagas ?></h6>
                            </div>
                            <div>
                                <label class="text-muted small d-block">Pendentes:</label>
                                <h6 class="mb-0 fs-6"><?= $pendentes ?></h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-2">
                                <label class="text-muted small d-block">Vencidas:</label>
                                <h6 class="mb-0 fs-6"><?= $vencidas ?></h6>
                            </div>
                            <div>
                                <label class="text-muted small d-block">Total:</label>
                                <h6 class="mb-0 fs-6"><?= $emprestimo['parcelas'] ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="border-top pt-2 mt-2">
                        <?php
                        $percentual_pago = ($pagas / $emprestimo['parcelas']) * 100;
                        
                        // Define a cor baseada no percentual
                        if ($percentual_pago < 25) {
                            $cor = '#dc3545'; // Vermelho
                        } elseif ($percentual_pago < 50) {
                            $cor = '#ffc107'; // Amarelo
                        } elseif ($percentual_pago < 75) {
                            $cor = '#28a745'; // Verde
                        } else {
                            $cor = '#198754'; // Verde escuro
                        }
                        ?>
                        <div class="mb-1">
                            <label class="text-muted small">Progresso de Pagamento:</label>
                        </div>
                        <div class="progress" style="height: 15px;">
                            <div class="progress-bar" role="progressbar" 
                                style="width: <?= $percentual_pago ?>%; background-color: <?= $cor ?>;" 
                                aria-valuenow="<?= $percentual_pago ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                                <span class="small"><?= number_format($percentual_pago, 0) ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Configurações -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header resumo-header py-2 text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Configurações</h5>
                    </div>
                </div>
                <div class="card-body py-2 d-flex flex-column justify-content-center">
                    <div class="mb-2">
                        <label class="text-muted small">Tipo de Cobrança:</label>
                        <h6 class="mb-0 fs-6">
                            <span class="badge text-bg-info" style="padding: 0.3em 0.5em;">
                                <?= ucfirst(str_replace('_', ' ', $emprestimo['tipo_de_cobranca'])) ?>
                            </span>
                        </h6>
                    </div>
                    <div class="border-top pt-2">
                        <div class="mb-2">
                            <label class="text-muted small">Período:</label>
                            <h6 class="mb-0 fs-6">
                                <span class="badge text-bg-secondary" style="padding: 0.3em 0.5em;">
                                    <?= ucfirst($configuracao['periodo_pagamento']) ?>
                                </span>
                            </h6>
                        </div>
                        <div>
                            <label class="text-muted small">Modo de Cálculo:</label>
                            <h6 class="mb-0 fs-6">
                                <span class="badge text-bg-secondary" style="padding: 0.3em 0.5em;">
                                    <?= $configuracao['modo_calculo'] === 'parcela' ? 'Por Parcela' : 'Por Taxa' ?>
                                </span>
                            </h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Parcelas -->
    <div class="card mt-4">
        <div class="card-header parcelas-header">
            <h5 class="card-title mb-0">Parcelas</h5>
        </div>
        <div class="card-body p-0">
            <!-- Filtro de Parcelas -->
            <div class="p-3 border-bottom">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select id="filtroStatus" class="form-select form-select-sm">
                            <option value="">Todos os status</option>
                            <option value="pago">Pago</option>
                            <option value="pendente">Pendente</option>
                            <option value="atrasado">Atrasado</option>
                            <option value="parcial">Parcial</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="filtroValor" class="form-select form-select-sm">
                            <option value="">Todos os valores</option>
                            <option value="menor">Menor valor</option>
                            <option value="maior">Maior valor</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="filtroVencimento" class="form-select form-select-sm">
                            <option value="">Todos os vencimentos</option>
                            <option value="proximo">Próximo vencimento</option>
                            <option value="atrasado">Vencimentos atrasados</option>
                            <option value="futuro">Vencimentos futuros</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button id="limparFiltros" class="btn btn-sm btn-outline-secondary w-100">Limpar Filtros</button>
                    </div>
                </div>
            </div>
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Vencimento</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Pagamento</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcelas as $p): ?>
                            <tr data-status="<?= $p['status'] ?>">
                                <td><?= $p['numero'] ?></td>
                                <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
                                <td>R$ <?= isset($p['valor']) ? number_format($p['valor'], 2, ',', '.') : '0,00' ?></td>
                                <td>
                                    <?php
                                        $status_class = match($p['status']) {
                                            'pago' => 'text-bg-success',
                                            'pendente' => 'text-bg-warning',
                                            'atrasado' => 'text-bg-danger',
                                            'parcial' => 'text-bg-info',
                                            default => 'text-bg-secondary'
                                        };
                                    ?>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'pago' || $p['status'] === 'parcial'): ?>
                                        <?php if ($p['status'] === 'parcial' && isset($p['valor']) && isset($p['valor_pago'])): ?>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <div class="text-decoration-line-through text-muted small">
                                                    <strong>Valor original:</strong> R$ <?= number_format($p['valor'], 2, ',', '.') ?>
                                                </div>
                                                <div class="d-flex flex-column align-items-end">
                                                    <div class="text-success fw-semibold">
                                                        <small>Já pago:</small> R$ <?= number_format($p['valor_pago'], 2, ',', '.') ?>
                                                    </div>
                                                    <div class="text-danger fw-semibold">
                                                        <small>Falta:</small> R$ <?= number_format($p['valor'] - $p['valor_pago'], 2, ',', '.') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="card-text"><strong>Pagamento:</strong> R$ <?= number_format($p['valor_pago'] ?? $p['valor'], 2, ',', '.') ?></p>
                                        <?php endif; ?>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <?php
                                                if (isset($p['data_pagamento']) && strtotime($p['data_pagamento']) > 0): 
                                                    echo date('d/m/Y', strtotime($p['data_pagamento']));
                                                    if (isset($p['forma_pagamento']) && !empty($p['forma_pagamento'])) {
                                                        echo ' via ' . ($p['forma_pagamento'] === 'SOBRA DA PARCELA ANTERIOR' ? $p['forma_pagamento'] : ucfirst($p['forma_pagamento']));
                                                    }
                                                endif;
                                                ?>
                                                <?php if (isset($p['observacao']) && !empty($p['observacao'])): ?>
                                                    <a href="javascript:void(0);" 
                                                       class="text-info ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       data-bs-title="<?= htmlspecialchars($p['observacao']) ?>">
                                                        <i class="bi bi-info-circle"></i> Ver observação
                                                    </a>
                                                <?php endif; ?>
                                            </small>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php 
                                    // Encontra o número da próxima parcela pendente
                                    $proxima_parcela = null;
                                    foreach ($parcelas as $parcela) {
                                        if ($parcela['status'] === 'pendente' || $parcela['status'] === 'parcial') {
                                            $proxima_parcela = $parcela['numero'];
                                            break;
                                        }
                                    }

                                    if (($p['status'] === 'pendente' || $p['status'] === 'parcial' || $p['status'] === 'atrasado') && isset($p['valor']) && $p['valor'] > 0): 
                                        $botoes_habilitados = ($p['numero'] === $proxima_parcela || $p['status'] === 'atrasado');
                                    ?>
                                        <button type="button" 
                                                class="btn btn-sm <?= $botoes_habilitados ? 'btn-success' : 'btn-secondary' ?> btn-pagar" 
                                                data-parcela='<?= json_encode($p) ?>'
                                                <?= !$botoes_habilitados ? 'disabled' : '' ?>
                                                title="<?= $botoes_habilitados ? 'Registrar Pagamento' : 'Pagamento disponível apenas para próxima parcela ou atrasadas' ?>">
                                            <i class="bi bi-cash-coin"></i> Pagar
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm <?= $botoes_habilitados ? 'btn-info' : 'btn-secondary' ?>" 
                                                onclick="enviarCobranca(<?= $emprestimo['id'] ?>, <?= $p['numero'] ?>)"
                                                <?= !$botoes_habilitados ? 'disabled' : '' ?>
                                                title="<?= $botoes_habilitados ? 'Enviar Cobrança' : 'Cobrança disponível apenas para próxima parcela ou atrasadas' ?>">
                                            <i class="bi bi-whatsapp"></i> Cobrar
                                        </button>
                                    <?php else: ?>
                                        <div class="btn-group">
                                            <a href="parcelas/recibo.php?emprestimo_id=<?= $emprestimo['id'] ?>&parcela_numero=<?= $p['numero'] ?>" 
                                               class="btn btn-sm btn-secondary" 
                                               target="_blank"
                                               title="Imprimir Recibo">
                                                <i class="bi bi-printer"></i> Recibo
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success" 
                                                    onclick="enviarReciboWhatsapp(<?= $emprestimo['id'] ?>, <?= $p['numero'] ?>)"
                                                    title="Enviar recibo via WhatsApp">
                                                <i class="bi bi-whatsapp"></i> Enviar
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Versão Mobile (Cards) -->
            <div class="d-md-none">
                <?php foreach ($parcelas as $p): 
                    $status_class = match($p['status']) {
                        'pago' => 'success',
                        'pendente' => 'warning',
                        'atrasado' => 'danger',
                        'parcial' => 'info',
                        default => 'secondary'
                    };
                ?>
                    <div class="card-installment d-block d-md-none mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <p class="card-text"><strong>Parcela:</strong> <?= $p['numero'] ?></p>
                                    <p class="card-text">
                                        <strong>Status:</strong>
                                        <?php if ($p['status'] === 'pendente'): ?>
                                            <span class="badge bg-warning text-dark">Pendente</span>
                                        <?php elseif ($p['status'] === 'pago'): ?>
                                            <span class="badge bg-success">Pago</span>
                                        <?php elseif ($p['status'] === 'parcial'): ?>
                                            <span class="badge bg-info">Parcial</span>
                                        <?php elseif ($p['status'] === 'atrasado'): ?>
                                            <span class="badge bg-danger">Atrasado</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <p class="card-text"><strong>Vencimento:</strong> <?= date('d/m/Y', strtotime($p['vencimento'])) ?></p>
                                <p class="card-text"><strong>Valor:</strong> R$ <?= number_format($p['valor'], 2, ',', '.') ?></p>
                                
                                <?php if ($p['status'] === 'pago' || $p['status'] === 'parcial'): ?>
                                    <?php if ($p['status'] === 'parcial' && isset($p['valor']) && isset($p['valor_pago'])): ?>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <div class="text-decoration-line-through text-muted small">
                                                <strong>Valor original:</strong> R$ <?= number_format($p['valor'], 2, ',', '.') ?>
                                            </div>
                                            <div class="d-flex flex-column align-items-end">
                                                <div class="text-success fw-semibold">
                                                    <small>Já pago:</small> R$ <?= number_format($p['valor_pago'], 2, ',', '.') ?>
                                                </div>
                                                <div class="text-danger fw-semibold">
                                                    <small>Falta:</small> R$ <?= number_format($p['valor'] - $p['valor_pago'], 2, ',', '.') ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="card-text"><strong>Pagamento:</strong> R$ <?= number_format($p['valor_pago'] ?? $p['valor'], 2, ',', '.') ?></p>
                                    <?php endif; ?>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <?php
                                            if (isset($p['data_pagamento']) && strtotime($p['data_pagamento']) > 0): 
                                                echo date('d/m/Y', strtotime($p['data_pagamento']));
                                                if (isset($p['forma_pagamento']) && !empty($p['forma_pagamento'])) {
                                                    echo ' via ' . ($p['forma_pagamento'] === 'SOBRA DA PARCELA ANTERIOR' ? $p['forma_pagamento'] : ucfirst($p['forma_pagamento']));
                                                }
                                            endif;
                                            ?>
                                            <?php if (isset($p['observacao']) && !empty($p['observacao'])): ?>
                                                <a href="javascript:void(0);" 
                                                   class="text-info ms-1" 
                                                   data-bs-toggle="tooltip" 
                                                   data-bs-placement="top" 
                                                   data-bs-title="<?= htmlspecialchars($p['observacao']) ?>">
                                                    <i class="bi bi-info-circle"></i> Ver observação
                                                </a>
                                            <?php endif; ?>
                                        </small>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="mt-3 d-flex justify-content-end gap-2">
                                <?php 
                                // Encontra o número da próxima parcela pendente se ainda não foi definido
                                if (!isset($proxima_parcela)) {
                                    $proxima_parcela = null;
                                    foreach ($parcelas as $parcela) {
                                        if ($parcela['status'] === 'pendente' || $parcela['status'] === 'parcial') {
                                            if ($proxima_parcela === null) {
                                                $proxima_parcela = $parcela['numero'];
                                            } else if ($parcela_seguinte === null) {
                                                $parcela_seguinte = $parcela['numero'];
                                                break;
                                            }
                                        }
                                    }
                                }
                                
                                if (($p['status'] === 'pendente' || $p['status'] === 'parcial' || $p['status'] === 'atrasado') && isset($p['valor']) && $p['valor'] > 0): 
                                    $botoes_habilitados = ($p['numero'] === $proxima_parcela || $p['numero'] === $parcela_seguinte || $p['status'] === 'atrasado');
                                ?>
                                    <button type="button" 
                                            class="btn btn-sm <?= $botoes_habilitados ? 'btn-success' : 'btn-secondary' ?> btn-pagar" 
                                            data-parcela='<?= json_encode($p) ?>'
                                            <?= !$botoes_habilitados ? 'disabled' : '' ?>
                                            title="<?= $botoes_habilitados ? 'Registrar Pagamento' : 'Pagamento disponível apenas para próximas parcelas ou atrasadas' ?>">
                                        <i class="bi bi-cash-coin"></i> Pagar
                                    </button>
                                    <button type="button" 
                                            class="btn btn-sm <?= $botoes_habilitados ? 'btn-info' : 'btn-secondary' ?>" 
                                            onclick="enviarCobranca(<?= $emprestimo['id'] ?>, <?= $p['numero'] ?>)"
                                            <?= !$botoes_habilitados ? 'disabled' : '' ?>
                                            title="<?= $botoes_habilitados ? 'Enviar Cobrança' : 'Cobrança disponível apenas para próximas parcelas ou atrasadas' ?>">
                                        <i class="bi bi-whatsapp"></i> Cobrar
                                    </button>
                                <?php else: ?>
                                    <div class="d-flex gap-2">
                                        <a href="parcelas/recibo.php?emprestimo_id=<?= $emprestimo['id'] ?>&parcela_numero=<?= $p['numero'] ?>" 
                                           class="btn btn-sm btn-secondary flex-fill" 
                                           target="_blank"
                                           title="Imprimir Recibo">
                                            <i class="bi bi-printer"></i> Recibo
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-success flex-fill" 
                                                onclick="enviarReciboWhatsapp(<?= $emprestimo['id'] ?>, <?= $p['numero'] ?>)"
                                                title="Enviar recibo via WhatsApp">
                                            <i class="bi bi-whatsapp"></i> Enviar
                                        </button>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Pagamento -->
<div class="modal fade" id="modalPagamento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Parcela <span id="numero_parcela_display"></span>/<?= $emprestimo['parcelas'] ?></h6>
                    <span id="status_parcela_display" class="badge"></span>
                </div>

                <div class="alert alert-info mb-3">
                    <h6 class="mb-2">Cálculo do Valor a Receber:</h6>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Valor Total da Parcela:</span>
                        <span id="valor_total_display"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Valor Já Pago:</span>
                        <span id="valor_pago_display"></span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Valor a Receber:</span>
                        <span id="valor_a_receber_display"></span>
                    </div>
                </div>

                <form id="formPagamento">
                    <input type="hidden" id="emprestimo_id" name="emprestimo_id">
                    <input type="hidden" id="parcela_numero" name="parcela_numero">
                    <input type="hidden" id="valor_parcela" name="valor_parcela">
                    
                    <div class="mb-3">
                        <label for="valor_pago" class="form-label">Valor a Receber</label>
                        <input type="number" step="0.01" class="form-control" id="valor_pago" name="valor_pago" required>
                    </div>

                    <div class="mb-3">
                        <label for="data_pagamento" class="form-label">Data do Pagamento</label>
                        <input type="date" class="form-control" id="data_pagamento" name="data_pagamento" required>
                    </div>

                    <div class="mb-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento" name="forma_pagamento" required>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="cartao">Cartão</option>
                            <option value="transferencia">Transferência</option>
                        </select>
                    </div>

                    <!-- Opções de distribuição do excedente -->
                    <div id="opcoes_distribuicao" class="d-none">
                        <div class="alert alert-info">
                            <h6 class="mb-2">Como deseja distribuir o excedente?</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_distribuicao" id="desconto_proximas" value="desconto_proximas" checked>
                                <label class="form-check-label" for="desconto_proximas">
                                    Descontar das próximas parcelas
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_distribuicao" id="desconto_ultimas" value="desconto_ultimas">
                                <label class="form-check-label" for="desconto_ultimas">
                                    Descontar das últimas parcelas
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Opções para pagamento parcial -->
                    <div id="opcoes_menor" class="d-none">
                        <div class="alert alert-info">
                            <h6 class="mb-2">Pagamento Parcial</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_distribuicao" id="proxima_parcela" value="proxima_parcela" checked>
                                <label class="form-check-label" for="proxima_parcela">
                                    Adicionar diferença na próxima parcela
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="registrarPagamento()">Registrar Pagamento</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Sucesso -->
<div class="modal fade" id="modalSucesso" tabindex="-1" aria-labelledby="modalSucessoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                </div>
                <h4 class="mb-3" id="mensagem-sucesso">Pagamento Registrado!</h4>
                <p class="mb-0 text-muted" id="submensagem-sucesso">O pagamento foi registrado com sucesso.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4">
                <button type="button" class="btn btn-success px-4" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Atualizar Página
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Quitação -->
<div class="modal fade" id="modalQuitacao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quitar Empréstimo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-4">
                    <h6 class="alert-heading mb-2">Resumo da Quitação</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total do Empréstimo:</span>
                        <strong id="total_emprestimo">R$ <?= number_format($total_previsto, 2, ',', '.') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Já Pago:</span>
                        <strong id="total_pago">R$ <?= number_format($total_pago, 2, ',', '.') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Valor para Quitação:</span>
                        <strong id="valor_quitacao" class="text-success">R$ <?= number_format($total_previsto - $total_pago, 2, ',', '.') ?></strong>
                    </div>
                </div>

                <form id="formQuitacao">
                    <div class="mb-3">
                        <label for="valor_quitacao_input" class="form-label">Valor a Pagar</label>
                        <input type="number" step="0.01" class="form-control" id="valor_quitacao_input" name="valor_quitacao_input" value="<?= number_format($total_previsto - $total_pago, 2, '.', '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="data_quitacao" class="form-label">Data do Pagamento</label>
                        <input type="date" class="form-control" id="data_quitacao" name="data_quitacao" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="forma_pagamento_quitacao" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento_quitacao" name="forma_pagamento_quitacao" required>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="cartao">Cartão</option>
                            <option value="transferencia">Transferência</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="quitarEmprestimo()">
                    <i class="bi bi-check2-circle me-2"></i>Confirmar Quitação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inclui o script do Canvas Confetti antes de qualquer uso -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<script>
// Definindo a variável parcelas em escopo global
var parcelas = [];

<?php 
// Preencher a variável parcelas para uso global
echo 'parcelas = ' . json_encode($parcelas) . ';';
?>

// Aguarda o DOM estar completamente carregado
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa os modais
    const modalPagamento = document.getElementById('modalPagamento');
    const modalQuitacao = document.getElementById('modalQuitacao');
    
    if (modalPagamento) {
        new bootstrap.Modal(modalPagamento);
    }
    
    if (modalQuitacao) {
        new bootstrap.Modal(modalQuitacao);
    }

    // Adiciona event listeners para os botões de pagamento
    const botoesPagamento = document.querySelectorAll('.btn-pagar');
    botoesPagamento.forEach(botao => {
        botao.addEventListener('click', function() {
            const parcelaData = JSON.parse(this.getAttribute('data-parcela'));
            abrirModalPagamento(
                <?php echo $emprestimo_id; ?>, 
                parcelaData.numero, 
                parcelaData.valor, 
                parcelaData.valor_pago || 0, 
                parcelaData.status
            );
        });
    });

    // Função para abrir o modal de pagamento
    window.abrirModalPagamento = function(emprestimo_id, parcela_numero, valor, valor_pago, status) {
        const emprestimo_id_input = document.getElementById('emprestimo_id');
        const parcela_numero_input = document.getElementById('parcela_numero');
        const valor_parcela_input = document.getElementById('valor_parcela');
        
        if (emprestimo_id_input) emprestimo_id_input.value = emprestimo_id;
        if (parcela_numero_input) parcela_numero_input.value = parcela_numero;
        if (valor_parcela_input) valor_parcela_input.value = valor;
        
        // Exibe o número da parcela e status
        const numero_display = document.getElementById('numero_parcela_display');
        if (numero_display) numero_display.textContent = parcela_numero;
        
        // Define a classe do badge baseado no status
        const statusBadge = document.getElementById('status_parcela_display');
        if (statusBadge) {
            statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            statusBadge.className = 'badge ' + 
                (status === 'pago' ? 'bg-success' : 
                 status === 'pendente' ? 'bg-warning' : 
                 status === 'atrasado' ? 'bg-danger' : 
                 status === 'parcial' ? 'bg-info' : 'bg-secondary');
        }
        
        // Calcula e exibe os valores formatados
        const valorTotal = parseFloat(valor);
        const valorPagoAtual = parseFloat(valor_pago) || 0;
        const valorAReceber = valorTotal - valorPagoAtual;
        
        const valor_total_display = document.getElementById('valor_total_display');
        const valor_pago_display = document.getElementById('valor_pago_display');
        const valor_a_receber_display = document.getElementById('valor_a_receber_display');
        const valor_pago_input = document.getElementById('valor_pago');
        const data_pagamento_input = document.getElementById('data_pagamento');
        
        if (valor_total_display) valor_total_display.textContent = 'R$ ' + valorTotal.toFixed(2).replace('.', ',');
        if (valor_pago_display) valor_pago_display.textContent = 'R$ ' + valorPagoAtual.toFixed(2).replace('.', ',');
        if (valor_a_receber_display) valor_a_receber_display.textContent = 'R$ ' + valorAReceber.toFixed(2).replace('.', ',');
        
        // Define o valor padrão como o valor a receber
        if (valor_pago_input) valor_pago_input.value = valorAReceber.toFixed(2);
        
        // Define a data padrão como hoje
        if (data_pagamento_input) data_pagamento_input.value = new Date().toISOString().split('T')[0];
        
        // Garante que o radio button "Descontar das próximas parcelas" esteja marcado
        const radioDescontoProximas = document.querySelector('input[name="modo_distribuicao"][value="desconto_proximas"]');
        if (radioDescontoProximas) {
            radioDescontoProximas.checked = true;
        }
        
        // Garante que o radio button "Adicionar diferença na próxima parcela" esteja marcado
        const radioProximaParcela = document.querySelector('input[name="modo_distribuicao"][value="proxima_parcela"]');
        if (radioProximaParcela) {
            radioProximaParcela.checked = true;
        }
        
        // Adiciona evento para verificar excedente
        if (valor_pago_input) {
            valor_pago_input.addEventListener('input', function() {
                const valorInformado = parseFloat(this.value) || 0;
                const excedente = valorInformado - valorAReceber;
                
                // Remove alerta anterior se existir
                const alertaAnterior = document.querySelector('.alert-excedente');
                if (alertaAnterior) {
                    alertaAnterior.remove();
                }
                
                // Esconde as opções de distribuição inicialmente
                const opcoes_distribuicao = document.getElementById('opcoes_distribuicao');
                const opcoes_menor = document.getElementById('opcoes_menor');
                if (opcoes_distribuicao) opcoes_distribuicao.classList.add('d-none');
                if (opcoes_menor) opcoes_menor.classList.add('d-none');
                
                if (excedente > 0) {
                    // Se o valor informado é maior que o valor a receber
                    const alerta = document.createElement('div');
                    alerta.className = 'alert alert-warning mt-3 alert-excedente';
                    alerta.innerHTML = `Valor maior que o valor a receber. Excedente: R$ ${excedente.toFixed(2).replace('.', ',')}`;
                    this.parentElement.appendChild(alerta);
                    
                    // Mostra opções de distribuição do excedente e marca a opção padrão
                    if (opcoes_distribuicao) {
                        opcoes_distribuicao.classList.remove('d-none');
                        opcoes_menor.classList.add('d-none');
                        if (radioDescontoProximas) {
                            radioDescontoProximas.checked = true;
                        }
                    }
                } else if (excedente < 0 && valorInformado > 0) {
                    // Se o valor informado é menor que o valor a receber
                    const alerta = document.createElement('div');
                    alerta.className = 'alert alert-info mt-3 alert-excedente';
                    alerta.innerHTML = `Pagamento parcial. Restará um saldo de R$ ${Math.abs(excedente).toFixed(2).replace('.', ',')} para a próxima parcela.`;
                    this.parentElement.appendChild(alerta);
                    
                    // Mostra opções para pagamento parcial e marca a opção padrão
                    if (opcoes_menor) {
                        opcoes_menor.classList.remove('d-none');
                        opcoes_distribuicao.classList.add('d-none');
                        if (radioProximaParcela) {
                            radioProximaParcela.checked = true;
                        }
                    }
                }
            });
        }
        
        // Mostra o modal
        const modalPagamento = new bootstrap.Modal(document.getElementById('modalPagamento'));
        modalPagamento.show();
    };

    // Função para abrir o modal de quitação
    window.abrirModalQuitacao = function() {
        const modal = new bootstrap.Modal(modalQuitacao);
        modal.show();
    };

    // Monitora mudanças no valor pago
    const valorPagoInput = document.getElementById('valor_pago');
    if (valorPagoInput) {
        valorPagoInput.addEventListener('input', function() {
            const valorOriginal = parseFloat(document.getElementById('valor_parcela')?.value || 0);
            const valorPago = parseFloat(this.value) || 0;
            
            const diferenca = valorPago - valorOriginal;
            const opcoesDistribuicao = document.getElementById('opcoes_distribuicao');
            const opcoesMenor = document.getElementById('opcoes_menor');
            const opcoesMaior = document.getElementById('opcoes_maior');
            const infoDiv = document.getElementById('info_diferenca');
            
            if (!opcoesDistribuicao || !opcoesMenor || !opcoesMaior || !infoDiv) return;
            
            if (diferenca !== 0) {
                opcoesDistribuicao.classList.remove('d-none');
                
                if (diferenca < 0) {
                    infoDiv.textContent = `Valor menor que o original. Diferença: R$ ${Math.abs(diferenca).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                    opcoesMenor.classList.remove('d-none');
                    opcoesMaior.classList.add('d-none');
                    
                    const radioProximaParcela = document.querySelector('input[name="modo_distribuicao"][value="proxima_parcela"]');
                    if (radioProximaParcela) {
                        radioProximaParcela.checked = true;
                    }
                } else {
                    infoDiv.textContent = `Valor maior que o original. Excedente: R$ ${diferenca.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                    opcoesMaior.classList.remove('d-none');
                    opcoesMenor.classList.add('d-none');
                    
                    const radioDescontoProximas = document.querySelector('input[name="modo_distribuicao"][value="desconto_proximas"]');
                    if (radioDescontoProximas) {
                        radioDescontoProximas.checked = true;
                    }
                }
            } else {
                opcoesDistribuicao.classList.add('d-none');
            }
        });
    }

    // Implementação do filtro de parcelas
    const filtroStatus = document.getElementById('filtroStatus');
    const filtroValor = document.getElementById('filtroValor');
    const filtroVencimento = document.getElementById('filtroVencimento');
    const limparFiltros = document.getElementById('limparFiltros');
    const tabela = document.querySelector('.table tbody');
    const linhas = tabela.querySelectorAll('tr');
    const cards = document.querySelectorAll('.parcela-card');

    function aplicarFiltros() {
        const valorStatus = filtroStatus.value;
        const valorFiltroValor = filtroValor.value;
        const valorFiltroVencimento = filtroVencimento.value;
        
        // Filtra as linhas da tabela
        linhas.forEach(linha => {
            const status = linha.getAttribute('data-status');
            const valor = parseFloat(linha.querySelector('td:nth-child(3)').textContent.replace('R$', '').replace('.', '').replace(',', '.'));
            const vencimento = new Date(linha.querySelector('td:nth-child(2)').textContent.split('/').reverse().join('-'));
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            
            let matchStatus = valorStatus === '' || status === valorStatus;
            let matchValor = true;
            let matchVencimento = true;
            
            // Aplica filtro de valor
            if (valorFiltroValor === 'menor') {
                matchValor = valor < parseFloat(<?= $emprestimo['valor_parcela'] ?>);
            } else if (valorFiltroValor === 'maior') {
                matchValor = valor > parseFloat(<?= $emprestimo['valor_parcela'] ?>);
            }
            
            // Aplica filtro de vencimento
            if (valorFiltroVencimento === 'proximo') {
                // Encontra a próxima parcela pendente
                const proximaParcela = <?= $proxima_parcela ?? 'null' ?>;
                matchVencimento = parseInt(linha.querySelector('td:nth-child(1)').textContent) === proximaParcela;
            } else if (valorFiltroVencimento === 'atrasado') {
                // Para verificação de atraso
                const hoje = new Date();
                const ontem = new Date();
                ontem.setDate(hoje.getDate() - 1);
                
                // Se o vencimento for menor que ONTEM e não estiver pago, está atrasado
                matchVencimento = vencimento < ontem && status !== 'pago';
            } else if (valorFiltroVencimento === 'futuro') {
                matchVencimento = vencimento > hoje;
            }
            
            if (matchStatus && matchValor && matchVencimento) {
                linha.style.display = '';
            } else {
                linha.style.display = 'none';
            }
        });
        
        // Filtra os cards na versão mobile
        cards.forEach(card => {
            const status = card.getAttribute('data-status');
            const valor = parseFloat(card.querySelector('.info-value').textContent.replace('R$', '').replace('.', '').replace(',', '.'));
            const vencimentoText = card.querySelector('.info-row:nth-child(2) .info-value').textContent.trim();
            const vencimento = new Date(vencimentoText.split('/').reverse().join('-'));
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            
            let matchStatus = valorStatus === '' || status === valorStatus;
            let matchValor = true;
            let matchVencimento = true;
            
            // Aplica filtro de valor
            if (valorFiltroValor === 'menor') {
                matchValor = valor < parseFloat(<?= $emprestimo['valor_parcela'] ?>);
            } else if (valorFiltroValor === 'maior') {
                matchValor = valor > parseFloat(<?= $emprestimo['valor_parcela'] ?>);
            }
            
            // Aplica filtro de vencimento
            if (valorFiltroVencimento === 'proximo') {
                // Encontra a próxima parcela pendente
                const proximaParcela = <?= $proxima_parcela ?? 'null' ?>;
                const numeroParcela = parseInt(card.querySelector('.parcela-number .number').textContent);
                matchVencimento = numeroParcela === proximaParcela;
            } else if (valorFiltroVencimento === 'atrasado') {
                // Para verificação de atraso
                const hoje = new Date();
                const ontem = new Date();
                ontem.setDate(hoje.getDate() - 1);
                
                // Se o vencimento for menor que ONTEM e não estiver pago, está atrasado
                matchVencimento = vencimento < ontem && status !== 'pago';
            } else if (valorFiltroVencimento === 'futuro') {
                matchVencimento = vencimento > hoje;
            }
            
            if (matchStatus && matchValor && matchVencimento) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    filtroStatus.addEventListener('change', aplicarFiltros);
    filtroValor.addEventListener('change', aplicarFiltros);
    filtroVencimento.addEventListener('change', aplicarFiltros);
    
    limparFiltros.addEventListener('click', function() {
        filtroStatus.value = '';
        filtroValor.value = '';
        filtroVencimento.value = '';
        aplicarFiltros();
    });

    // Inicializa os cards expandidos
    document.querySelectorAll('.card-content').forEach(content => {
        content.style.maxHeight = content.scrollHeight + "px";
    });
    
    // Inicializa os tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
});

// Função para criar a animação de fogos
function celebrarQuitacao() {
    console.log('Iniciando celebração!'); // Debug

    // Explosão inicial
    confetti({
        particleCount: 100,
        spread: 70,
        origin: { y: 0.6 }
    });

    // Canhões dos lados
    const end = Date.now() + (3 * 1000); // 3 segundos

    // Lança confetti dos dois lados
    (function frame() {
        confetti({
            particleCount: 2,
            angle: 60,
            spread: 55,
            origin: { x: 0 }
        });
        confetti({
            particleCount: 2,
            angle: 120,
            spread: 55,
            origin: { x: 1 }
        });

        if (Date.now() < end) {
            requestAnimationFrame(frame);
        }
    }());

    // Explosão final após 2 segundos
    setTimeout(() => {
        confetti({
            particleCount: 150,
            spread: 100,
            origin: { y: 0.6 }
        });
    }, 2000);
}

// Função para verificar se todas as parcelas estão pagas
function verificarEmprestimoQuitado(parcelas) {
    return parcelas.every(p => p.status === 'pago');
}

// Função para mostrar o modal de sucesso com animação
function mostrarSucessoComAnimacao(mensagem, submensagem, mostrarFogos = false) {
    // Atualiza as mensagens
    document.getElementById('mensagem-sucesso').textContent = mensagem;
    document.getElementById('submensagem-sucesso').textContent = submensagem;
    
    // Mostra o modal
    const modalSucesso = new bootstrap.Modal(document.getElementById('modalSucesso'));
    modalSucesso.show();

    // Se for quitação, mostra os fogos
    if (mostrarFogos) {
        console.log('Chamando celebração...'); // Debug
        setTimeout(celebrarQuitacao, 500); // Pequeno delay para garantir que o modal está visível
    }
}

// Função para registrar o pagamento
window.registrarPagamento = function() {
    const parcela_numero = document.getElementById('parcela_numero')?.value;
    const valor_input = document.getElementById('valor_pago')?.value;
    const data_pagamento = document.getElementById('data_pagamento')?.value;
    const forma_pagamento = document.getElementById('forma_pagamento')?.value;
    const modo_distribuicao = document.querySelector('input[name="modo_distribuicao"]:checked')?.value || 'desconto_proximas';

    if (!parcela_numero || !valor_input || !data_pagamento || !forma_pagamento) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }

    const valor_pago = parseFloat(valor_input.replace(',', '.')) || 0;
    
    if (isNaN(valor_pago) || valor_pago <= 0) {
        alert('Por favor, insira um valor válido.');
        return;
    }
    
    const dados = {
        emprestimo_id: <?php echo $emprestimo_id; ?>,
        parcela_numero: parcela_numero,
        valor_pago: valor_pago.toFixed(2),
        data_pagamento: data_pagamento,
        forma_pagamento: forma_pagamento,
        modo_distribuicao: modo_distribuicao
    };
    
    fetch('parcelas/pagar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(dados)
    })
    .then(async response => {
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
            if (data.status === 'success') {
                // Fecha o modal de pagamento
                const modalPagamento = bootstrap.Modal.getInstance(document.getElementById('modalPagamento'));
                if (modalPagamento) modalPagamento.hide();

                // Verifica se o empréstimo foi quitado
                const todasParcelas = document.querySelectorAll('[data-parcela]');
                const parcelas = Array.from(todasParcelas).map(el => JSON.parse(el.dataset.parcela));
                const quitado = verificarEmprestimoQuitado(parcelas);

                // Recolher informações para envio da mensagem
                // Verificamos todas as parcelas que foram atualizadas neste pagamento (atual e possivelmente próximas/últimas)
                const parcela_atual = parcelas.find(p => parseInt(p.numero) === parseInt(parcela_numero));
                const cliente_id = <?php echo $emprestimo['cliente_id'] ?? 0; ?>;
                const parcelas_alteradas = [];
                
                // Adicionamos a parcela atual que acabou de ser paga/alterada
                if (parcela_atual) {
                    parcelas_alteradas.push(parcela_atual);
                }
                
                // Buscamos as parcelas que podem ter sido alteradas pela distribuição automática
                if (modo_distribuicao === 'desconto_proximas' || modo_distribuicao === 'desconto_ultimas') {
                    parcelas.forEach(p => {
                        if (parseInt(p.numero) > parseInt(parcela_numero) && (p.status === 'pago' || p.status === 'parcial')) {
                            // Apenas adiciona se não for a parcela principal
                            if (parseInt(p.numero) !== parseInt(parcela_numero)) {
                                parcelas_alteradas.push(p);
                            }
                        }
                    });
                }
                
                // Determinar o status para buscar o template correto (pago ou parcial)
                const valor_parcela = parseFloat(document.getElementById('valor_parcela')?.value || 0);
                const valor_informado = parseFloat(valor_pago);
                const status_template = valor_informado >= valor_parcela ? 'pago' : 'parcial';
                
                // Buscar os templates de mensagens do status correspondente
                fetch(`<?= BASE_URL ?>emprestimos/templates_mensagens_ajax.php?acao=buscar_por_status&status=${status_template}`)
                    .then(response => {
                        // Se não encontrar template para parcial, tenta buscar para pago como alternativa
                        if (!response.ok && status_template === 'parcial') {
                            return fetch(`<?= BASE_URL ?>emprestimos/templates_mensagens_ajax.php?acao=buscar_por_status&status=pago`);
                        }
                        return response;
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.sucesso && data.templates && data.templates.length > 0) {
                            // Usar o primeiro template encontrado
                            const template_id = data.templates[0].id;
                            
                            // Precisamos obter os IDs das parcelas alteradas para incluí-las na URL
                            const parcela_ids = parcelas_alteradas.map(p => p.id).join(',');
                            
                            // Envio da mensagem via API
                            const url = `<?= BASE_URL ?>mensagens/api/enviar.php?template=${template_id}&status=pago,parcial&coletiva=false&cliente_id=${cliente_id}&cliente_especifico=1&parcela_id=${parcela_ids}`;
                            
                            fetch(url)
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Mensagem enviada:', data);
                                })
                                .catch(error => {
                                    console.error('Erro ao enviar mensagem:', error);
                                });
                        } else {
                            console.warn('Nenhum template encontrado para o status:', status_template);
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar templates:', error);
                    });

                // Verificar se o pagamento veio da página de cobrança e redirecionar se necessário
                const urlParams = new URLSearchParams(window.location.search);
                const origem = urlParams.get('origem');
                
                if (origem === 'cobrancas') {
                    // Obter informações adicionais para passar na URL
                    const cliente_nome = '<?= htmlspecialchars(urlencode($emprestimo['cliente_nome'] ?? '')) ?>';
                    const parcelas_total = '<?= $emprestimo['parcelas'] ?? 0 ?>';
                    const valor_emprestimo = '<?= $emprestimo['valor_emprestado'] ?? 0 ?>';
                    
                    // Redirecionar para a página de cobrança com informações completas
                    window.location.href = '<?= BASE_URL ?>emprestimos/parcelas/cobrancas/index.php?sucesso=1&parcela=' + 
                        parcela_numero + 
                        '&parcela_total=' + parcelas_total + 
                        '&cliente=' + cliente_nome + 
                        '&emprestimo=' + <?= $emprestimo_id ?> + 
                        '&valor_emprestimo=' + valor_emprestimo;
                } else {
                    // Comportamento padrão: mostrar modal de sucesso
                    if (quitado) {
                        mostrarSucessoComAnimacao(
                            'Empréstimo Quitado!',
                            'Parabéns! Todas as parcelas foram pagas.',
                            true // mostra fogos
                        );
                    } else {
                        mostrarSucessoComAnimacao(
                            'Pagamento Registrado!',
                            'O pagamento foi registrado com sucesso.',
                            false // não mostra fogos
                        );
                    }
                }
            } else {
                throw new Error(data.message || 'Erro desconhecido');
            }
        } catch (e) {
            console.error('Erro ao processar resposta:', e);
            console.log('Resposta do servidor:', text);
            alert('Erro ao registrar pagamento: ' + (e.message || 'Resposta inválida do servidor'));
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        alert('Erro ao registrar pagamento: ' + error.message);
    });
};

// Função para quitar o empréstimo
window.quitarEmprestimo = function() {
    const valor_quitacao = document.getElementById('valor_quitacao_input')?.value;
    const data_quitacao = document.getElementById('data_quitacao')?.value;
    const forma_pagamento = document.getElementById('forma_pagamento_quitacao')?.value;

    if (!valor_quitacao || !data_quitacao || !forma_pagamento) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }

    const valor = parseFloat(valor_quitacao.replace(',', '.')) || 0;
    
    if (isNaN(valor) || valor <= 0) {
        alert('Por favor, insira um valor válido.');
        return;
    }

    if (!confirm('Tem certeza que deseja quitar o empréstimo? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    const dados = {
        emprestimo_id: <?php echo $emprestimo_id; ?>,
        valor_quitacao: valor.toFixed(2),
        data_quitacao: data_quitacao,
        forma_pagamento: forma_pagamento
    };
    
    fetch('parcelas/quitar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(dados)
    })
    .then(async response => {
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
            if (data.status === 'success') {
                // Fecha o modal de quitação
                const modalQuitacao = bootstrap.Modal.getInstance(document.getElementById('modalQuitacao'));
                if (modalQuitacao) modalQuitacao.hide();
                
                // Buscar template de quitação e enviar mensagem
                fetch(`<?= BASE_URL ?>emprestimos/templates_mensagens_ajax.php?acao=buscar_por_status&status=quitado`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.sucesso && data.templates && data.templates.length > 0) {
                            // Usar o primeiro template encontrado
                            const template_id = data.templates[0].id;
                            const cliente_id = <?php echo $emprestimo['cliente_id'] ?? 0; ?>;
                            
                            // Envio da mensagem via API
                            const url = `<?= BASE_URL ?>mensagens/api/enviar.php?template=${template_id}&status=quitado&coletiva=false&cliente_id=${cliente_id}&cliente_especifico=1`;
                            
                            fetch(url)
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Mensagem de quitação enviada:', data);
                                })
                                .catch(error => {
                                    console.error('Erro ao enviar mensagem de quitação:', error);
                                });
                        } else {
                            console.warn('Nenhum template encontrado para quitação');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar templates de quitação:', error);
                    });
                
                // Mostra o sucesso com animação
                mostrarSucessoComAnimacao(
                    'Empréstimo Quitado!',
                    'Parabéns! O empréstimo foi quitado com sucesso.',
                    true // mostra fogos
                );
            } else {
                throw new Error(data.message || 'Erro desconhecido');
            }
        } catch (e) {
            console.error('Erro ao processar resposta:', e);
            console.log('Resposta do servidor:', text);
            alert('Erro ao quitar empréstimo: ' + (e.message || 'Resposta inválida do servidor'));
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        alert('Erro ao quitar empréstimo: ' + error.message);
    });
};

// Função para abrir modal de template para parcelas pendentes ou atrasadas
function enviarCobranca(emprestimo_id, parcela_numero) {
    console.log('enviarCobranca chamado com:', { emprestimo_id, parcela_numero });
    
    // Definir os valores nos campos ocultos
    document.getElementById('template_emprestimo_id').value = emprestimo_id;
    document.getElementById('template_parcela_numero').value = parcela_numero;
    
    // Verificar se parcelas está definido
    if (typeof parcelas === 'undefined' || !Array.isArray(parcelas)) {
        console.error('Array parcelas não está definido ou não é um array');
        // Mesmo com erro, mostrar o modal
        const modalTemplate = new bootstrap.Modal(document.getElementById('modalTemplate'));
        modalTemplate.show();
        return;
    }
    
    // Buscar os dados da parcela para pré-selecionar o template apropriado
    let parcela = null;
    
    // Encontrar a parcela pelo número
    for (const p of parcelas) {
        if (parseInt(p.numero) === parseInt(parcela_numero)) {
            parcela = p;
            console.log('Parcela encontrada:', p);
            break;
        }
    }
    
    if (parcela) {
        // Determinar o status da parcela para pré-selecionar o template
        let statusParcela = parcela.status;
        const selectTemplate = document.getElementById('template_id');
        
        // Percorrer opções para encontrar a correspondente ao status
        for (let i = 0; i < selectTemplate.options.length; i++) {
            const option = selectTemplate.options[i];
            if (option.dataset.status === statusParcela || 
                (statusParcela === 'atrasado' && option.dataset.status === 'atrasado') || 
                (statusParcela === 'pendente' && option.dataset.status === 'pendente')) {
                selectTemplate.selectedIndex = i;
                break;
            }
        }
    } else {
        console.warn('Parcela não encontrada:', parcela_numero);
    }
    
    // Definir o título do modal
    document.querySelector('#modalTemplate .modal-title').textContent = 'Enviar Cobrança';
    
    // Exibir o modal
    const modalTemplate = new bootstrap.Modal(document.getElementById('modalTemplate'));
    modalTemplate.show();
}

// Função para abrir modal de template para parcelas pagas
function enviarReciboWhatsapp(emprestimo_id, parcela_numero) {
    console.log('enviarReciboWhatsapp chamado com:', { emprestimo_id, parcela_numero });
    
    // Definir os valores nos campos ocultos
    document.getElementById('template_emprestimo_id').value = emprestimo_id;
    document.getElementById('template_parcela_numero').value = parcela_numero;
    
    // Verificar se parcelas está definido
    if (typeof parcelas === 'undefined' || !Array.isArray(parcelas)) {
        console.error('Array parcelas não está definido ou não é um array');
        // Mesmo com erro, mostrar o modal
        const modalTemplate = new bootstrap.Modal(document.getElementById('modalTemplate'));
        modalTemplate.show();
        return;
    }
    
    // Buscar os dados da parcela para pré-selecionar o template apropriado
    let parcela = null;
    
    // Encontrar a parcela pelo número
    for (const p of parcelas) {
        if (parseInt(p.numero) === parseInt(parcela_numero)) {
            parcela = p;
            console.log('Parcela encontrada:', p);
            break;
        }
    }
    
    if (parcela) {
        // Pré-selecionar template com status 'pago' ou 'quitado'
        const selectTemplate = document.getElementById('template_id');
        
        // Percorrer opções para encontrar um template com status 'pago' ou 'quitado'
        for (let i = 0; i < selectTemplate.options.length; i++) {
            const option = selectTemplate.options[i];
            if (option.dataset.status === 'pago' || option.dataset.status === 'quitado') {
                selectTemplate.selectedIndex = i;
                break;
            }
        }
    } else {
        console.warn('Parcela não encontrada:', parcela_numero);
    }
    
    // Definir o título do modal
    document.querySelector('#modalTemplate .modal-title').textContent = 'Enviar Recibo';
    
    // Exibir o modal
    const modalTemplate = new bootstrap.Modal(document.getElementById('modalTemplate'));
    modalTemplate.show();
}

// Função para enviar a mensagem após seleção do template
function enviarMensagem() {
    // Obter dados do formulário
    const emprestimo_id = document.getElementById('template_emprestimo_id').value;
    const parcela_numero = document.getElementById('template_parcela_numero').value;
    const template_id = document.getElementById('template_id').value;
    
    console.log('Dados do formulário:', {
        emprestimo_id,
        parcela_numero,
        template_id
    });
    
    // Validar template
    if (!template_id) {
        alert('Por favor, selecione um template de mensagem.');
        return;
    }
    
    try {
        // Validar array de parcelas
        if (typeof parcelas === 'undefined' || !Array.isArray(parcelas)) {
            throw new Error('Array de parcelas não está disponível');
        }
        
        console.log('Array parcelas:', parcelas);
        console.log('Buscando parcela com número:', parcela_numero);
        
        // Encontrar a parcela pelo número
        let parcela_encontrada = null;
        for (const p of parcelas) {
            console.log(`Comparando parcela ${p.numero} com ${parcela_numero}`);
            if (parseInt(p.numero) === parseInt(parcela_numero)) {
                parcela_encontrada = p;
                console.log('Parcela encontrada:', p);
                break;
            }
        }
        
        // Verificar se encontrou a parcela
        if (!parcela_encontrada || !parcela_encontrada.id) {
            throw new Error(`Parcela ${parcela_numero} não encontrada no array de parcelas`);
        }
        
        // Obter o ID da parcela
        const parcela_id = parcela_encontrada.id;
        
        // Formatar o telefone do cliente (remover caracteres não numéricos)
        const telefone = '<?= preg_replace("/[^0-9]/", "", $emprestimo["telefone"] ?? "") ?>';
        console.log('Telefone formatado:', telefone);
        
        // Construir URL para enviar_individual.php com caminho relativo
        const url = '<?= BASE_URL ?>mensagens/api/enviar_individual.php?emprestimo_id=' + emprestimo_id + '&parcela_id=' + parcela_id + '&template_id=' + template_id + '&telefone=' + telefone;
        console.log('URL construída:', url);
        
        // Fechar o modal antes de redirecionar
        const modalTemplate = bootstrap.Modal.getInstance(document.getElementById('modalTemplate'));
        if (modalTemplate) {
            modalTemplate.hide();
        }
        
        // Redirecionar para a URL de envio
        window.location.href = url;
        
    } catch (error) {
        console.error('Erro ao processar envio:', error);
        alert('Erro ao processar envio: ' + error.message);
    }
}

// Cria uma variável global com os dados das parcelas para uso em JavaScript
const parcelasData = <?php echo json_encode($parcelas); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Quando a página carregar completamente, abre o modal de pagamento para a parcela especificada
    setTimeout(function() {
        abrirModalPagamentoParcela(<?= $emprestimo_id ?>, <?= $parcela_numero ?>);
    }, 500);
});

// Função para abrir o modal de pagamento para uma parcela específica
function abrirModalPagamentoParcela(emprestimo_id, parcela_numero) {
    // Encontrar a parcela com o número especificado
    const parcela = parcelasData.find(p => parseInt(p.numero) === parseInt(parcela_numero));
    
    if (parcela) {
        // Converter valores para números com parseFloat
        const valorParcela = parseFloat(parcela.valor) || 0;
        const valorPago = parseFloat(parcela.valor_pago) || 0;
        
        // Abrir o modal de pagamento para esta parcela
        abrirModalPagamento(
            emprestimo_id,
            parcela.numero,
            valorParcela,
            valorPago,
            parcela.status
        );
        
        // Rolar até a seção de pagamento
        document.getElementById('pagamento').scrollIntoView({ behavior: 'smooth' });
    } else {
        console.error('Parcela não encontrada:', parcela_numero);
    }
}
</script>

<!-- Modal para Escolha de Template -->
<div class="modal fade" id="modalTemplate" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enviar Cobrança</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formTemplate">
                    <input type="hidden" id="template_emprestimo_id" name="emprestimo_id">
                    <input type="hidden" id="template_parcela_numero" name="parcela_numero">
                    
                    <div class="mb-3">
                        <label for="template_id" class="form-label">Escolha o Template de Mensagem</label>
                        <select class="form-select" id="template_id" name="template_id" required>
                            <option value="">Selecione...</option>
                            <?php
                            // Buscar templates disponíveis
                            $sql_templates = "SELECT * FROM templates_mensagens WHERE ativo = 1 ORDER BY status, nome";
                            $result_templates = $conn->query($sql_templates);
                            
                            if ($result_templates && $result_templates->num_rows > 0) {
                                while ($template = $result_templates->fetch_assoc()) {
                                    echo '<option value="' . $template['id'] . '" data-status="' . $template['status'] . '">' 
                                        . htmlspecialchars($template['nome']) . '</option>';
                                }
                            } else {
                                echo '<option value="" disabled>Nenhum template disponível</option>';
                            }
                            ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="enviarMensagem()">Enviar Mensagem</button>
            </div>
        </div>
    </div>
</div>

<?php
function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

function formatarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    $tamanho = strlen($telefone);
    if ($tamanho == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    }
    return $telefone;
}
?>

<style>
/* Estilos para os cards */
.card {
    border: 1px solid rgba(0,0,0,.125);
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
}

.card-header {
    padding: 1rem;
    color: white;
}

.card-header.cliente-header {
    background: linear-gradient(45deg, #2c7744, #1a4e2c);
}

.card-header.valores-header {
    background: linear-gradient(45deg, #277553, #1b503a);
}

.card-header.status-header {
    background: linear-gradient(45deg, #246e62, #194d45);
}

.card-header.resumo-header {
    background: linear-gradient(45deg, #1f6470, #17454d);
}

.card-header.parcelas-header {
    background: linear-gradient(45deg, #1b5962, #13404a);
}

.card-title {
    color: white;
    font-size: 1.1rem;
    font-weight: 600;
}

.card-body h6 {
    font-size: 1.5rem;
    font-weight: 600;
}

.card-body .text-muted {
    font-size: 1rem;
    color: #344767;
}

.info-value {
    text-align: right;
    color: #344767;
    font-weight: 500;
}

.parcela-card-footer {
    padding: 12px 16px;
    background: #f8f9fa;
    border-top: 1px solid rgba(0,0,0,0.05);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.parcela-card-footer .btn {
    padding: 8px 16px;
}

@media (max-width: 767.98px) {
    .parcela-card {
        margin: 8px;
    }
    
    .parcela-card-footer {
        flex-direction: row;
        justify-content: stretch;
    }
    
    .parcela-card-footer .btn {
        flex: 1;
    }
    
    .card-installment .btn {
        font-size: 0.8rem;
        padding: 0.375rem 0.5rem;
    }
    
    .card-installment .btn i {
        margin-right: 0.25rem;
    }
    
    .card-installment .card-body {
        padding-bottom: 0.75rem;
    }
}

/* Estilos para os Cards de Parcelas no Mobile */
.parcela-card {
    background: #fff;
    border-radius: 12px;
    margin: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(0,0,0,0.08);
    overflow: hidden;
}

.parcela-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.parcela-card[data-status="pago"] {
    border-left: 4px solid #28a745;
}

.parcela-card[data-status="pendente"] {
    border-left: 4px solid #ffc107;
}

.parcela-card[data-status="atrasado"] {
    border-left: 4px solid #dc3545;
}

.parcela-card[data-status="parcial"] {
    border-left: 4px solid #17a2b8;
}

.parcela-card-header {
    padding: 16px;
    display: flex;
}

/* Estilos para o toggle */
.cursor-pointer {
    cursor: pointer;
}

.toggle-icon {
    transition: transform 0.3s ease;
}

.toggle-icon.collapsed {
    transform: rotate(-180deg);
}

.card-content {
    transition: all 0.3s ease-out;
    max-height: 1000px; /* Altura máxima inicial */
    opacity: 1;
    padding: 1rem;
}

.card-content.collapsed {
    max-height: 0;
    opacity: 0;
    padding: 0;
    overflow: hidden;
}

/* Ajuste do espaçamento dos cards */
.row.g-5 > * {
    padding-top: 2rem;
    padding-bottom: 2rem;
}

/* Opções de distribuição do excedente */
.d-none {
    display: none;
}

/* Opções para pagamento parcial */
.d-none {
    display: none;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>