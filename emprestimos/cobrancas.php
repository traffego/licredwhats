<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

// Habilita exibição de todos os erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Teste de conexão com o banco
echo '<div class="alert alert-info">';
echo '<strong>Testando conexão com o banco:</strong><br>';
if ($conn->connect_error) {
    echo "Falha na conexão: " . $conn->connect_error;
} else {
    echo "Conexão OK!<br>";
    // Tenta fazer uma consulta simples
    $test_query = "SHOW TABLES";
    $test_result = $conn->query($test_query);
    if ($test_result) {
        echo "Tabelas encontradas:<br>";
        while($row = $test_result->fetch_array()) {
            echo "- " . $row[0] . "<br>";
        }
    } else {
        echo "Erro ao listar tabelas: " . $conn->error;
    }
}
echo '</div>';

// Função para debug de erros no JSON
function debugJSON($json_string) {
    $json_error = json_last_error();
    if ($json_error !== JSON_ERROR_NONE) {
        echo '<div class="alert alert-danger">';
        echo '<strong>Erro ao decodificar JSON:</strong> ';
        switch ($json_error) {
            case JSON_ERROR_DEPTH:
                echo 'Profundidade máxima excedida';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                echo 'State mismatch (invalid or malformed JSON)';
                break;
            case JSON_ERROR_CTRL_CHAR:
                echo 'Caractere de controle encontrado';
                break;
            case JSON_ERROR_SYNTAX:
                echo 'Erro de sintaxe';
                break;
            case JSON_ERROR_UTF8:
                echo 'Caracteres UTF-8 malformados';
                break;
            default:
                echo 'Erro desconhecido';
                break;
        }
        echo '<br><strong>JSON recebido:</strong> <pre>' . htmlspecialchars($json_string) . '</pre>';
        echo '</div>';
    }
}

// Função para verificar se um empréstimo está quitado
function emprestimoQuitado($parcelas) {
    if (!is_array($parcelas)) return false;
    
    foreach ($parcelas as $parcela) {
        if ($parcela['status'] !== 'pago') {
            return false;
        }
    }
    return true;
}

// Primeiro, vamos buscar todos os empréstimos
$sql = "SELECT 
            e.id as emprestimo_id,
            e.cliente_id,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            e.valor_emprestado,
            e.json_parcelas,
            e.data_emprestimo
        FROM emprestimos e
        INNER JOIN clientes c ON e.cliente_id = c.id
        ORDER BY e.id DESC";

$result = $conn->query($sql);
$emprestimos = [];
$parcelas_cobranca = [];

// Debug dos empréstimos encontrados
echo '<div class="alert alert-info">';
echo '<strong>Empréstimos encontrados:</strong> ' . $result->num_rows . '<br>';
echo '</div>';

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Adiciona ao array de empréstimos
        $emprestimos[] = [
            'id' => $row['emprestimo_id'],
            'cliente_id' => $row['cliente_id'],
            'cliente_nome' => $row['cliente_nome'],
            'cliente_telefone' => $row['cliente_telefone'],
            'valor_emprestado' => $row['valor_emprestado'],
            'json_parcelas' => $row['json_parcelas'],
            'data_emprestimo' => $row['data_emprestimo']
        ];
    }
}

// Debug do array de empréstimos
echo '<div style="display:none;" class="debug-json">';
echo '<strong>Array de empréstimos:</strong><br>';
echo '<pre>' . htmlspecialchars(json_encode($emprestimos, JSON_PRETTY_PRINT)) . '</pre>';
echo '</div>';

// Processa cada empréstimo
foreach ($emprestimos as $emprestimo) {
    // Debug do JSON antes de decodificar
    echo '<div style="display:none;" class="debug-json">';
    echo '<strong>JSON para empréstimo #' . $emprestimo['id'] . ':</strong><br>';
    echo '<pre>' . htmlspecialchars($emprestimo['json_parcelas']) . '</pre>';
    echo '</div>';
    
    $parcelas = json_decode($emprestimo['json_parcelas'], true);
    debugJSON($emprestimo['json_parcelas']);
    
    if ($parcelas === null) {
        echo '<div class="alert alert-warning">';
        echo 'Erro ao decodificar parcelas do empréstimo #' . $emprestimo['id'];
        echo '</div>';
        continue;
    }

    // Pula empréstimos quitados
    if (emprestimoQuitado($parcelas)) {
        continue;
    }

    // Para cada parcela do empréstimo
    foreach ($parcelas as $parcela) {
        // Pula se a parcela já foi totalmente paga
        if ($parcela['status'] === 'pago') {
            continue;
        }

        // Calcula dias em atraso
        $vencimento = new DateTime($parcela['vencimento']);
        $hoje = new DateTime();
        
        $diff = $hoje->diff($vencimento);
        // Considera atrasada se a data de vencimento já passou
        $dias_atraso = $vencimento < $hoje ? $diff->days : 0;

        // Define o status da parcela para exibição
        $status_exibicao = $parcela['status'];
        if ($dias_atraso > 0 && $parcela['status'] !== 'pago') {
            $status_exibicao = 'atrasado';
        }

        // Calcula valor pendente (para parcelas parciais)
        $valor_pendente = $parcela['status'] === 'parcial' 
            ? floatval($parcela['valor']) - floatval($parcela['valor_pago'])
            : floatval($parcela['valor']);

        $parcelas_cobranca[] = [
            'emprestimo_id' => $emprestimo['id'],
            'cliente_id' => $emprestimo['cliente_id'],
            'cliente_nome' => $emprestimo['cliente_nome'],
            'cliente_telefone' => preg_replace('/[^0-9]/', '', $emprestimo['cliente_telefone']),
            'numero_parcela' => $parcela['numero'],
            'valor_original' => floatval($parcela['valor_original']),
            'valor_atual' => floatval($parcela['valor']),
            'valor_pendente' => $valor_pendente,
            'valor_pago' => floatval($parcela['valor_pago']),
            'vencimento' => $parcela['vencimento'],
            'status' => $status_exibicao,
            'dias_atraso' => $dias_atraso,
            'data_pagamento' => $parcela['data_pagamento'],
            'forma_pagamento' => $parcela['forma_pagamento']
        ];
    }
}

// Debug do array final de parcelas
echo '<div style="display:none;" class="debug-json">';
echo '<strong>Array final de parcelas para cobrança:</strong><br>';
echo '<pre>' . htmlspecialchars(json_encode($parcelas_cobranca, JSON_PRETTY_PRINT)) . '</pre>';
echo '</div>';

// Ordena as parcelas por data de vencimento e depois por nome do cliente
usort($parcelas_cobranca, function($a, $b) {
    $comp_data = strtotime($a['vencimento']) - strtotime($b['vencimento']);
    if ($comp_data === 0) {
        return strcmp($a['cliente_nome'], $b['cliente_nome']);
    }
    return $comp_data;
});

// Calcula totais para o resumo
$total_pendente = 0;
$total_atrasado = 0;
$total_parcial = 0;
$quantidade_pendente = 0;
$quantidade_atrasado = 0;
$quantidade_parcial = 0;
$quantidade_hoje = 0;
$total_hoje = 0;

foreach ($parcelas_cobranca as $cobranca) {
    $valor = $cobranca['valor_pendente'];
    
    if ($cobranca['status'] === 'pendente') {
        $total_pendente += $valor;
        $quantidade_pendente++;
    } else if ($cobranca['status'] === 'atrasado') {
        $total_atrasado += $valor;
        $quantidade_atrasado++;
    } else if ($cobranca['status'] === 'parcial') {
        $total_parcial += $valor;
        $quantidade_parcial++;
    }
    
    if (date('Y-m-d', strtotime($cobranca['vencimento'])) === date('Y-m-d')) {
        $quantidade_hoje++;
        $total_hoje += $valor;
    }
}

$total_geral = $total_pendente + $total_atrasado + $total_parcial;
$quantidade_geral = $quantidade_pendente + $quantidade_atrasado + $quantidade_parcial;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Cobranças - Sistema de Empréstimos</title>
    <?php require_once __DIR__ . '/../includes/head.php'; ?>
    <style>
        .status-pendente {
            background-color: #ffd700;
            color: #000;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-atrasado {
            background-color: #dc3545;
            color: #fff;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-parcial {
            background-color: #17a2b8;
            color: #fff;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .dias-atraso {
            color: #dc3545;
            font-size: 0.875rem;
        }
        .valor-pendente {
            font-weight: bold;
        }
        .valor-pago {
            color: #28a745;
            font-size: 0.875rem;
        }
        /* Estilos para debug */
        .debug-json {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .debug-json pre {
            margin: 0;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <!-- Botão de Debug -->
    <div class="container-fluid mt-2">
        <button class="btn btn-secondary btn-sm" onclick="toggleDebug()">
            Mostrar/Ocultar Debug JSON
        </button>
    </div>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-warning text-dark mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Pendentes</h6>
                                        <p class="card-text">
                                            Quantidade: <?php echo $quantidade_pendente; ?><br>
                                            Total: R$ <?php echo number_format($total_pendente, 2, ',', '.'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Atrasados</h6>
                                        <p class="card-text">
                                            Quantidade: <?php echo $quantidade_atrasado; ?><br>
                                            Total: R$ <?php echo number_format($total_atrasado, 2, ',', '.'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Parciais</h6>
                                        <p class="card-text">
                                            Quantidade: <?php echo $quantidade_parcial; ?><br>
                                            Total: R$ <?php echo number_format($total_parcial, 2, ',', '.'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-primary text-white mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Vencem Hoje</h6>
                                        <p class="card-text">
                                            Quantidade: <?php echo $quantidade_hoje; ?><br>
                                            Total: R$ <?php echo number_format($total_hoje, 2, ',', '.'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Cobranças</h2>
                    <div>
                        <button type="button" class="btn btn-danger me-2" onclick="enviarParaAtrasados()">
                            <i class="bi bi-whatsapp"></i> Enviar para Atrasados
                        </button>
                        <button type="button" class="btn btn-primary" onclick="enviarCobrancasSelecionadas()">
                            <i class="bi bi-whatsapp"></i> Enviar Cobranças Selecionadas
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tabelaCobrancas">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selecionarTodos"></th>
                                        <th>Cliente</th>
                                        <th>Parcela</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parcelas_cobranca as $cobranca): ?>
                                    <tr data-status="<?php echo $cobranca['status']; ?>">
                                        <td>
                                            <input type="checkbox" class="selecionar-cobranca" 
                                                data-emprestimo="<?php echo $cobranca['emprestimo_id']; ?>"
                                                data-parcela="<?php echo $cobranca['numero_parcela']; ?>"
                                                data-telefone="<?php echo $cobranca['cliente_telefone']; ?>">
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($cobranca['cliente_nome']); ?><br>
                                            <small class="text-muted">
                                                <?php 
                                                    $telefone = $cobranca['cliente_telefone'];
                                                    echo "(" . substr($telefone, 0, 2) . ") " . 
                                                         substr($telefone, 2, 5) . "-" . 
                                                         substr($telefone, 7);
                                                ?>
                                            </small>
                                        </td>
                                        <td>#<?php echo $cobranca['numero_parcela']; ?></td>
                                        <td>
                                            <?php if ($cobranca['status'] === 'parcial'): ?>
                                                <span class="valor-pendente">
                                                    R$ <?php echo number_format($cobranca['valor_pendente'], 2, ',', '.'); ?>
                                                </span><br>
                                                <small class="valor-pago">
                                                    Pago: R$ <?php echo number_format($cobranca['valor_pago'], 2, ',', '.'); ?>
                                                </small>
                                            <?php else: ?>
                                                R$ <?php echo number_format($cobranca['valor_atual'], 2, ',', '.'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($cobranca['vencimento'])); ?>
                                            <?php if ($cobranca['dias_atraso'] > 0): ?>
                                                <br>
                                                <small class="dias-atraso">
                                                    <?php echo $cobranca['dias_atraso']; ?> dia(s) em atraso
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-<?php echo $cobranca['status']; ?>">
                                                <?php 
                                                    echo ucfirst($cobranca['status']); 
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="registrar_pagamento.php?emprestimo=<?php echo $cobranca['emprestimo_id']; ?>&parcela=<?php echo $cobranca['numero_parcela']; ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="bi bi-cash"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-primary btn-sm"
                                                        onclick="enviarMensagemIndividual(<?php echo $cobranca['emprestimo_id']; ?>, <?php echo $cobranca['numero_parcela']; ?>, '<?php echo $cobranca['cliente_telefone']; ?>')">
                                                    <i class="bi bi-whatsapp"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Mensagem -->
    <div class="modal fade" id="modalMensagem" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar Mensagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select class="form-select" id="templateMensagem"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Preview</label>
                        <div class="whatsapp-preview border p-3 bg-light">
                            <div class="whatsapp-message" id="previewMensagem"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnEnviarMensagem">Enviar Mensagem</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Função para toggle do debug
        function toggleDebug() {
            document.querySelectorAll('.debug-json').forEach(el => {
                el.style.display = el.style.display === 'none' ? 'block' : 'none';
            });
        }

        // Função para carregar templates do localStorage
        function carregarTemplates() {
            return JSON.parse(localStorage.getItem('templatesMensagens') || '{}');
        }

        // Função para atualizar o select de templates
        function atualizarSelectTemplates() {
            const templates = carregarTemplates();
            const select = document.getElementById('templateMensagem');
            select.innerHTML = '<option value="">Selecione um template</option>';
            
            Object.keys(templates).forEach(key => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = templates[key].nome;
                select.appendChild(option);
            });
        }

        // Função para gerar mensagem baseada no template
        function gerarMensagem(cobranca, template) {
            if (!template || !template.mensagem) return '';

            let mensagem = template.mensagem;
            const hoje = new Date();
            const vencimento = new Date(cobranca.vencimento);
            
            // Substitui as variáveis do template
            mensagem = mensagem
                .replace(/{nome_cliente}/g, cobranca.cliente_nome)
                .replace(/{valor_parcela}/g, cobranca.valor_pendente.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }))
                .replace(/{data_vencimento}/g, vencimento.toLocaleDateString('pt-BR'))
                .replace(/{numero_parcela}/g, cobranca.numero_parcela)
                .replace(/{total_parcelas}/g, 12) // Ajuste conforme necessário
                .replace(/{atraso}/g, cobranca.dias_atraso > 0 ? `(${cobranca.dias_atraso} dias em atraso)` : '');

            return mensagem;
        }

        // Função para enviar para todos os atrasados
        function enviarParaAtrasados() {
            const cobrancasAtrasadas = [];
            document.querySelectorAll('tr[data-status="atrasado"]').forEach(row => {
                const checkbox = row.querySelector('.selecionar-cobranca');
                cobrancasAtrasadas.push({
                    emprestimo_id: checkbox.dataset.emprestimo,
                    numero_parcela: checkbox.dataset.parcela,
                    telefone: checkbox.dataset.telefone,
                    cliente_nome: row.querySelector('td:nth-child(2)').textContent.trim().split('\n')[0],
                    valor_pendente: parseFloat(row.querySelector('td:nth-child(4)').textContent.replace('R$ ', '').replace('.', '').replace(',', '.')),
                    vencimento: row.querySelector('td:nth-child(5)').textContent.split('\n')[0],
                    dias_atraso: parseInt(row.querySelector('.dias-atraso')?.textContent || '0')
                });
            });

            if (cobrancasAtrasadas.length === 0) {
                alert('Não há cobranças atrasadas no momento.');
                return;
            }

            const modal = new bootstrap.Modal(document.getElementById('modalMensagem'));
            modal.show();

            atualizarSelectTemplates();

            document.getElementById('templateMensagem').addEventListener('change', function() {
                atualizarPreviewAtrasados(cobrancasAtrasadas);
            });

            document.getElementById('btnEnviarMensagem').onclick = function() {
                const templates = carregarTemplates();
                const templateSelecionado = templates[document.getElementById('templateMensagem').value];
                
                cobrancasAtrasadas.forEach(cobranca => {
                    const mensagem = gerarMensagem(cobranca, templateSelecionado);
                    const url = `https://wa.me/55${cobranca.telefone}?text=${encodeURIComponent(mensagem)}`;
                    window.open(url, '_blank');
                });
                modal.hide();
            };

            atualizarPreviewAtrasados(cobrancasAtrasadas);
        }

        // Função para atualizar preview de cobranças atrasadas
        function atualizarPreviewAtrasados(cobrancasAtrasadas) {
            let previewText = '';
            if (cobrancasAtrasadas.length === 0) {
                previewText = 'Não há cobranças atrasadas para enviar.';
            } else {
                const templates = carregarTemplates();
                const templateSelecionado = templates[document.getElementById('templateMensagem').value];
                
                if (cobrancasAtrasadas.length > 1) {
                    previewText = `${cobrancasAtrasadas.length} cobranças atrasadas serão enviadas.\n\n`;
                    previewText += `Exemplo para ${cobrancasAtrasadas[0].cliente_nome}:\n\n`;
                }
                
                if (templateSelecionado) {
                    previewText += gerarMensagem(cobrancasAtrasadas[0], templateSelecionado);
                }
            }
            
            document.getElementById('previewMensagem').innerHTML = previewText.replace(/\n/g, '<br>');
        }

        // Função para enviar cobranças selecionadas
        function enviarCobrancasSelecionadas() {
            const cobrancasSelecionadas = [];
            document.querySelectorAll('.selecionar-cobranca:checked').forEach(checkbox => {
                const row = checkbox.closest('tr');
                cobrancasSelecionadas.push({
                    emprestimo_id: checkbox.dataset.emprestimo,
                    numero_parcela: checkbox.dataset.parcela,
                    telefone: checkbox.dataset.telefone,
                    cliente_nome: row.querySelector('td:nth-child(2)').textContent.trim().split('\n')[0],
                    valor_pendente: parseFloat(row.querySelector('td:nth-child(4)').textContent.replace('R$ ', '').replace('.', '').replace(',', '.')),
                    vencimento: row.querySelector('td:nth-child(5)').textContent.split('\n')[0],
                    dias_atraso: parseInt(row.querySelector('.dias-atraso')?.textContent || '0')
                });
            });

            if (cobrancasSelecionadas.length === 0) {
                alert('Selecione pelo menos uma cobrança.');
                return;
            }

            const modal = new bootstrap.Modal(document.getElementById('modalMensagem'));
            modal.show();

            atualizarSelectTemplates();

            document.getElementById('templateMensagem').addEventListener('change', function() {
                atualizarPreviewSelecionadas(cobrancasSelecionadas);
            });

            document.getElementById('btnEnviarMensagem').onclick = function() {
                const templates = carregarTemplates();
                const templateSelecionado = templates[document.getElementById('templateMensagem').value];
                
                cobrancasSelecionadas.forEach(cobranca => {
                    const mensagem = gerarMensagem(cobranca, templateSelecionado);
                    const url = `https://wa.me/55${cobranca.telefone}?text=${encodeURIComponent(mensagem)}`;
                    window.open(url, '_blank');
                });
                modal.hide();
            };

            atualizarPreviewSelecionadas(cobrancasSelecionadas);
        }

        // Função para atualizar preview de cobranças selecionadas
        function atualizarPreviewSelecionadas(cobrancasSelecionadas) {
            let previewText = '';
            if (cobrancasSelecionadas.length === 0) {
                previewText = 'Selecione cobranças para enviar.';
            } else {
                const templates = carregarTemplates();
                const templateSelecionado = templates[document.getElementById('templateMensagem').value];
                
                if (cobrancasSelecionadas.length > 1) {
                    previewText = `${cobrancasSelecionadas.length} cobranças selecionadas serão enviadas.\n\n`;
                    previewText += `Exemplo para ${cobrancasSelecionadas[0].cliente_nome}:\n\n`;
                }
                
                if (templateSelecionado) {
                    previewText += gerarMensagem(cobrancasSelecionadas[0], templateSelecionado);
                }
            }
            
            document.getElementById('previewMensagem').innerHTML = previewText.replace(/\n/g, '<br>');
        }

        // Função para enviar mensagem individual
        function enviarMensagemIndividual(emprestimoId, numeroParcela, telefone) {
            const row = document.querySelector(`[data-emprestimo="${emprestimoId}"][data-parcela="${numeroParcela}"]`).closest('tr');
            const cobranca = {
                emprestimo_id: emprestimoId,
                numero_parcela: numeroParcela,
                telefone: telefone,
                cliente_nome: row.querySelector('td:nth-child(2)').textContent.trim().split('\n')[0],
                valor_pendente: parseFloat(row.querySelector('td:nth-child(4)').textContent.replace('R$ ', '').replace('.', '').replace(',', '.')),
                vencimento: row.querySelector('td:nth-child(5)').textContent.split('\n')[0],
                dias_atraso: parseInt(row.querySelector('.dias-atraso')?.textContent || '0')
            };

            const modal = new bootstrap.Modal(document.getElementById('modalMensagem'));
            modal.show();

            atualizarSelectTemplates();

            document.getElementById('templateMensagem').addEventListener('change', function() {
                const templates = carregarTemplates();
                const templateSelecionado = templates[this.value];
                const mensagem = templateSelecionado ? gerarMensagem(cobranca, templateSelecionado) : '';
                document.getElementById('previewMensagem').innerHTML = mensagem.replace(/\n/g, '<br>');
            });

            document.getElementById('btnEnviarMensagem').onclick = function() {
                const templates = carregarTemplates();
                const templateSelecionado = templates[document.getElementById('templateMensagem').value];
                const mensagem = gerarMensagem(cobranca, templateSelecionado);
                const url = `https://wa.me/55${telefone}?text=${encodeURIComponent(mensagem)}`;
                window.open(url, '_blank');
                modal.hide();
            };
        }

        // Evento para selecionar/desselecionar todos
        document.getElementById('selecionarTodos').addEventListener('change', function() {
            document.querySelectorAll('.selecionar-cobranca').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Inicialização do DataTable
        $(document).ready(function() {
            $('#tabelaCobrancas').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                },
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
                order: [[4, 'asc']], // Ordena por data de vencimento
                columnDefs: [
                    { orderable: false, targets: [0, 6] } // Desativa ordenação nas colunas de checkbox e ações
                ]
            });
        });
    </script>
</body>
</html> 