<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/queries.php';

// Verifica se veio ID do cliente (pode vir por POST ou GET)
$cliente_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$cliente_id) {
    $cliente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
}

// Busca clientes para o select
$clientes = buscarTodosClientes($conn);

// Buscar usuários investidores
$sql_investidores = "SELECT id, nome FROM usuarios WHERE tipo = 'investidor' OR id = 1 ORDER BY nome";
$result_investidores = $conn->query($sql_investidores);
$investidores = [];

if ($result_investidores && $result_investidores->num_rows > 0) {
    while ($row = $result_investidores->fetch_assoc()) {
        // Marcar o administrador (id=1)
        if ($row['id'] == 1) {
            $row['nome'] = $row['nome'] . ' (Administrador)';
        }
        $investidores[] = $row;
    }
}

// Buscar investidores que têm contas ativas
$sql_contas = "SELECT DISTINCT usuario_id FROM contas WHERE status = 'ativo'";
$result_contas = $conn->query($sql_contas);
$investidores_com_conta = [];

if ($result_contas && $result_contas->num_rows > 0) {
    while ($row = $result_contas->fetch_assoc()) {
        $investidores_com_conta[] = $row['usuario_id'];
    }
}

// Se não houver resultados, verificar se a coluna 'tipo' existe na tabela 'usuarios'
if (empty($investidores)) {
    $check_column = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'tipo'");
    $tipo_column_exists = ($check_column && $check_column->num_rows > 0);
    
    if (!$tipo_column_exists) {
        // Se a coluna não existir, buscar todos os usuários
        $sql_usuarios = "SELECT id, nome FROM usuarios ORDER BY nome";
        $result_usuarios = $conn->query($sql_usuarios);
        
        if ($result_usuarios && $result_usuarios->num_rows > 0) {
            while ($row = $result_usuarios->fetch_assoc()) {
                // Marcar o administrador (id=1)
                if ($row['id'] == 1) {
                    $row['nome'] = $row['nome'] . ' (Administrador)';
                }
                $investidores[] = $row;
            }
        }
    }
}

// Se tiver um cliente_id, busca seus dados
$cliente_selecionado = null;
if ($cliente_id) {
    $stmt = $conn->prepare("SELECT c.*, u.nome as investidor_nome FROM clientes c 
                            LEFT JOIN usuarios u ON c.indicacao = u.id 
                            WHERE c.id = ?");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $cliente_selecionado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!-- Incluir Select2 para estilização do select -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Apenas ajustes mínimos para melhor integração com Bootstrap */
.select2-container .select2-selection--single {
    height: calc(1.5em + 0.75rem + 2px);
    padding: 0.375rem 0.75rem;
    border: 1px solid #ced4dd;
    border-radius: 0.25rem;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: calc(1.5em + 0.75rem);
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 1.5;
    color: #212529;
}

/* Ajustes para o Select2 dentro do input-group */
.input-group > .select2-container {
    position: relative;
    flex: 1 1 auto;
    width: 1% !important;
}

.input-group > .select2-container .select2-selection--single {
    height: 100%;
    line-height: calc(1.5em + 0.75rem);
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

/* Estilizar a opção de novo cliente */
.select2-results__option[aria-selected=true][value="novo_cliente"],
.select2-results__option[data-select2-id="novo_cliente"] {
    font-weight: bold;
    border-top: 1px solid #dee2e6;
    margin-top: 5px;
    padding-top: 10px;
    color: #0d6efd;
}

.select2-container--default .select2-results__option--highlighted[aria-selected="true"][value="novo_cliente"],
.select2-container--default .select2-results__option--highlighted[data-select2-id="novo_cliente"] {
    background-color: #f8f9fa;
    color: #0d6efd;
}

/* Alerta de sucesso para cadastro de cliente */
#alertaSucesso {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
}
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Novo Empréstimo</h4>
                </div>
                <div class="card-body">
                    <form id="formEmprestimo" action="salvar.php" method="POST">
                        <!-- Cliente -->
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="bi bi-person-fill"></i> Cliente
                            </h5>
                            <?php if ($cliente_selecionado): ?>
                                <div class="alert alert-info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Cliente Selecionado:</strong> <?= htmlspecialchars($cliente_selecionado['nome']) ?>
                                            <?php if (!empty($cliente_selecionado['cpf_cnpj'])): ?>
                                            <br>
                                            <small class="text-muted">CPF: <?= formatarCPF($cliente_selecionado['cpf_cnpj']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <a href="novo.php" class="btn btn-sm btn-outline-secondary">Alterar Cliente</a>
                                    </div>
                                </div>
                                <input type="hidden" name="cliente" value="<?= $cliente_selecionado['id'] ?>">
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="cliente" class="form-label">Selecione o Cliente:</label>
                                            <select class="form-select" id="cliente" name="cliente" required>
                                                <option value="">Selecione um cliente</option>
                                                <?php foreach ($clientes as $cliente): ?>
                                                    <option value="<?= $cliente['id'] ?>">
                                                        <?= htmlspecialchars($cliente['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="novo_cliente" class="text-primary">+ Cadastrar novo cliente</option>
                                            </select>
                                        </div>
                                    </div>
                            
                                    <!-- Investidor -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="investidor_id" class="form-label">Selecione o Investidor:</label>
                                            <select class="form-select" id="investidor_id" name="investidor_id" required>
                                                <option value="">Selecione um investidor</option>
                                                <?php foreach ($investidores as $investidor): 
                                                    $tem_conta = in_array($investidor['id'], $investidores_com_conta);
                                                ?>
                                                    <option value="<?= $investidor['id'] ?>" <?= !$tem_conta ? 'data-sem-conta="true"' : '' ?>>
                                                        <?= htmlspecialchars($investidor['nome']) ?><?= !$tem_conta ? ' (Sem conta)' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div id="aviso-investidor" class="text-danger mt-2" style="display: none;">
                                                <i class="bi bi-exclamation-triangle-fill"></i> Este investidor não possui uma conta ativa. 
                                                Por favor, solicite ao administrador que crie uma conta para este investidor antes de registrar empréstimos.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Configurações -->
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="bi bi-gear-fill"></i> Configurações
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tipo_cobranca" class="form-label">Tipo de Cobrança:</label>
                                        <select class="form-select" id="tipo_cobranca" name="tipo_cobranca" required>
                                            <option value="">Selecione</option>
                                            <option value="parcelada_comum" selected>Parcelada Comum</option>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" id="usar_tlc" name="usar_tlc" value="0">
                                <input type="hidden" id="tlc_valor" name="tlc_valor" value="0">
                            </div>
                        </div>

                        <!-- Valores -->
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="bi bi-cash"></i> Valores
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="capital" class="form-label">Capital (R$):</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-cash"></i></span>
                                            <input type="text" class="form-control" id="capital" name="capital" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="parcelas" class="form-label">Número de Parcelas:</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-123"></i></span>
                                            <input type="number" class="form-control" id="parcelas" name="parcelas" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="modo_calculo" class="form-label">Modo de Cálculo:</label>
                                        <select class="form-select" id="modo_calculo" name="modo_calculo" required>
                                            <option value="">Selecione</option>
                                            <option value="parcela" selected>Informar Valor da Parcela</option>
                                            <option value="taxa">Informar Taxa de Juros</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6" id="taxa_juros_container" style="display: none;">
                                    <div class="mb-3">
                                        <label for="juros" class="form-label">Taxa de Juros (%):</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-percent"></i></span>
                                            <input type="text" class="form-control" id="juros" name="juros" step="0.01">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6" id="valor_parcela_container">
                                    <div class="mb-3">
                                        <label for="valor_parcela" class="form-label">Valor da Parcela (R$):</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                            <input type="text" class="form-control" id="valor_parcela" name="valor_parcela" required>
                                            <button type="button" class="btn btn-outline-secondary" id="btn_arredondar" title="Arredondar para o próximo valor inteiro">
                                                <i class="bi bi-arrow-up-circle"></i> Arredondar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="periodo_pagamento" class="form-label">Período de Pagamento:</label>
                                        <select class="form-select" id="periodo_pagamento" name="periodo_pagamento" required>
                                            <option value="">Selecione</option>
                                            <option value="diario" selected>Diário</option>
                                            <option value="semanal">Semanal</option>
                                            <option value="quinzenal">Quinzenal</option>
                                            <option value="trimestral">Trimestral</option>
                                            <option value="mensal">Mensal</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumo do Cálculo -->
                        <div class="row mt-3" id="resumo_calculo_container" style="display: none;">
                            <div class="col-12">
                                <div class="alert alert-info mb-3">
                                    <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Resumo do Cálculo</h6>
                                    <div id="resumo_calculo"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Data e Dias -->
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="bi bi-calendar-event"></i> Data e Dias
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="data" class="form-label">Data Inicial:</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-calendar-date"></i></span>
                                            <input type="date" class="form-control" id="data" name="data" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Dias para Não Gerar Parcelas:</label>
                                        <div class="d-flex gap-2 mb-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selecionarTodosDias()">
                                                <i class="bi bi-check-all"></i> Todos
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limparSelecaoDias()">
                                                <i class="bi bi-x-lg"></i> Limpar
                                            </button>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" id="domingo" class="form-check-input" value="0" name="dias_semana[]" checked>
                                                    <label for="domingo" class="form-check-label">Domingo</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" id="segunda" class="form-check-input" value="1" name="dias_semana[]">
                                                    <label for="segunda" class="form-check-label">Segunda</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" id="terca" class="form-check-input" value="2" name="dias_semana[]">
                                                    <label for="terca" class="form-check-label">Terça</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" id="quarta" class="form-check-input" value="3" name="dias_semana[]">
                                                    <label for="quarta" class="form-check-label">Quarta</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" id="quinta" class="form-check-input" value="4" name="dias_semana[]">
                                                    <label for="quinta" class="form-check-label">Quinta</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" id="sexta" class="form-check-input" value="5" name="dias_semana[]">
                                                    <label for="sexta" class="form-check-label">Sexta</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" id="sabado" class="form-check-input" value="6" name="dias_semana[]">
                                                    <label for="sabado" class="form-check-label">Sábado</label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" id="feriados" class="form-check-input" value="feriados" name="dias_semana[]" checked>
                                                    <label for="feriados" class="form-check-label">Feriados</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botão de Submit -->
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='index.php'">
                                <i class="bi bi-arrow-left"></i> Voltar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Salvar Empréstimo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Função para formatar moeda
function formatarMoeda(input) {
    // Remove tudo que não é número
    let valor = input.value.replace(/[^\d]/g, '');
    // Converte para número com 2 casas decimais
    valor = (parseFloat(valor) / 100).toFixed(2);
    // Formata com vírgula
    input.value = valor.replace('.', ',');
}

// Função para formatar porcentagem
function formatarPorcentagem(input) {
    // Remove tudo que não é número ou vírgula
    let valor = input.value.replace(/[^\d,]/g, '');
    if (valor) {
        // Se tiver mais de uma vírgula, mantém só a primeira
        valor = valor.replace(/,/g, function(match, offset, string) {
            return offset === string.indexOf(',') ? match : '';
        });
        // Converte vírgula para ponto, formata com 2 casas e volta para vírgula
        valor = parseFloat(valor.replace(',', '.')).toFixed(2).replace('.', ',');
        input.value = valor;
    }
}

// Função para selecionar todos os dias
function selecionarTodosDias() {
    document.querySelectorAll('input[name="dias_semana[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

// Função para limpar seleção de dias
function limparSelecaoDias() {
    document.querySelectorAll('input[name="dias_semana[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Adiciona formatação aos campos de moeda
document.getElementById('capital').addEventListener('input', function() {
    formatarMoeda(this);
});
document.getElementById('valor_parcela').addEventListener('input', function() {
    formatarMoeda(this);
});
document.getElementById('tlc_valor').addEventListener('input', function() {
    formatarMoeda(this);
});

// Adiciona formatação ao campo de juros
document.getElementById('juros').addEventListener('input', function() {
    formatarPorcentagem(this);
});

// Função para verificar se todos os campos obrigatórios estão preenchidos
function verificarFormulario() {
    const form = document.getElementById('formEmprestimo');
    const btnGerar = document.getElementById('btnGerar');
    
    // Lista de campos obrigatórios
    const camposObrigatorios = [
        'cliente',
        'tipo_cobranca',
        'capital',
        'parcelas',
        'modo_calculo',
        'periodo_pagamento'
    ];

    // Verifica se os campos básicos estão preenchidos
    const camposBasicosPreenchidos = camposObrigatorios.every(campo => {
        const elemento = form.querySelector(`[name="${campo}"]`);
        return elemento && elemento.value.trim() !== '';
    });

    // Verifica o modo de cálculo e seus campos relacionados
    const modoCalculo = form.querySelector('[name="modo_calculo"]').value;
    let campoCalculoPreenchido = false;

    if (modoCalculo === 'parcela') {
        campoCalculoPreenchido = form.querySelector('[name="valor_parcela"]').value.trim() !== '';
    } else if (modoCalculo === 'taxa') {
        campoCalculoPreenchido = form.querySelector('[name="juros"]').value.trim() !== '';
    }

    // Habilita ou desabilita o botão com base nas validações
    btnGerar.disabled = !(camposBasicosPreenchidos && campoCalculoPreenchido);
}

// Adiciona listeners para todos os campos relevantes
document.querySelectorAll('#formEmprestimo [name]').forEach(element => {
    element.addEventListener('change', verificarFormulario);
    element.addEventListener('input', verificarFormulario);
});

// Verifica o formulário inicialmente
document.addEventListener('DOMContentLoaded', verificarFormulario);

// Adiciona listener específico para o modo de cálculo
document.getElementById('modo_calculo').addEventListener('change', function() {
    const valorParcelaContainer = document.getElementById('valor_parcela_container');
    const taxaJurosContainer = document.getElementById('taxa_juros_container');
    const valorParcelaInput = document.getElementById('valor_parcela');
    const jurosInput = document.getElementById('juros');
    
    if (this.value === 'parcela') {
        valorParcelaContainer.style.display = 'block';
        taxaJurosContainer.style.display = 'none';
        jurosInput.value = '';
        jurosInput.removeAttribute('required');
        valorParcelaInput.setAttribute('required', '');
    } else if (this.value === 'taxa') {
        valorParcelaContainer.style.display = 'none';
        taxaJurosContainer.style.display = 'block';
        valorParcelaInput.value = '';
        valorParcelaInput.removeAttribute('required');
        jurosInput.setAttribute('required', '');
    }
    
    verificarFormulario();
});

// Inicializar corretamente o modo de cálculo no carregamento da página
document.addEventListener('DOMContentLoaded', function() {
    const modoCalculo = document.getElementById('modo_calculo');
    // Dispara o evento change para configurar corretamente os campos baseado na seleção inicial
    const event = new Event('change');
    modoCalculo.dispatchEvent(event);
});

// Verifica quando o formulário é enviado
document.getElementById('formEmprestimo').addEventListener('submit', function(e) {
    // Primeiro validar se o investidor tem conta
    const investidorSelect = document.getElementById('investidor_id');
    const option = investidorSelect.options[investidorSelect.selectedIndex];
    
    if (option && option.getAttribute('data-sem-conta') === 'true') {
        e.preventDefault();
        alert('Não é possível registrar o empréstimo. O investidor selecionado não possui uma conta ativa.');
        investidorSelect.focus();
        return false;
    }
    
    // Se passar pela validação da conta, preparar os valores para envio
    e.preventDefault();
    
    // Converte campos de moeda
    const camposMoeda = ['capital', 'valor_parcela', 'tlc_valor'];
    camposMoeda.forEach(campo => {
        const input = document.getElementById(campo);
        if (input && input.value) {
            // Remove qualquer caractere que não seja número ou vírgula
            let valor = input.value.replace(/[^\d,]/g, '').replace(',', '.');
            // Garante que é um número válido
            if (!isNaN(valor)) {
                input.value = valor;
            }
        }
    });

    // Converte campo de juros
    const campoJuros = document.getElementById('juros');
    if (campoJuros && campoJuros.value) {
        let valor = campoJuros.value.replace(/[^\d,]/g, '').replace(',', '.');
        if (!isNaN(valor)) {
            campoJuros.value = valor;
        }
    }

    // Envia o formulário
    document.getElementById('formEmprestimo').submit();
});

$(document).ready(function() {
    // Inicializa o Select2 para o select de clientes
    $('#cliente').select2({
        placeholder: 'Selecione um cliente',
        width: '100%',
        templateResult: function(data) {
            if (data.id === 'novo_cliente') {
                return $('<span><i class="bi bi-person-plus-fill me-2"></i>' + data.text + '</span>');
            }
            return data.text;
        }
    });
    
    // Detectar quando a opção "Cadastrar novo cliente" é selecionada
    $('#cliente').on('change', function() {
        const selectedValue = $(this).val();
        
        if (selectedValue === 'novo_cliente') {
            // Abre a modal
            $('#modalNovoCliente').modal('show');
            
            // Restaura a seleção anterior após um pequeno delay
            setTimeout(function() {
                $('#cliente').val('').trigger('change');
            }, 100);
        }
    });
    
    // Corrigir o comportamento do Select2 quando a modal é aberta
    $('#modalNovoCliente').on('shown.bs.modal', function() {
        // Foca no campo nome quando a modal é aberta
        $('#formNovoCliente input[name="nome"]').focus();
    });
    
    // Quando a modal de novo cliente é fechada, reseta o formulário
    $('#modalNovoCliente').on('hidden.bs.modal', function() {
        $('#formNovoCliente')[0].reset();
        $('#erroNovoCliente').addClass('d-none');
    });
    
    // Submissão do formulário de novo cliente via AJAX
    $('#formNovoCliente').on('submit', function(e) {
        e.preventDefault();
        
        // Mostrar indicador de carregamento
        $('#btnSalvarCliente').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvando...');
        $('#btnSalvarCliente').prop('disabled', true);
        
        $.ajax({
            url: '../api/salvar_cliente.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Adiciona o novo cliente ao select
                    var novoCliente = new Option(response.cliente.nome, response.cliente.id, true, true);
                    $('#cliente').append(novoCliente).trigger('change');
                    
                    // Fecha a modal
                    $('#modalNovoCliente').modal('hide');
                    
                    // Mostra mensagem de sucesso
                    $('#alertaSucesso').html('<strong>Sucesso!</strong> Cliente cadastrado e selecionado.').removeClass('d-none');
                    setTimeout(function() {
                        $('#alertaSucesso').addClass('d-none');
                    }, 5000);
                } else {
                    // Mostra erro
                    $('#erroNovoCliente').html(response.message).removeClass('d-none');
                }
            },
            error: function(xhr, status, error) {
                // Mostra erro
                $('#erroNovoCliente').html('Erro ao cadastrar cliente: ' + error).removeClass('d-none');
            },
            complete: function() {
                // Restaura o botão de salvar
                $('#btnSalvarCliente').html('Salvar');
                $('#btnSalvarCliente').prop('disabled', false);
            }
        });
    });
});

// Verificar se o investidor selecionado tem conta ativa
document.getElementById('investidor_id').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const avisoInvestidor = document.getElementById('aviso-investidor');
    const semConta = option.getAttribute('data-sem-conta') === 'true';
    
    if (semConta) {
        avisoInvestidor.style.display = 'block';
        this.classList.add('is-invalid');
    } else {
        avisoInvestidor.style.display = 'none';
        this.classList.remove('is-invalid');
    }
});
</script>

<!-- Modal para cadastro rápido de cliente -->
<div class="modal fade" id="modalNovoCliente" tabindex="-1" aria-labelledby="modalNovoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoClienteLabel"><i class="bi bi-person-plus-fill"></i> Cadastrar Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="erroNovoCliente"></div>
                
                <form id="formNovoCliente" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" placeholder="Nome completo" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="exemplo@dominio.com">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo Pessoa</label>
                            <select name="tipo_pessoa" class="form-select">
                                <option value="1" selected>Física</option>
                                <option value="2">Jurídica</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CPF / CNPJ</label>
                            <input type="text" name="cpf_cnpj" class="form-control" placeholder="000.000.000-00">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Chave Pix</label>
                            <input type="text" name="chave_pix" class="form-control" placeholder="CPF, telefone, email ou aleatória">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Ativo" selected>Ativo</option>
                                <option value="Inativo">Inativo</option>
                                <option value="Alerta">Alerta</option>
                                <option value="Atenção">Atenção</option>
                            </select>
                        </div>
                    </div>
                    
                    <input type="hidden" name="ajax" value="1">
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <input type="hidden" name="investidor_id" value="<?= $_SESSION['usuario_id'] ?>">
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formNovoCliente" class="btn btn-primary" id="btnSalvarCliente">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Alerta de sucesso -->
<div class="alert alert-success alert-dismissible fade show d-none" id="alertaSucesso" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

<?php
function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}
?>
