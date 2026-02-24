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

// Preparar HTML da tabela
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
        $valor_faltante = 0;
        
        foreach ($parcelas as $p) {
            if ($p['status'] === 'pago') {
                $pagas++;
                $valor_total_pago += isset($p['valor']) ? floatval($p['valor']) : 0;
            } elseif ($p['status'] === 'parcial') {
                $valor_total_pago += isset($p['valor_pago']) ? floatval($p['valor_pago']) : 0;
                $valor_faltante += (floatval($p['valor']) - floatval($p['valor_pago'] ?? 0));
            } elseif ($p['status'] === 'pendente' || $p['status'] === 'atrasado') {
                $valor_faltante += floatval($p['valor']);
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
    <tr class="clickable-row" data-href="visualizar.php?id=<?= htmlspecialchars($e['id']) ?>" style="cursor: pointer;">
        <td>
            <div class="d-flex flex-column">
                <div class="fw-bold"><?= htmlspecialchars($e['cliente_nome']) ?></div>
                <div class="text-muted small">Início: <?= date('d/m/Y', strtotime($e['data_inicio'])) ?></div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="fw-bold text-danger">R$ <?= number_format($valor_faltante, 2, ',', '.') ?></div>
                    <div class="text-muted small"><?= number_format($progresso, 1) ?>%</div>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" 
                         style="width: <?= $progresso ?>%"
                         aria-valuenow="<?= $progresso ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
                <div class="text-muted small"><?= $total_parcelas - $pagas ?> parcelas restantes</div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column">
                <div class="fw-bold">R$ <?= number_format((float)$e['valor_emprestado'], 2, ',', '.') ?></div>
                <?php if (!empty($e['juros_percentual']) && $e['juros_percentual'] > 0): ?>
                    <div class="text-muted small"><?= $e['juros_percentual'] ?>% juros</div>
                <?php endif; ?>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="fw-bold"><?= (int)$e['parcelas'] ?>x R$ <?= number_format((float)$e['valor_parcela'], 2, ',', '.') ?></div>
                    <span class="badge <?= $status_class ?>"><?= ucfirst($status) ?></span>
                </div>
                <div class="text-muted small d-flex justify-content-between align-items-center">
                    <span><?= $pagas ?> pagas (R$ <?= number_format($valor_total_pago, 2, ',', '.') ?>)</span>
                </div>
            </div>
        </td>
        <td class="text-end">
            <div class="btn-group">
                <a href="visualizar.php?id=<?= $e['id'] ?>" 
                   class="btn btn-sm btn-outline-primary" 
                   title="Ver Detalhes">
                    <i class="bi bi-eye"></i>
                </a>
                <a href="excluir.php?id=<?= $e['id'] ?>" 
                   class="btn btn-sm btn-outline-danger" 
                   title="Excluir Empréstimo">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="7" class="text-center py-4">
            <div class="text-muted">Nenhum empréstimo encontrado</div>
        </td>
    </tr>
<?php endif; ?>

<?php
$html_tabela = ob_get_clean();

// Preparar HTML da paginação
ob_start();
if ($total_paginas > 1):
?>
<div class="d-flex justify-content-between align-items-center mt-3">
    <div class="text-muted">
        Mostrando página <?= $pagina_atual ?> de <?= $total_paginas ?>
    </div>
    <nav aria-label="Navegação das páginas">
        <ul class="pagination mb-0">
            <?php if ($pagina_atual > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="#" data-pagina="1" aria-label="Primeira">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" data-pagina="<?= $pagina_atual - 1 ?>" aria-label="Anterior">
                        <span aria-hidden="true">&lsaquo;</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php
            $inicio = max(1, $pagina_atual - 2);
            $fim = min($total_paginas, $pagina_atual + 2);

            if ($inicio > 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            for ($i = $inicio; $i <= $fim; $i++) {
                echo '<li class="page-item ' . ($i == $pagina_atual ? 'active' : '') . '">';
                echo '<a class="page-link" href="#" data-pagina="' . $i . '">' . $i . '</a>';
                echo '</li>';
            }

            if ($fim < $total_paginas) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            ?>

            <?php if ($pagina_atual < $total_paginas): ?>
                <li class="page-item">
                    <a class="page-link" href="#" data-pagina="<?= $pagina_atual + 1 ?>" aria-label="Próxima">
                        <span aria-hidden="true">&rsaquo;</span>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" data-pagina="<?= $total_paginas ?>" aria-label="Última">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php
endif;
$html_paginacao = ob_get_clean();

// Retornar resultado em JSON
header('Content-Type: application/json');
echo json_encode([
    'html_tabela' => $html_tabela,
    'html_paginacao' => $html_paginacao,
    'total_registros' => $resultado['total_registros'],
    'pagina_atual' => $pagina_atual,
    'total_paginas' => $total_paginas
]); 