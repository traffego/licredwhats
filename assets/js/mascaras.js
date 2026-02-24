$(document).ready(function () {
    // Máscara dinâmica CPF ou CNPJ
    function aplicarMascaraDocumento() {
        var tipo = $('select[name="tipo_pessoa"]').val();
        var $input = $('input[name="cpf_cnpj"]');
        $input.unmask();
        if (tipo === '1') {
            $input.mask('000.000.000-00'); // CPF
            $input.attr('placeholder', '000.000.000-00');
        } else if (tipo === '2') {
            $input.mask('00.000.000/0000-00'); // CNPJ
            $input.attr('placeholder', '00.000.000/0000-00');
        }
    }

    aplicarMascaraDocumento();
    $('select[name="tipo_pessoa"]').on('change', aplicarMascaraDocumento);

    // Telefone e telefone secundário
    var telefoneMask = function (val) {
        return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
    };
    var telefoneOptions = {
        onKeyPress: function (val, e, field, options) {
            field.mask(telefoneMask.apply({}, arguments), options);
        }
    };
    $('input[name="telefone"], input[name="telefone_secundario"]').mask(telefoneMask, telefoneOptions);

    // CEP
    $('input[name="cep"]').mask('00000-000').attr('placeholder', '00000-000');

    // Nascimento
    $('input[name="nascimento"]').mask('00/00/0000').attr('placeholder', 'dd/mm/aaaa');

    // Valores monetários
    $('.valor').mask('000.000.000,00', {reverse: true});

    // --- Lógica dinâmica de cálculos ---
    function limparMascara(valor) {
        if (!valor) return 0;
        return parseFloat(valor.replace(/\./g, '').replace(',', '.')) || 0;
    }

    function aplicarMascara(numero) {
        return numero.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function atualizarCampos() {
        const parcelas = parseInt($('input[name="quantidade_parcelas"]').val()) || 0;
        const emprestado = limparMascara($('input[name="valor_emprestado"]').val());
        const valorParcela = limparMascara($('input[name="valor_parcela"]').val());

        if (emprestado && parcelas && !valorParcela) {
            const calculado = emprestado / parcelas;
            $('input[name="valor_parcela"]').val(aplicarMascara(calculado));
        }

        const parcelaFinal = limparMascara($('input[name="valor_parcela"]').val());

        if (parcelas && parcelaFinal) {
            const total = parcelas * parcelaFinal;
            $('input[name="valor_total"]').val(aplicarMascara(total));
        }
    }

    $('input[name="valor_emprestado"], input[name="quantidade_parcelas"], input[name="valor_parcela"]').on('input', atualizarCampos);
});
