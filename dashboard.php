<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/autenticacao.php';

// Verificar se o usuário é administrador
apenasAdmin();

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/verificar_parcelas.php';

// Executar verificação de parcelas vencidas
verificarAtualizarParcelasVencidas();

// Inicializa variáveis com valores padrão
$emprestimos = [];
$ultimos_emprestimos = [];
$total_atrasado = 0;
$total_emprestimos_ativos = 0;
$total_emprestimos = 0;

try {
    // Busca empréstimos com tratamento de erro
    $resultado = buscarTodosEmprestimosComCliente($conn);
    $emprestimos = $resultado['emprestimos'] ?? [];
    if (!empty($emprestimos)) {
        $ultimos_emprestimos = array_slice($emprestimos, 0, 5);
    }

    // Calcula totais com tratamento de erro
    $total_emprestimos = contarTotalEmprestimos($conn);
    $total_emprestimos_ativos = contarEmprestimosAtivos($conn);
    
    // Debug - verificar empréstimos ativos manualmente
    $sql_debug_ativos = "SELECT id, cliente_id, status FROM emprestimos WHERE (status = 'ativo' OR status IS NULL)";
    $result_debug_ativos = $conn->query($sql_debug_ativos);
    $emprestimos_ativos_ids = [];
    while ($row = $result_debug_ativos->fetch_assoc()) {
        $emprestimos_ativos_ids[] = $row['id'];
    }
    error_log("Total de empréstimos ativos (IDs): " . implode(', ', $emprestimos_ativos_ids));
    error_log("Total de empréstimos ativos (contagem): " . count($emprestimos_ativos_ids));
    
    // Cálculos adicionais para cards
    $total_emprestado = 0;
    $total_recebido = 0;
    
    foreach ($emprestimos as $e) {
        if (isset($e['valor_emprestado'])) {
            $total_emprestado += floatval($e['valor_emprestado']);
        }
        if (isset($e['total_pago'])) {
            $total_recebido += floatval($e['total_pago']);
        }
    }
    
    // Calculando o total pendente - somando todas as parcelas pendentes
    $sql_pendente = "SELECT 
                        SUM(
                            CASE 
                                WHEN p.status = 'pendente' THEN p.valor 
                                WHEN p.status = 'parcial' THEN (p.valor - IFNULL(p.valor_pago, 0))
                                ELSE 0 
                            END
                        ) AS total 
                    FROM parcelas p
                    INNER JOIN emprestimos e ON p.emprestimo_id = e.id
                    WHERE p.status IN ('pendente', 'parcial') 
                    AND (e.status != 'inativo' OR e.status IS NULL)";
    $result_pendente = $conn->query($sql_pendente);
    if ($result_pendente && $row_pendente = $result_pendente->fetch_assoc()) {
        $total_pendente = floatval($row_pendente['total'] ?? 0);
    } else {
        error_log("Erro ao calcular total pendente: " . $conn->error);
        $total_pendente = 0;
    }
    
    // Calculando o total a receber - somando todas as parcelas (pagas e não pagas) de empréstimos ativos
    $sql_a_receber = "SELECT 
                        SUM(p.valor) AS total 
                    FROM parcelas p
                    INNER JOIN emprestimos e ON p.emprestimo_id = e.id
                    WHERE e.status = 'ativo'";
    $result_a_receber = $conn->query($sql_a_receber);
    if ($result_a_receber && $row_a_receber = $result_a_receber->fetch_assoc()) {
        $total_a_receber = floatval($row_a_receber['total'] ?? 0);
    } else {
        error_log("Erro ao calcular total a receber: " . $conn->error);
        $total_a_receber = 0;
    }
    
    // Calculando o total que falta receber - somando todas as parcelas não pagas
    $sql_falta_receber = "SELECT 
                        SUM(
                            CASE 
                                WHEN p.status = 'pendente' THEN p.valor 
                                WHEN p.status = 'parcial' THEN (p.valor - IFNULL(p.valor_pago, 0))
                                WHEN p.status = 'atrasado' THEN p.valor
                                ELSE 0 
                            END
                        ) AS total 
                    FROM parcelas p
                    INNER JOIN emprestimos e ON p.emprestimo_id = e.id
                    WHERE p.status != 'pago'";
    $result_falta_receber = $conn->query($sql_falta_receber);
    if ($result_falta_receber && $row_falta_receber = $result_falta_receber->fetch_assoc()) {
        $total_falta_receber = floatval($row_falta_receber['total'] ?? 0);
    } else {
        error_log("Erro ao calcular total que falta receber: " . $conn->error);
        $total_falta_receber = 0;
    }
    
    // Calculando total atrasado corretamente
    $sql_atrasado = "SELECT 
                        SUM(
                            CASE 
                                WHEN status = 'parcial' THEN (valor - IFNULL(valor_pago, 0))
                                ELSE valor 
                            END
                        ) AS total_valor,
                        COUNT(DISTINCT emprestimo_id) AS total_emprestimos,
                        COUNT(id) AS total_parcelas
                     FROM parcelas 
                     WHERE (status = 'atrasado' OR (status IN ('pendente', 'parcial') AND vencimento < CURRENT_DATE))";
    
    $stmt_atrasado = $conn->prepare($sql_atrasado);
    if (!$stmt_atrasado) {
        error_log("Erro ao preparar a query de parcelas atrasadas: " . $conn->error);
        $total_atrasado = 0;
        $emprestimos_atrasados = 0;
        $parcelas_atrasadas = 0;
    } else {
        $stmt_atrasado->execute();
        $result_atrasado = $stmt_atrasado->get_result();
        
        if ($result_atrasado && $row_atrasado = $result_atrasado->fetch_assoc()) {
            $total_atrasado = floatval($row_atrasado['total_valor'] ?? 0);
            $emprestimos_atrasados = intval($row_atrasado['total_emprestimos'] ?? 0);
            $parcelas_atrasadas = intval($row_atrasado['total_parcelas'] ?? 0);
        } else {
            error_log("Erro ao calcular total atrasado: " . $conn->error);
            $total_atrasado = 0;
            $emprestimos_atrasados = 0;
            $parcelas_atrasadas = 0;
        }
    }
} catch (Exception $e) {
    // Log do erro (você pode implementar um sistema de log)
    error_log("Erro no dashboard: " . $e->getMessage());
}
?>

<div class="container py-1">
    <h2 class="mb-4 text-uppercase">🚀 Painel Financeiro</h2>

    <!-- Área de Ações Rápidas Modernizada -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-muted">Ações Rápidas</h6>
                        <div class="d-flex gap-2">
                            <a href="emprestimos/novo.php" class="btn btn-sm btn-success d-flex align-items-center gap-2">
                                <i class="bi bi-plus-circle-fill"></i>
                                <span class="d-none d-md-inline">Novo Empréstimo</span>
                            </a>
                            <a href="clientes/novo.php" class="btn btn-sm btn-primary d-flex align-items-center gap-2">
                                <i class="bi bi-person-plus-fill"></i>
                                <span class="d-none d-md-inline">Novo Cliente</span>
                            </a>
                            <a href="relatorios/" class="btn btn-sm btn-info text-white d-flex align-items-center gap-2">
                                <i class="bi bi-graph-up"></i>
                                <span class="d-none d-md-inline">Relatório Diário</span>
                            </a>
                            <a href="emprestimos/parcelas/cobrancas/" class="btn btn-sm btn-warning d-flex align-items-center gap-2">
                                <i class="bi bi-bell-fill"></i>
                                <span class="d-none d-md-inline">Cobranças</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards Informativos - Desktop -->
    <div class="d-none d-md-block">
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Emprestado</h6>
                        <h4 class="mb-0">R$ <?= number_format($total_emprestado, 2, ',', '.') ?></h4>
                        <p class="mt-1 mb-0">A receber: R$ <?= number_format($total_a_receber, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Recebido</h6>
                        <h4 class="mb-0">R$ <?= number_format($total_recebido, 2, ',', '.') ?></h4>
                        <p class="mt-1 mb-0">Falta receber: R$ <?= number_format($total_falta_receber, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Pendente</h6>
                        <h4 class="mb-0">R$ <?= number_format($total_pendente, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Empréstimos</h6>
                        <h4 class="mb-0"><?= $total_emprestimos ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-primary bg-opacity-75 text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Ativos</h6>
                        <h4 class="mb-0"><?= (int)$total_emprestimos_ativos ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Parcelas Atrasadas</h6>
                         <h4><?= $parcelas_atrasadas ?> parcelas | R$ <?= number_format($total_atrasado, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cards Informativos - Mobile -->
    <div class="d-md-none mb-4">
        <div class="row g-3">
            <div class="col-6">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Emprestado</h6>
                        <h4 class="mb-0">R$ <?= number_format($total_emprestado, 2, ',', '.') ?></h4>
                        <p class="mt-1 mb-0">A receber: R$ <?= number_format($total_a_receber, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Recebido</h6>
                        <h4 class="mb-0">R$ <?= number_format($total_recebido, 2, ',', '.') ?></h4>
                        <p class="mt-1 mb-0">Falta receber: R$ <?= number_format($total_falta_receber, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Pendente</h6>
                        <h4 class="mb-0">R$ <?= number_format($total_pendente, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Empréstimos</h6>
                        <h4 class="mb-0"><?= $total_emprestimos ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-primary bg-opacity-75 text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Ativos</h6>
                        <h4 class="mb-0"><?= (int)$total_emprestimos_ativos ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Atrasados</h6>
                        <h4 class="mb-0"><?= $emprestimos_atrasados ?></h4>
                        <small><?= $parcelas_atrasadas ?> parcelas | R$ <?= number_format($total_atrasado, 2, ',', '.') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cobranças via WhatsApp -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
            <h5 class="mb-0">Envio de Cobranças via WhatsApp</h5>
            <a href="mensagens/templates_mensagens.php" class="btn btn-sm btn-outline-primary bg-white text-dark">Gerenciar Templates</a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php
                // Buscar templates de mensagens para uso nos botões
                $sql_templates = "SELECT id, nome, status FROM templates_mensagens WHERE ativo = 1 ORDER BY status, nome";
                $templates_result = $conn->query($sql_templates);
                $templates = [];
                
                if ($templates_result && $templates_result->num_rows > 0) {
                    while ($template = $templates_result->fetch_assoc()) {
                        $templates[] = $template;
                    }
                }

                // Templates por status
                $templates_atrasados = array_filter($templates, function($t) { return $t['status'] == 'atrasado'; });
                $templates_pendentes = array_filter($templates, function($t) { return $t['status'] == 'pendente'; });
                $templates_quitados = array_filter($templates, function($t) { return $t['status'] == 'quitado'; });
                ?>
                
                <!-- Cobranças de Parcelas Atrasadas -->
                <div class="col-md-6">
                    <div class="card h-100 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">Parcelas Atrasadas</h6>
                        </div>
                        <div class="card-body">
                            <p class="card-text mb-3">Enviar mensagens para clientes com parcelas em atraso.</p>
                            <div class="d-grid gap-2">
                                <?php if (!empty($templates_atrasados)): ?>
                                    <?php foreach($templates_atrasados as $template): ?>
                                        <a href="javascript:void(0);" 
                                           class="btn btn-outline-danger btn-enviar-mensagem"
                                           data-status="atrasado"
                                           data-template="<?= $template['id'] ?>"
                                           data-template-nome="<?= htmlspecialchars($template['nome']) ?>">
                                            <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($template['nome']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0">
                                        Nenhum template para parcelas atrasadas encontrado.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cobranças de Parcelas Pendentes -->
                <div class="col-md-6">
                    <div class="card h-100 border-warning">
                        <div class="card-header bg-warning">
                            <h6 class="mb-0">Vencimentos Hoje</h6>
                        </div>
                        <div class="card-body">
                            <p class="card-text mb-3">Enviar para clientes com parcelas que vencem hoje.</p>
                            <div class="d-grid gap-2">
                                <?php if (!empty($templates_pendentes)): ?>
                                    <?php foreach($templates_pendentes as $template): ?>
                                        <a href="javascript:void(0);" 
                                           class="btn btn-outline-warning btn-enviar-mensagem"
                                           data-status="vence_hoje"
                                           data-template="<?= $template['id'] ?>"
                                           data-template-nome="<?= htmlspecialchars($template['nome']) ?>">
                                            <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($template['nome']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0">
                                        Nenhum template para parcelas pendentes encontrado.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos Empréstimos -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Últimos Empréstimos</h5>
            <a href="emprestimos/" class="btn btn-sm btn-primary">Ver Todos</a>
        </div>
        <div class="card-body p-0">
            <!-- Filtro de busca -->
            <div class="p-3 border-bottom">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" id="filtroCliente" class="form-control form-control-sm" placeholder="Filtrar por cliente...">
                    </div>
                    <div class="col-md-3">
                        <select id="filtroStatus" class="form-select form-select-sm">
                            <option value="">Todos os status</option>
                            <option value="Ativo">Ativo</option>
                            <option value="Atrasado">Atrasado</option>
                            <option value="Quitado">Quitado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="filtroTipo" class="form-select form-select-sm">
                            <option value="">Todos os tipos</option>
                            <option value="Parcelamento Comum">Parcelamento Comum</option>
                            <option value="Reparcelado c/ Juros">Reparcelado c/ Juros</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button id="limparFiltros" class="btn btn-sm btn-outline-secondary w-100">Limpar</button>
                    </div>
                </div>
            </div>
            
            <!-- Tabela para Desktop -->
            <div class="d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 25%">Cliente</th>
                                <th style="width: 15%">Tipo</th>
                                <th style="width: 15%">Valor</th>
                                <th style="width: 15%">Parcelas</th>
                                <th style="width: 15%">Progresso</th>
                                <th style="width: 8%">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ultimos_emprestimos)): ?>
                                <?php foreach ($ultimos_emprestimos as $e): 
                                    // Busca as parcelas na tabela parcelas
                                    $stmt = $conn->prepare("SELECT 
                                        status, valor, valor_pago, vencimento 
                                        FROM parcelas 
                                        WHERE emprestimo_id = ?");
                                    $stmt->bind_param("i", $e['id']);
                                    $stmt->execute();
                                    $result_parcelas = $stmt->get_result();
                                    $parcelas = $result_parcelas->fetch_all(MYSQLI_ASSOC);
                                    
                                    // Calcula o progresso
                                    $total_parcelas = count($parcelas);
                                    $pagas = 0;
                                    $valor_total_pago = 0;
                                    foreach ($parcelas as $p) {
                                        if ($p['status'] === 'pago') {
                                            $pagas++;
                                            $valor_total_pago += isset($p['valor']) ? floatval($p['valor']) : 0;
                                        } elseif ($p['status'] === 'parcial') {
                                            $valor_total_pago += isset($p['valor_pago']) ? floatval($p['valor_pago']) : 0;
                                        }
                                    }
                                    $progresso = ($total_parcelas > 0) ? ($pagas / $total_parcelas) * 100 : 0;
                                    
                                    // Calcula o status do empréstimo
                                    $status = 'quitado';
                                    $tem_atrasada = false;
                                    $tem_pendente = false;

                                    foreach ($parcelas as $p) {
                                        if ($p['status'] !== 'pago') {
                                            $tem_pendente = true;
                                            $status = 'ativo';
                                            
                                            $data_vencimento = new DateTime($p['vencimento']);
                                            $hoje = new DateTime();
                                            
                                            if ($data_vencimento < $hoje) {
                                                $tem_atrasada = true;
                                                $status = 'atrasado';
                                                break;
                                            }
                                        }
                                    }
                                    
                                    // Define as classes de status
                                    $status_class = match($status) {
                                        'ativo' => 'text-bg-primary',
                                        'atrasado' => 'text-bg-danger',
                                        'quitado' => 'text-bg-success',
                                        default => 'text-bg-secondary'
                                    };
                                ?>
                                    <tr class="clickable-row" data-href="emprestimos/visualizar.php?id=<?= htmlspecialchars($e['id']) ?>" style="cursor: pointer;">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($e['cliente_nome']) ?></div>
                                                    <small class="text-muted">
                                                        Início: <?= date('d/m/Y', strtotime($e['data_inicio'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-info">
                                                <?php
                                                    $tipos = [
                                                        'parcelada_comum' => 'Parcelamento Comum',
                                                        'reparcelada_com_juros' => 'Reparcelado c/ Juros'
                                                    ];
                                                    $tipo = $e['tipo_de_cobranca'] ?? '';
                                                    echo htmlspecialchars($tipos[$tipo] ?? 'Não definido');
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold">R$ <?= number_format((float)$e['valor_emprestado'], 2, ',', '.') ?></div>
                                            <?php if (!empty($e['juros_percentual']) && $e['juros_percentual'] > 0): ?>
                                                <small class="text-muted">
                                                    <?= $e['juros_percentual'] ?>% juros
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= (int)$e['parcelas'] ?>x R$ <?= number_format((float)$e['valor_parcela'], 2, ',', '.') ?></div>
                                            <small class="text-muted">
                                                <?= $pagas ?> pagas (R$ <?= number_format($valor_total_pago, 2, ',', '.') ?>)
                                            </small>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?= $progresso ?>%"
                                                     aria-valuenow="<?= $progresso ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                            <small class="text-muted"><?= number_format($progresso, 1) ?>%</small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($status) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-info-circle me-2"></i>
                                            Nenhum empréstimo encontrado
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Cards para Mobile -->
            <div class="d-md-none">
                <div class="list-group list-group-flush">
                    <?php if (!empty($ultimos_emprestimos)): ?>
                        <?php foreach ($ultimos_emprestimos as $e): 
                            // Busca as parcelas na tabela parcelas
                            $stmt = $conn->prepare("SELECT 
                                status, valor, valor_pago, vencimento 
                                FROM parcelas 
                                WHERE emprestimo_id = ?");
                            $stmt->bind_param("i", $e['id']);
                            $stmt->execute();
                            $result_parcelas = $stmt->get_result();
                            $parcelas = $result_parcelas->fetch_all(MYSQLI_ASSOC);
                            
                            // Calcula o progresso
                            $total_parcelas = count($parcelas);
                            $pagas = 0;
                            $valor_total_pago = 0;
                            foreach ($parcelas as $p) {
                                if ($p['status'] === 'pago') {
                                    $pagas++;
                                    $valor_total_pago += isset($p['valor']) ? floatval($p['valor']) : 0;
                                } elseif ($p['status'] === 'parcial') {
                                    $valor_total_pago += isset($p['valor_pago']) ? floatval($p['valor_pago']) : 0;
                                }
                            }
                            $progresso = ($total_parcelas > 0) ? ($pagas / $total_parcelas) * 100 : 0;
                            
                            // Calcula o status do empréstimo
                            $status = 'quitado';
                            $tem_atrasada = false;
                            $tem_pendente = false;

                            foreach ($parcelas as $p) {
                                if ($p['status'] !== 'pago') {
                                    $tem_pendente = true;
                                    $status = 'ativo';
                                    
                                    $data_vencimento = new DateTime($p['vencimento']);
                                    $hoje = new DateTime();
                                    
                                    if ($data_vencimento < $hoje) {
                                        $tem_atrasada = true;
                                        $status = 'atrasado';
                                        break;
                                    }
                                }
                            }
                            
                            // Define as classes de status
                            $status_class = match($status) {
                                'ativo' => 'text-bg-primary',
                                'atrasado' => 'text-bg-danger',
                                'quitado' => 'text-bg-success',
                                default => 'text-bg-secondary'
                            };
                            
                            $tipos = [
                                'parcelada_comum' => 'Parcelamento Comum',
                                'reparcelada_com_juros' => 'Reparcelado c/ Juros'
                            ];
                            $tipo = $e['tipo_de_cobranca'] ?? '';
                        ?>
                            <div class="list-group-item p-3 mb-3 border rounded shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($e['cliente_nome']) ?></h6>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted d-block">
                                        Início: <?= date('d/m/Y', strtotime($e['data_inicio'])) ?>
                                    </small>
                                    <span class="badge text-bg-info">
                                        <?= htmlspecialchars($tipos[$tipo] ?? 'Não definido') ?>
                                    </span>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Valor</small>
                                        <strong>R$ <?= number_format((float)$e['valor_emprestado'], 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Parcelas</small>
                                        <strong><?= (int)$e['parcelas'] ?>x R$ <?= number_format((float)$e['valor_parcela'], 2, ',', '.') ?></strong>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= $progresso ?>%"
                                             aria-valuenow="<?= $progresso ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted"><?= number_format($progresso, 1) ?>% concluído</small>
                                        <small class="text-muted"><?= $pagas ?>/<?= $total_parcelas ?> parcelas</small>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="emprestimos/visualizar.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
                                        <i class="bi bi-eye"></i> Detalhes
                                    </a>
                                    <a href="emprestimos/visualizar.php?id=<?= $e['id'] ?>#pagamento" class="btn btn-sm btn-outline-success flex-fill">
                                        <i class="bi bi-cash-coin"></i> Pagar
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-info flex-fill" onclick="enviarCobranca(<?= $e['id'] ?>)">
                                        <i class="bi bi-whatsapp"></i> Cobrar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            Nenhum empréstimo encontrado
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Adiciona padding no final para o último card não ficar colado no fim da página -->
                <div class="pb-3"></div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Ajusta o tamanho das colunas */
    .table th, .table td {
        padding: 0.5rem;
        font-size: 0.9rem;
    }
    
    /* Trunca texto longo com reticências */
    .text-truncate {
        max-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    /* Ajusta o tamanho dos badges */
    .badge {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
    
    /* Ajusta o tamanho dos botões */
    .btn-sm {
        padding: 0.5rem 0.8rem;
        font-size: 0.875rem;
        border-radius: 0.5rem;
        transition: all 0.2s ease;
    }

    .btn-sm:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .btn-sm i {
        font-size: 1rem;
    }

    .gap-2 {
        gap: 0.5rem;
    }

    .shadow-sm {
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    }

    .border-0 {
        border: none;
    }

    /* Estilo para linhas clicáveis */
    .clickable-row {
        cursor: pointer;
    }
    .clickable-row:hover {
        background-color: rgba(0,0,0,.05);
    }

    /* Estilos para os cards informativos */
    .cardBox {
        padding: 1.5rem;
        border-radius: 0.5rem;
        position: relative;
        overflow: hidden;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .bg-red {
        background-color: #dc3545;
        color: white;
    }

    .bg-gray {
        background-color: #6c757d;
        color: white;
    }

    .cardBox .title {
        font-size: 1rem;
        font-weight: 500;
        opacity: 0.8;
    }

    .cardBox .subtitle {
        font-size: 1.5rem;
        font-weight: 600;
    }

    .icon-bg-bi i {
        position: absolute;
        right: 1rem;
        bottom: 1rem;
        font-size: 3rem;
        opacity: 0.2;
    }
    
    /* Classes para cores de fundo */
    .text-bg-primary {
        background-color: var(--bs-primary);
        color: white;
    }
    
    .text-bg-danger {
        background-color: var(--bs-danger);
        color: white;
    }
    
    .text-bg-success {
        background-color: var(--bs-success);
        color: white;
    }
    
    .text-bg-info {
        background-color: var(--bs-info);
        color: white;
    }
    
    .text-bg-secondary {
        background-color: var(--bs-secondary);
        color: white;
    }
    
    /* Ajuste para a barra de progresso */
    .progress {
        border-radius: 0.25rem;
        margin-bottom: 0.25rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tornar as linhas da tabela clicáveis
        const rows = document.querySelectorAll('.clickable-row');
        rows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Previne o clique se o usuário clicar em um botão ou formulário
                if (e.target.closest('button') || e.target.closest('form') || e.target.closest('a')) {
                    return;
                }
                
                // Redirecionar para o URL especificado no atributo data-href
                if (this.dataset.href) {
                    window.location.href = this.dataset.href;
                }
            });
        });

        // Implementação do filtro de tabela
        const filtroCliente = document.getElementById('filtroCliente');
        const filtroStatus = document.getElementById('filtroStatus');
        const filtroTipo = document.getElementById('filtroTipo');
        const limparFiltros = document.getElementById('limparFiltros');
        const tabela = document.querySelector('.table tbody');
        const linhas = tabela.querySelectorAll('tr');

        function aplicarFiltros() {
            const valorCliente = filtroCliente.value.toLowerCase();
            const valorStatus = filtroStatus.value;
            const valorTipo = filtroTipo.value;

            linhas.forEach(linha => {
                if (linha.querySelector('td:nth-child(1)')) { // Verifica se é uma linha de dados
                    const cliente = linha.querySelector('td:nth-child(1)').textContent.toLowerCase();
                    const status = linha.querySelector('td:nth-child(6) .badge').textContent.trim();
                    const tipo = linha.querySelector('td:nth-child(2)').textContent.trim();
                    
                    const matchCliente = cliente.includes(valorCliente);
                    const matchStatus = valorStatus === '' || status === valorStatus;
                    const matchTipo = valorTipo === '' || tipo === valorTipo;
                    
                    if (matchCliente && matchStatus && matchTipo) {
                        linha.style.display = '';
                    } else {
                        linha.style.display = 'none';
                    }
                }
            });
        }

        filtroCliente.addEventListener('input', aplicarFiltros);
        filtroStatus.addEventListener('change', aplicarFiltros);
        filtroTipo.addEventListener('change', aplicarFiltros);
        
        limparFiltros.addEventListener('click', function() {
            filtroCliente.value = '';
            filtroStatus.value = '';
            filtroTipo.value = '';
            aplicarFiltros();
        });
        
        // Função para enviar cobrança via WhatsApp
        window.enviarCobranca = function(id) {
            // Implementar lógica de envio de cobrança
            alert('Função de envio de cobrança será implementada em breve!');
        };

        // Processamento dos botões de envio de mensagens WhatsApp
        const botoesEnviarMensagem = document.querySelectorAll('.btn-enviar-mensagem');
        
        botoesEnviarMensagem.forEach(botao => {
            botao.addEventListener('click', function() {
                const status = this.getAttribute('data-status');
                const templateId = this.getAttribute('data-template');
                const templateNome = this.getAttribute('data-template-nome');
                
                if (confirm(`Deseja enviar mensagens para todos os clientes com parcelas ${status}?\nTemplate: ${templateNome}`)) {
                    // Mostrar indicador de carregamento
                    this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...`;
                    this.disabled = true;
                    
                    // Fazer requisição AJAX para a API de envio
                    fetch(`mensagens/api/enviar.php?coletiva=sim&status=${status}&template=${templateId}`, {
                        method: 'GET'
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Restaurar botão
                        this.innerHTML = `<i class="bi bi-whatsapp"></i> ${templateNome}`;
                        this.disabled = false;
                        
                        // Mostrar resultado
                        if (data.sucesso) {
                            // Criar modal de sucesso
                            const modalHtml = `
                                <div class="modal fade" id="resultadoEnvioModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">Envio de Mensagens - Resultado</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-success">
                                                    ${data.mensagem}
                                                </div>
                                                
                                                ${data.total_enviados > 0 ? `
                                                <h6 class="mt-3">Mensagens Enviadas (${data.total_enviados}):</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th>Cliente</th>
                                                                <th>Telefone</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            ${data.enviados.map(item => `
                                                                <tr>
                                                                    <td>${item.cliente}</td>
                                                                    <td>${item.telefone}</td>
                                                                </tr>
                                                            `).join('')}
                                                        </tbody>
                                                    </table>
                                                </div>
                                                ` : ''}
                                                
                                                ${data.total_falhas > 0 ? `
                                                <h6 class="mt-3 text-danger">Falhas no Envio (${data.total_falhas}):</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th>Cliente</th>
                                                                <th>Telefone</th>
                                                                <th>Erro</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            ${data.falhas.map(item => `
                                                                <tr>
                                                                    <td>${item.cliente}</td>
                                                                    <td>${item.telefone}</td>
                                                                    <td>${item.erro}</td>
                                                                </tr>
                                                            `).join('')}
                                                        </tbody>
                                                    </table>
                                                </div>
                                                ` : ''}
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Adicionar modal ao DOM
                            const modalContainer = document.createElement('div');
                            modalContainer.innerHTML = modalHtml;
                            document.body.appendChild(modalContainer);
                            
                            // Mostrar modal
                            const modal = new bootstrap.Modal(document.getElementById('resultadoEnvioModal'));
                            modal.show();
                            
                            // Remover modal do DOM quando for fechado
                            document.getElementById('resultadoEnvioModal').addEventListener('hidden.bs.modal', function() {
                                document.body.removeChild(modalContainer);
                            });
                        } else {
                            alert(`Erro: ${data.mensagem}`);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        this.innerHTML = `<i class="bi bi-whatsapp"></i> ${templateNome}`;
                        this.disabled = false;
                        alert('Erro ao processar a solicitação. Verifique o console para mais detalhes.');
                    });
                }
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php' ?>
