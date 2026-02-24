/**
 * functions.js - Funções globais para o sistema de empréstimos
 */

// Controle do tipo de cobrança no formulário de empréstimos
function controlarTipoCobranca() {
    const tipoCobranca = $('#tipo_cobranca').val();
    
    if (tipoCobranca === 'reparcelada_com_juros') {
        // Configura para Reparcelada com Juros
        $('#periodo_pagamento').val('mensal');
        $('#periodo_pagamento').prop('disabled', true);
        
        $('#parcelas').val(1);
        $('#parcelas').prop('disabled', true);
        
        // Configura juros padrão de 30% para Reparcelada
        $('#modo_calculo').val('taxa');
        $('#juros').val('30.00');
        
        // Mostra o container de taxa e esconde o de parcela
        $('#taxa_juros_container').show();
        $('#valor_parcela_container').hide();
        
        // Calcula com a taxa de 30%
        calcularPorTaxaJuros();
    } else {
        // Para outros tipos, habilita os campos
        $('#periodo_pagamento').prop('disabled', false);
        $('#parcelas').prop('disabled', false);
    }
    
    // Recalcula o valor das parcelas se o tipo for parcelada comum
    calcularValorParcela();
}

// Funções para manipulação dos dias da semana
function selecionarTodosDias() {
    // Marca todos os checkboxes
    $('.form-check-input[name="dias_semana[]"]').prop('checked', true).trigger('change');
    
    console.log("Todos os dias foram marcados");
    
    // Dispara o evento de change para atualizar a data (já está sendo disparado pelo trigger acima)
}

function limparSelecaoDias() {
    // Desmarca todos os checkboxes
    $('.form-check-input[name="dias_semana[]"]').prop('checked', false);
    
    // Manter feriados sempre marcado
    $('#feriados').prop('checked', true);
    
    // Dispara o evento de change para atualizar a data
    $('.form-check-input[name="dias_semana[]"]:first').trigger('change');
    
    console.log("Seleção de dias limpa, exceto feriados");
}

// Configurar data inicial para o próximo dia válido
function configurarDataInicial() {
    try {
        // Obtém a data atual e zera as horas para comparar apenas datas
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);
        
        console.log("Data atual:", hoje.toISOString().split('T')[0]);
        
        // Função para verificar se um dia é válido (não está marcado para não gerar parcelas)
        function isDiaValido(data) {
            const diaSemana = data.getDay(); // 0=domingo, 1=segunda, ..., 6=sábado
            
            // Verifica se o dia da semana está marcado para não gerar parcelas
            const diaCheckbox = $(`input[type="checkbox"][value="${diaSemana}"]`);
            
            return !(diaCheckbox.length > 0 && diaCheckbox.prop('checked'));
        }
        
        // Lógica para encontrar a próxima data válida a partir de amanhã
        let proximoDia = new Date(hoje);
        proximoDia.setDate(proximoDia.getDate() + 1); // Começa a partir de amanhã
        
        console.log("Calculando a partir de:", proximoDia.toISOString().split('T')[0]);
        
        // Procura a próxima data válida (até 30 dias no futuro)
        let tentativas = 0;
        const MAX_TENTATIVAS = 30;
        
        while (!isDiaValido(proximoDia) && tentativas < MAX_TENTATIVAS) {
            proximoDia.setDate(proximoDia.getDate() + 1);
            tentativas++;
            console.log("Tentativa " + tentativas + ": " + proximoDia.toISOString().split('T')[0] + " - Válido: " + isDiaValido(proximoDia));
        }
        
        // Formata a data encontrada como YYYY-MM-DD
        const ano = proximoDia.getFullYear();
        const mes = String(proximoDia.getMonth() + 1).padStart(2, '0');
        const dia = String(proximoDia.getDate()).padStart(2, '0');
        const dataFormatada = `${ano}-${mes}-${dia}`;
        
        console.log("Data inicial definida para:", dataFormatada);
        
        // Define a data no campo
        $('#data').val(dataFormatada);
    } catch (error) {
        console.error("Erro ao configurar data inicial:", error);
    }
}

// Função para atualizar a data inicial quando os dias de exclusão são alterados
function atualizarDataInicial() {
    configurarDataInicial();
}

// Manipulação do campo TLC
function controlarCamposTLC() {
    $('#tlc_valor_container').toggle($('#usar_tlc').val() === '1');
}

// Função auxiliar para limpar e converter valores monetários
function obterValorNumerico(valor) {
    if (!valor) return 0;
    
    // Remove prefixo/sufixo e converte para formato numérico
    let valorLimpo = valor.toString().trim();
    
    // Se o valor tem formato brasileiro (1.234,56), converte para formato aceito pelo parseFloat
    if (valorLimpo.indexOf(',') > -1) {
        // Remove todos os caracteres não numéricos, exceto vírgula e ponto
        valorLimpo = valorLimpo.replace(/[^\d,.]/g, '');
        
        // Remove todos os pontos (separadores de milhar)
        valorLimpo = valorLimpo.replace(/\./g, '');
        
        // Substitui vírgula por ponto (para o parseFloat entender)
        valorLimpo = valorLimpo.replace(',', '.');
    } else {
        // Para valores que já estão no formato americano ou sem formatação
        valorLimpo = valorLimpo.replace(/[^\d.]/g, '');
    }
    
    // Converte para número e garante que é um valor válido
    const numero = parseFloat(valorLimpo);
    return isNaN(numero) ? 0 : numero;
}

// Função para atualizar campo com valor monetário formatado
function atualizarCampoMonetario(seletor, valor) {
    // Formata o valor com 2 casas decimais
    const valorFormatado = valor.toFixed(2);
    
    // Atualiza o campo com o valor formatado
    $(seletor).val(valorFormatado);
    
    // Reaplica a máscara para formatar corretamente
    $(seletor).maskMoney('mask');
    
    console.log(`Campo ${seletor} atualizado para: ${valorFormatado} -> ${$(seletor).val()}`);
}

// Manipulação do modo de cálculo
function controlarModoCalculo() {
    const modoCalculo = $('#modo_calculo').val();
    
    // Esconde ambos os containers primeiro
    $('#taxa_juros_container').hide();
    $('#valor_parcela_container').hide();
    
    // Se nenhum modo foi selecionado, não mostra nenhum campo
    if (!modoCalculo) {
        return;
    }
    
    // Mostra apenas o container apropriado
    if (modoCalculo === 'taxa') {
        $('#taxa_juros_container').show();
        
        // Ao mudar para modo "informar taxa de juros", preencher com valor calculado
        const capital = obterValorNumerico($('#capital').val());
        const parcelas = parseInt($('#parcelas').val()) || 1;
        const valorParcela = obterValorNumerico($('#valor_parcela').val());
        
        // Só preenche a taxa automaticamente se os três valores estiverem preenchidos
        if (capital > 0 && parcelas > 0 && valorParcela > 0) {
            // Calcular a taxa de juros baseado no valor da parcela
            const valorTotal = valorParcela * parcelas;
            const valorJuros = valorTotal - capital;
            const taxaJuros = (valorJuros / capital) * 100;
            
            // Preencher o campo de taxa de juros
            $('#juros').val(taxaJuros.toFixed(2));
            
            // Manter o resumo visível
            const infoCalculo = {
                capital: capital,
                parcelas: parcelas,
                taxaJuros: taxaJuros,
                valorJuros: valorJuros,
                valorTotal: valorTotal,
                valorParcela: valorParcela
            };
            mostrarResumoCalculo(infoCalculo);
        } else {
            // Se não tiver dados suficientes, usar o valor padrão (igual ao número de parcelas)
            if (parcelas > 0) {
                $('#juros').val(parcelas.toFixed(2));
            }
        }
    } else if (modoCalculo === 'parcela') {
        $('#valor_parcela_container').show();
        
        // Se mudar para modo informar parcela, calcular automaticamente
        calcularValorParcela();
    }
}

// Calcula o valor da parcela automaticamente para tipo "parcelada comum"
function calcularValorParcela() {
    const tipoCobranca = $('#tipo_cobranca').val();
    const modoCalculo = $('#modo_calculo').val();
    
    // Só calcula quando for parcelada comum e modo informar parcela e os dois estiverem selecionados
    if (tipoCobranca === 'parcelada_comum' && modoCalculo === 'parcela') {
        const capital = obterValorNumerico($('#capital').val());
        const parcelas = parseInt($('#parcelas').val()) || 1;
        
        if (capital > 0 && parcelas > 0) {
            console.log("Calculando valor da parcela: Capital =", capital, "Parcelas =", parcelas);
            
            // Cálculo considerando que a taxa de juros é igual à quantidade de parcelas
            const taxaJuros = parcelas; // Ex: 10 parcelas = 10% de juros
            const valorJuros = capital * (taxaJuros / 100);
            const valorTotal = capital + valorJuros;
            const valorParcela = valorTotal / parcelas;
            
            console.log("Resultado do cálculo: Valor parcela =", valorParcela);
            
            // Atualiza o campo com o valor calculado formatado
            atualizarCampoMonetario('#valor_parcela', valorParcela);
            
            // Exibir resumo do cálculo na interface
            mostrarResumoCalculo({
                capital: capital,
                parcelas: parcelas,
                taxaJuros: taxaJuros,
                valorJuros: valorJuros,
                valorTotal: valorTotal,
                valorParcela: valorParcela
            });
        }
    }
}

// Calcula o valor total baseado no valor da parcela editado pelo usuário
function atualizarValorTotalPorParcela() {
    const tipoCobranca = $('#tipo_cobranca').val();
    const modoCalculo = $('#modo_calculo').val();
    
    if (tipoCobranca === 'parcelada_comum' && modoCalculo === 'parcela') {
        const capital = obterValorNumerico($('#capital').val());
        const parcelas = parseInt($('#parcelas').val()) || 1;
        const valorParcelaEditado = obterValorNumerico($('#valor_parcela').val());
        
        if (capital > 0 && parcelas > 0 && valorParcelaEditado > 0) {
            // Calcula os valores atualizados com base no valor da parcela editado
            const valorTotalEditado = valorParcelaEditado * parcelas;
            const valorJurosEditado = valorTotalEditado - capital;
            const taxaJurosEditada = (valorJurosEditado / capital) * 100;
            
            // Exibir resumo do cálculo atualizado na interface
            mostrarResumoCalculo({
                capital: capital,
                parcelas: parcelas,
                taxaJuros: taxaJurosEditada,
                valorJuros: valorJurosEditado,
                valorTotal: valorTotalEditado,
                valorParcela: valorParcelaEditado,
                editado: true
            });
        }
    }
}

// Função para exibir o resumo do cálculo na interface
function mostrarResumoCalculo(dados) {
    if (!dados) return;
    
    // Formatação dos valores para exibição
    const formatarMoeda = (valor) => {
        // Garante que é um número antes de formatar
        const numero = typeof valor === 'number' ? valor : parseFloat(valor) || 0;
        return `R$ ${numero.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
    };
    
    // Log para debug
    console.log("Dados do resumo:", dados);
    
    let html = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>Capital:</strong> ${formatarMoeda(dados.capital)}</p>
                <p><strong>Juros:</strong> ${dados.taxaJuros.toFixed(2)}% (${formatarMoeda(dados.valorJuros)})</p>
            </div>
            <div class="col-md-6">
                <p><strong>Total:</strong> ${formatarMoeda(dados.valorTotal)}</p>
                <p><strong>Parcelas:</strong> ${dados.parcelas}x de ${formatarMoeda(dados.valorParcela)}</p>
            </div>
        </div>
    `;
    
    // Se o valor foi editado manualmente, mostrar essa informação
    if (dados.editado) {
        html += `
            <div class="alert alert-warning mt-2 mb-0 py-2 small">
                <i class="bi bi-exclamation-triangle"></i> 
                Valor da parcela foi ajustado manualmente. A taxa de juros efetiva é de ${dados.taxaJuros.toFixed(2)}%.
            </div>
        `;
    }
    
    // Atualiza o conteúdo e mostra o container
    $('#resumo_calculo').html(html);
    $('#resumo_calculo_container').show();
}

// Função para calcular o empréstimo
function calcularEmprestimo() {
    // Aqui será implementada a lógica de cálculo do empréstimo
    // Por enquanto apenas mostra o botão de gerar
    $('#btnGerar').show();
}

// Calcular baseado na taxa de juros informada
function calcularPorTaxaJuros() {
    const tipoCobranca = $('#tipo_cobranca').val();
    const modoCalculo = $('#modo_calculo').val();
    
    // Só calcula quando for modo informar taxa de juros
    if (modoCalculo === 'taxa') {
        const capital = obterValorNumerico($('#capital').val());
        const parcelas = parseInt($('#parcelas').val()) || 1;
        const taxaJuros = obterValorNumerico($('#juros').val());
        
        if (capital > 0 && parcelas > 0 && taxaJuros >= 0) {
            // Cálculo com base na taxa de juros informada
            const valorJuros = capital * (taxaJuros / 100);
            const valorTotal = capital + valorJuros;
            const valorParcela = valorTotal / parcelas;
            
            // Exibir resumo do cálculo na interface
            mostrarResumoCalculo({
                capital: capital,
                parcelas: parcelas,
                taxaJuros: taxaJuros,
                valorJuros: valorJuros,
                valorTotal: valorTotal,
                valorParcela: valorParcela
            });
        }
    }
}

// Arredondar valor da parcela para o inteiro superior
function arredondarValorParcela() {
    try {
        // Pega o valor da parcela atual
        const valorOriginal = $('#valor_parcela').val();
        console.log("Valor original (string):", valorOriginal);
        
        // Converte para número garantindo que é tratado como decimal
        const valorParcela = obterValorNumerico(valorOriginal);
        console.log("Valor convertido para número:", valorParcela);
        
        if (valorParcela <= 0) {
            console.error("Valor da parcela inválido para arredondamento:", valorParcela);
            return;
        }
        
        // Arredonda para o inteiro superior
        const valorArredondado = Math.ceil(valorParcela);
        console.log("Valor após arredondamento:", valorArredondado);
        
        // Atualiza o campo com o valor arredondado formatado
        atualizarCampoMonetario('#valor_parcela', valorArredondado);
        
        // Busca os valores atuais
        const capital = obterValorNumerico($('#capital').val());
        const parcelas = parseInt($('#parcelas').val()) || 1;
        
        if (capital > 0 && parcelas > 0) {
            // Recalcula os valores baseados no novo valor da parcela
            const valorTotal = valorArredondado * parcelas;
            const valorJuros = valorTotal - capital;
            const taxaJuros = (valorJuros / capital) * 100;
            
            // Exibe o resumo atualizado
            mostrarResumoCalculo({
                capital: capital,
                parcelas: parcelas,
                taxaJuros: taxaJuros,
                valorJuros: valorJuros,
                valorTotal: valorTotal,
                valorParcela: valorArredondado,
                editado: true
            });
        }
    } catch (error) {
        console.error("Erro ao arredondar valor da parcela:", error);
    }
}

// Aplicar máscaras nos campos
function aplicarMascaras() {
    // Limpar máscaras existentes primeiro para evitar conflitos
    $('#capital, #tlc_valor, #valor_parcela, #juros').maskMoney('destroy');
    
    // Máscara para valores monetários (usando o plugin jQuery Mask)
    $('#capital, #tlc_valor, #valor_parcela').maskMoney({
        prefix: 'R$ ',
        allowNegative: false,
        thousands: '.',
        decimal: ',',
        affixesStay: false,
        precision: 2
    });
    
    // Máscara para percentual
    $('#juros').maskMoney({
        suffix: '%',
        allowNegative: false,
        thousands: '',
        decimal: ',',
        affixesStay: false,
        precision: 2
    });
    
    // Inicializar eventos após aplicar máscaras
    $('#capital, #tlc_valor, #juros, #valor_parcela').on('blur', function() {
        // Forçar atualização dos cálculos após perder o foco
        const modoCalculo = $('#modo_calculo').val();
        const id = $(this).attr('id');
        
        if (id === 'valor_parcela') {
            atualizarValorTotalPorParcela();
        } else if (modoCalculo === 'parcela') {
            calcularValorParcela();
        } else {
            calcularPorTaxaJuros();
        }
    });
    
    // Aplicar máscaras nos campos que já têm valores
    $('#capital, #tlc_valor, #valor_parcela, #juros').each(function() {
        const valor = $(this).val();
        if (valor) {
            $(this).maskMoney('mask');
        }
    });
}

// Inicializar eventos quando o documento estiver pronto
$(document).ready(function() {
    // Configurar eventos para formulário de empréstimo quando estiver na página correta
    if ($('#formEmprestimo').length > 0) {
        // Marcar feriados por padrão e inicializar os checkboxes
        $('#feriados').prop('checked', true);
        
        // Aplicar máscaras nos campos
        aplicarMascaras();
        
        // Eventos de campos
        $('#tipo_cobranca').change(controlarTipoCobranca);
        $('#usar_tlc').change(controlarCamposTLC);
        $('#modo_calculo').change(controlarModoCalculo);
        
        // Evento para o botão de arredondar
        $('#btn_arredondar').click(arredondarValorParcela);
        
        // Eventos para checkboxes de dias da semana - usar delegação de evento
        // para garantir que funcione mesmo após recriação dos elementos
        $(document).on('change', '.form-check-input[name="dias_semana[]"]', function() {
            console.log("Checkbox de dia alterado:", $(this).val());
            // Atualiza a data inicial quando os dias são alterados
            atualizarDataInicial();
        });
        
        // Eventos para recalcular valor da parcela quando mudar capital ou parcelas
        $('#capital, #parcelas').on('input', function() {
            const modoCalculo = $('#modo_calculo').val();
            
            // Só executa se um modo estiver selecionado
            if (!modoCalculo) return;
            
            if (modoCalculo === 'parcela') {
                calcularValorParcela();
            } else if (modoCalculo === 'taxa') {
                calcularPorTaxaJuros();
            }
        });
        
        // Evento para atualizar cálculos quando o usuário editar o valor da parcela
        $('#valor_parcela').on('input', atualizarValorTotalPorParcela);
        
        // Evento para atualizar cálculos quando o usuário editar a taxa de juros
        $('#juros').on('input', calcularPorTaxaJuros);
        
        // Inicializa o estado dos campos baseado nos valores iniciais
        controlarTipoCobranca();
        controlarCamposTLC();
        controlarModoCalculo();
        
        // Configurar data inicial para o próximo dia válido
        // Deve ser chamado por último, depois que os checkboxes estiverem configurados
        setTimeout(configurarDataInicial, 100); // pequeno delay para garantir que tudo foi inicializado
    }
}); 

  