<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/autenticacao.php';
require_once __DIR__ . '/../../includes/conexao.php';
require_once __DIR__ . '/../../includes/funcoes_data.php';

// Recebe parâmetros
$emprestimo_id = filter_input(INPUT_GET, 'emprestimo_id', FILTER_VALIDATE_INT);
$parcela_numero = filter_input(INPUT_GET, 'parcela_numero', FILTER_VALIDATE_INT);

if (!$emprestimo_id || !$parcela_numero) {
    die('Parâmetros inválidos.');
}

// Busca informações do empréstimo e cliente
$stmt = $conn->prepare("
    SELECT 
        e.*, 
        c.nome AS cliente_nome, 
        c.cpf_cnpj AS cliente_cpf, 
        c.telefone AS cliente_telefone,
        c.endereco AS cliente_endereco,
        c.cidade AS cliente_cidade,
        c.estado AS cliente_uf
    FROM 
        emprestimos e
    JOIN 
        clientes c ON e.cliente_id = c.id
    WHERE 
        e.id = ?
");
$stmt->bind_param("i", $emprestimo_id);
$stmt->execute();
$emprestimo = $stmt->get_result()->fetch_assoc();

if (!$emprestimo) {
    die('Empréstimo não encontrado.');
}

// Busca dados da empresa
$stmt_empresa = $conn->prepare("SELECT * FROM configuracoes WHERE id = 1");
$stmt_empresa->execute();
$empresa = $stmt_empresa->get_result()->fetch_assoc();

// Busca a parcela específica
$stmt_parcela = $conn->prepare("
    SELECT 
        * 
    FROM 
        parcelas 
    WHERE 
        emprestimo_id = ? AND numero = ?
");
$stmt_parcela->bind_param("ii", $emprestimo_id, $parcela_numero);
$stmt_parcela->execute();
$parcela = $stmt_parcela->get_result()->fetch_assoc();

if (!$parcela) {
    die('Parcela não encontrada.');
}

if ($parcela['status'] !== 'pago') {
    die('Esta parcela ainda não foi paga.');
}

// Formata valores para exibição
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Só define formatarData se ela ainda não existir
if (!function_exists('formatarData')) {
    function formatarData($data) {
        return date('d/m/Y', strtotime($data));
    }
}

function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

// Formata a data por extenso
if (!function_exists('dataPorExtenso')) {
    function dataPorExtenso($data) {
        setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        
        $data_obj = new DateTime($data);
        $dia = $data_obj->format('d');
        $mes = (int)$data_obj->format('m');
        $ano = $data_obj->format('Y');
        
        return "$dia de {$meses[$mes]} de $ano";
    }
}

// Função para converter valor em extenso
function valorPorExtenso($valor) {
    // Primeiro, certifique-se de que o valor está no formato adequado para processamento
    if (is_string($valor)) {
        // Se for string, converte vírgula para ponto
        $valor = str_replace(',', '.', $valor);
    }
    
    // Agora converte para float para garantir
    $valor = (float) $valor;
    
    $valor_formatado = number_format($valor, 2, ',', '.');
    $singular = array('centavo', 'real', 'mil', 'milhão', 'bilhão');
    $plural = array('centavos', 'reais', 'mil', 'milhões', 'bilhões');

    $c = array('', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos');
    $d = array('', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa');
    $d10 = array('dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove');
    $u = array('', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove');

    $z = 0;
    $rt = '';

    $valor = number_format($valor, 2, '.', '.');
    $inteiro = explode('.', $valor);
    
    for($i=0;$i<count($inteiro);$i++)
        for($ii=mb_strlen($inteiro[$i]);$ii<3;$ii++)
            $inteiro[$i] = '0'.$inteiro[$i];

    $fim = count($inteiro) - ($inteiro[count($inteiro)-1] > 0 ? 1 : 2);
    
    for ($i=0;$i<count($inteiro);$i++) {
        $valor = $inteiro[$i];
        $rc = (($valor > 100) && ($valor < 200)) ? 'cento' : $c[$valor[0]];
        $rd = ($valor[1] < 2) ? '' : $d[$valor[1]];
        $ru = ($valor > 0) ? (($valor[1] == 1) ? $d10[$valor[2]] : $u[$valor[2]]) : '';

        $r = $rc.(($rc && ($rd || $ru)) ? ' e ' : '').$rd.(($rd && $ru) ? ' e ' : '').$ru;
        $t = count($inteiro)-1-$i;
        
        if ($valor > 0) {
            if ($t == 0) {
                $rt = $rt . ((($i > 0) && ($inteiro[0] > 0) && ($inteiro[$i-1] > 0)) ? ', ' : ' ') . $r . ' ' . ($valor > 1 ? $plural[$t] : $singular[$t]);
            } else if ($t == 1) {
                $rt = $rt . ((($i > 0) && ($inteiro[0] > 0)) ? (($inteiro[$i-1] > 0) ? ', ' : ' e ') : ' ') . $r . ' ' . ($valor > 1 ? $plural[$t] : $singular[$t]);
            } else {
                $rt = $rt . ((($i > 0) && ($inteiro[0] > 0)) ? ', ' : ' ') . $r . ' ' . ($valor > 1 ? $plural[$t] : $singular[$t]);
            }
        }
    }

    if(!empty($rt)) {
        $rt = trim($rt);
        if($rt[0] == ',') {
            $rt = substr($rt, 1);
        }
    }
    
    if (mb_substr($rt, -6) == 'bilhão') { $rt = mb_substr($rt, 0, -6) . 'bilhões'; }
    if (mb_substr($rt, -7) == 'milhão') { $rt = mb_substr($rt, 0, -7) . 'milhões'; }
    if (mb_substr($rt, -4) == 'mil ') { $rt = mb_substr($rt, 0, -4) . ' mil '; }
    
    $decimal = explode('.', number_format($valor, 2, '.', '.'));
    $decimal = intval($decimal[1]);
    
    if ($decimal) {
        $cent = $decimal == 1 ? 'centavo' : 'centavos';
        $rt .= ' e ' . $decimal . ' ' . $cent;
    }

    return $rt ? ucfirst($rt) : 'Zero';
}

// Define o número do recibo
$numero_recibo = $emprestimo_id . '-' . $parcela_numero . '/' . date('Y');

// Valor pago (adaptado para parcial ou pago completo)
$valor_pago = isset($parcela['valor_pago']) ? $parcela['valor_pago'] : $parcela['valor'];

// Gera o HTML do recibo
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pagamento - Parcela #' . $parcela_numero . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .recibo {
            border: 2px solid #333;
            padding: 20px;
            margin-bottom: 30px;
            position: relative;
        }
        .recibo-titulo {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        .recibo-numero {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 14px;
        }
        .cabecalho {
            text-align: center;
            margin-bottom: 30px;
        }
        .cabecalho h1 {
            margin: 0;
            font-size: 24px;
        }
        .cabecalho p {
            margin: 5px 0;
            font-size: 14px;
        }
        .info-bloco {
            margin-bottom: 20px;
        }
        .info-titulo {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .info-dados {
            margin-left: 20px;
        }
        .texto-recibo {
            text-align: justify;
            margin: 30px 0;
            line-height: 1.8;
        }
        .assinatura {
            margin-top: 50px;
            text-align: center;
        }
        .linha-assinatura {
            border-top: 1px solid #333;
            width: 70%;
            margin: 10px auto;
        }
        .data-assinatura {
            margin-top: 50px;
            text-align: right;
        }
        .valor-destacado {
            font-weight: bold;
        }
        .observacao {
            font-size: 11px;
            margin-top: 30px;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
        }
        .instrucoes {
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        @media print {
            body {
                padding: 0;
            }
            .instrucoes {
                display: none;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="instrucoes no-print">
        <p>Instruções:</p>
        <ol>
            <li>Imprima este recibo em duas vias</li>
            <li>Assine ambas as vias</li>
            <li>Entregue uma via para o cliente e mantenha a outra para seus registros</li>
        </ol>
        <button onclick="window.print()" class="no-print">Imprimir Recibo</button>
    </div>

    <div class="recibo">
        <div class="recibo-numero">RECIBO Nº ' . $numero_recibo . '</div>
        <div class="recibo-titulo">RECIBO DE PAGAMENTO DE PARCELA</div>
        
        <div class="cabecalho">
            <h1>' . htmlspecialchars($empresa['nome_empresa'] ?? 'EMPRESA DE EMPRÉSTIMOS') . '</h1>
            <p>' . htmlspecialchars($empresa['endereco'] ?? 'Endereço da Empresa') . '</p>
            <p>CNPJ: ' . htmlspecialchars($empresa['cnpj'] ?? '00.000.000/0000-00') . '</p>
        </div>
        
        <div class="info-bloco">
            <div class="info-titulo">DADOS DO CLIENTE:</div>
            <div class="info-dados">
                <p>Nome: ' . htmlspecialchars($emprestimo['cliente_nome']) . '</p>
                <p>CPF: ' . formatarCPF($emprestimo['cliente_cpf']) . '</p>
                <p>Endereço: ' . htmlspecialchars($emprestimo['cliente_endereco'] ?? 'Não informado') . '</p>
                <p>Telefone: ' . htmlspecialchars($emprestimo['cliente_telefone']) . '</p>
            </div>
        </div>
        
        <div class="info-bloco">
            <div class="info-titulo">DADOS DO PAGAMENTO:</div>
            <div class="info-dados">
                <p>Nº do Empréstimo: ' . $emprestimo_id . '</p>
                <p>Parcela: ' . $parcela_numero . '/' . $emprestimo['parcelas'] . '</p>
                <p>Data de Vencimento: ' . formatarData($parcela['vencimento']) . '</p>
                <p>Data de Pagamento: ' . formatarData($parcela['data_pagamento']) . '</p>
                <p>Valor: ' . formatarMoeda($parcela['valor']) . '</p>
                <p>Valor Pago: ' . formatarMoeda($valor_pago) . '</p>
                <p>Forma de Pagamento: ' . ucfirst($parcela['forma_pagamento'] ?? 'Não informado') . '</p>
            </div>
        </div>
        
        <div class="texto-recibo">
            <p>Recebi do cliente <span class="valor-destacado">' . htmlspecialchars($emprestimo['cliente_nome']) . '</span>, o valor de <span class="valor-destacado">' . formatarMoeda($valor_pago) . '</span> (<span class="valor-destacado">' . valorPorExtenso($valor_pago) . '</span>), referente ao pagamento ' . ($valor_pago < $parcela['valor'] ? 'parcial' : 'integral') . ' da parcela nº <span class="valor-destacado">' . $parcela_numero . '</span> do empréstimo nº <span class="valor-destacado">' . $emprestimo_id . '</span>.</p>
            
            <p>Este pagamento foi recebido em <span class="valor-destacado">' . formatarData($parcela['data_pagamento']) . '</span>, através de <span class="valor-destacado">' . ucfirst($parcela['forma_pagamento'] ?? 'pagamento') . '</span>.</p>
            ' . ($parcela['observacao'] ? '<p>Observação: ' . htmlspecialchars($parcela['observacao']) . '</p>' : '') . '
        </div>
        
        <div class="data-assinatura">
            <p>' . ($empresa['cidade'] ?? 'Cidade') . ', ' . dataPorExtenso(date('Y-m-d')) . '</p>
        </div>
        
        <div class="assinatura">
            <div class="linha-assinatura"></div>
            <p>' . htmlspecialchars($empresa['nome_empresa'] ?? 'EMPRESA DE EMPRÉSTIMOS') . '</p>
            <p>CNPJ: ' . htmlspecialchars($empresa['cnpj'] ?? '00.000.000/0000-00') . '</p>
        </div>
        
        <div class="observacao">
            <p>Este recibo é válido como comprovante de pagamento da parcela. Guarde-o em local seguro para futuras consultas.</p>
        </div>
    </div>
    
    <div class="instrucoes no-print">
        <button onclick="window.print()">Imprimir Recibo</button>
    </div>
</body>
</html>
';

// Exibe o HTML do recibo
echo $html; 