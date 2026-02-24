<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/autenticacao.php';
require_once __DIR__ . '/../../includes/conexao.php';
require_once __DIR__ . '/../../includes/funcoes_data.php';

// Recebe o ID do empréstimo
$emprestimo_id = filter_input(INPUT_GET, 'emprestimo_id', FILTER_VALIDATE_INT);

if (!$emprestimo_id) {
    die('ID do empréstimo não informado ou inválido.');
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

// Busca as parcelas do empréstimo para calcular o total
$stmt_parcelas = $conn->prepare("
    SELECT 
        valor, 
        status,
        valor_pago,
        data_pagamento,
        forma_pagamento
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

$total_pago = 0;
$total_previsto = 0;
$ultima_data_pagamento = null;
$ultima_forma_pagamento = null;

while ($parcela = $result_parcelas->fetch_assoc()) {
    $total_previsto += $parcela['valor'];
    
    if ($parcela['status'] === 'pago') {
        $total_pago += $parcela['valor_pago'] ?? $parcela['valor'];
        
        // Atualiza a última data e forma de pagamento
        if ($parcela['data_pagamento'] !== null) {
            $ultima_data_pagamento = $parcela['data_pagamento'];
            $ultima_forma_pagamento = $parcela['forma_pagamento'];
        }
    } elseif ($parcela['status'] === 'parcial' && isset($parcela['valor_pago'])) {
        $total_pago += $parcela['valor_pago'];
    }
}

// Formata valores para exibição
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarData($data) {
    if (empty($data)) {
        return date('d/m/Y'); // Se a data for vazia, retorna a data atual
    }
    return date('d/m/Y', strtotime($data));
}

function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

// Formata a data por extenso
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

// Função para converter valor em extenso
function valorPorExtenso($valor) {
    // Preparar o valor para processamento
    if (is_string($valor)) {
        // Remove quaisquer caracteres não numéricos, exceto ponto e vírgula
        $valor = preg_replace('/[^0-9.,]/', '', $valor);
        
        // Converte formato brasileiro para formato americano
        $valor = str_replace('.', '', $valor); // Remove pontos (separadores de milhares)
        $valor = str_replace(',', '.', $valor); // Substitui vírgula por ponto (decimal)
    }
    
    // Garantir que é um número
    $valor = (float)$valor;
    
    // Agora podemos usar o number_format com segurança
    $valor_formatado = number_format($valor, 2, ',', '.');
    
    $singular = array('centavo', 'real', 'mil', 'milhão', 'bilhão');
    $plural = array('centavos', 'reais', 'mil', 'milhões', 'bilhões');

    $c = array('', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos');
    $d = array('', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa');
    $d10 = array('dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove');
    $u = array('', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove');

    $z = 0;
    $rt = '';

    // Usar o valor já convertido para float
    $inteiro = explode(',', $valor_formatado);
    
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
    
    // Os centavos são a parte decimal do valor formatado
    $centavos = (int)$inteiro[1];
    
    if ($centavos) {
        $cent = $centavos == 1 ? 'centavo' : 'centavos';
        $rt .= ' e ' . $centavos . ' ' . $cent;
    }

    return $rt ? ucfirst($rt) : 'Zero';
}

// Define o número do recibo
$numero_recibo = $emprestimo_id . '/' . date('Y');

// Gera o HTML do recibo
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Quitação - Empréstimo #' . $emprestimo_id . '</title>
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
        <div class="recibo-titulo">RECIBO DE QUITAÇÃO DE EMPRÉSTIMO</div>
        
        <div class="cabecalho">
            <h1>' . htmlspecialchars($empresa['nome_empresa'] ?? 'EMPRESA DE EMPRÉSTIMOS') . '</h1>
            <p>' . htmlspecialchars($empresa['endereco'] ?? 'Endereço da Empresa') . '</p>
            <p>CNPJ: ' . htmlspecialchars($empresa['cnpj'] ?? '00.000.000/0000-00') . '</p>
        </div>
        
        <div class="info-bloco">
            <div class="info-titulo">DADOS DO CLIENTE:</div>
            <div class="info-dados">
                <p>Nome: ' . htmlspecialchars($emprestimo['cliente_nome'] ?? '') . '</p>
                <p>CPF: ' . formatarCPF($emprestimo['cliente_cpf']) . '</p>
                <p>Endereço: ' . htmlspecialchars($emprestimo['cliente_endereco'] ?? 'Não informado') . '</p>
                <p>Telefone: ' . htmlspecialchars($emprestimo['cliente_telefone'] ?? '') . '</p>
            </div>
        </div>
        
        <div class="info-bloco">
            <div class="info-titulo">DADOS DO EMPRÉSTIMO:</div>
            <div class="info-dados">
                <p>Nº do Contrato: ' . $emprestimo_id . '</p>
                <p>Data do Empréstimo: ' . formatarData($emprestimo['data_inicio'] ?? null) . '</p>
                <p>Valor Emprestado: ' . formatarMoeda($emprestimo['valor_emprestado']) . '</p>
                <p>Valor Total: ' . formatarMoeda($total_previsto) . '</p>
                <p>Valor Total Pago: ' . formatarMoeda($total_pago) . '</p>
            </div>
        </div>
        
        <div class="texto-recibo">
            <p>Declaro para os devidos fins que o empréstimo de número <span class="valor-destacado">' . $emprestimo_id . '</span>, no valor total de <span class="valor-destacado">' . formatarMoeda($total_previsto) . '</span> (<span class="valor-destacado">' . valorPorExtenso($total_previsto) . '</span>), foi totalmente QUITADO nesta data, não havendo mais nenhum valor a ser pago referente a este contrato.</p>
            
            <p>O pagamento total foi realizado em parcelas, sendo a última liquidação efetuada em <span class="valor-destacado">' . formatarData($ultima_data_pagamento) . '</span>, através de <span class="valor-destacado">' . ucfirst($ultima_forma_pagamento ?? 'pagamento') . '</span>.</p>
            
            <p>Dou plena, geral e irrevogável quitação, para nada mais reclamar em tempo algum, seja a que título for.</p>
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
            <p>Este recibo é válido como comprovante de quitação total do empréstimo. Guarde-o em local seguro para futuras consultas.</p>
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