<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/queries.php';

// Verificar permissões administrativas
apenasAdmin();

// Receber parâmetros
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 10;
$filtros = [
    'busca' => $_GET['busca'] ?? '',
    'tipo' => $_GET['tipo'] ?? '',
    'status' => $_GET['status'] ?? '',
    'ordem' => $_GET['ordem'] ?? ''
];

// Buscar dados
$resultado = buscarTodosEmprestimosComCliente($conn, $pagina_atual, $por_pagina, $filtros);
$emprestimos = $resultado['emprestimos'];
$total_paginas = $resultado['total_paginas'];

// Preparar HTML dos cards
ob_start();
?>

<?php if (count($emprestimos) > 0): ?>
    <?php foreach ($emprestimos as $e): 
        // Busca as parcelas do empréstimo
        $stmt = $conn->prepare("
            SELECT numero, valor, valor_pago, status, vencimento 
            FROM parcelas 
            WHERE emprestimo_id = ?
        ");
        $stmt->bind_param("i", $e['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $parcelas = $result->fetch_all(MYSQLI_ASSOC);
        
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
            <?php
            // Calcula o valor total que falta pagar
            $valor_faltante = 0;
            foreach ($parcelas as $p) {
                if ($p['status'] === 'pendente') {
                    $valor_faltante += floatval($p['valor']);
                } elseif ($p['status'] === 'parcial') {
                    $valor_faltante += (floatval($p['valor']) - floatval($p['valor_pago'] ?? 0));
                } elseif ($p['status'] === 'atrasado') {
                    $valor_faltante += floatval($p['valor']);
                }
            }
            ?>
            <div>
                <small class="text-muted d-block">Falta para finalizar:</small>
                <strong>R$ <?= number_format($valor_faltante, 2, ',', '.') ?></strong>
                <small class="text-muted d-block"><?= $total_parcelas - $pagas ?> parcelas restantes</small>
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-6">
                <small class="text-muted d-block">Valor</small>
                <strong>R$ <?= number_format($e['valor_emprestado'], 2, ',', '.') ?></strong>
            </div>
            <div class="col-6">
                <small class="text-muted d-block">Parcelas</small>
                <strong><?= $e['parcelas'] ?>x R$ <?= number_format($e['valor_parcela'], 2, ',', '.') ?></strong>
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

        <div class="d-flex gap-2 mt-3">
            <a href="visualizar.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
                <i class="bi bi-eye"></i> Visualizar
            </a>
            <a href="excluir.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger flex-fill">
                <i class="bi bi-trash"></i> Excluir
            </a>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="text-center py-4 text-muted">
        Nenhum empréstimo encontrado
    </div>
<?php endif; ?>

<?php
$html_cards = ob_get_clean();

// Retornar resultado em JSON
header('Content-Type: application/json');
echo json_encode([
    'html_cards' => $html_cards,
    'total_registros' => $resultado['total_registros'],
    'pagina_atual' => $pagina_atual,
    'total_paginas' => $total_paginas
]); 