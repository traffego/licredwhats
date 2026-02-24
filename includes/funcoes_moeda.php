<?php
/**
 * Funções úteis para manipulação de valores monetários
 */

/**
 * Formata um valor numérico para o formato de moeda brasileira
 * 
 * @param float $valor Valor a ser formatado
 * @param bool $comSimbolo Se deve incluir o símbolo R$
 * @return string Valor formatado
 */
function formatarMoeda($valor, $comSimbolo = true) {
    if ($valor === null || $valor === '') {
        return '';
    }
    
    $valor = (float) $valor;
    $formatado = number_format($valor, 2, ',', '.');
    
    return $comSimbolo ? 'R$ ' . $formatado : $formatado;
}

/**
 * Converte um valor formatado em moeda brasileira para um float
 * 
 * @param string $valor Valor formatado (ex: R$ 1.234,56)
 * @return float Valor como número
 */
function converterMoedaParaFloat($valor) {
    if (!$valor) return 0;
    
    // Remove o símbolo R$ e espaços
    $valor = trim(str_replace('R$', '', $valor));
    
    // Remove os pontos de milhar e substitui a vírgula decimal por ponto
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    
    return (float) $valor;
}

/**
 * Converte um valor numérico para seu equivalente por extenso em reais
 * 
 * @param float $valor Valor a ser convertido
 * @return string Valor por extenso
 */
function valorPorExtenso($valor) {
    if (!$valor) return 'zero reais';

    $singular = array('centavo', 'real', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão');
    $plural = array('centavos', 'reais', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões');

    $c = array('', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos');
    $d = array('', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa');
    $d10 = array('dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove');
    $u = array('', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove');

    $z = 0;
    $valor = number_format($valor, 2, '.', '.');
    $inteiro = explode('.', $valor);
    
    $count_inteiro = count($inteiro);
    
    for($i=0;$i<$count_inteiro;$i++)
        for($ii=strlen($inteiro[$i]);$ii<3;$ii++)
            $inteiro[$i] = "0".$inteiro[$i];

    $fim = count($inteiro) - ($inteiro[count($inteiro)-1] > 0 ? 1 : 2);
    $rt = '';
    
    for ($i=0;$i<=$fim;$i++) {
        $valor = $inteiro[$i];
        
        $rc = (($valor > 100) && ($valor < 200)) ? "cento" : $c[$valor[0]];
        $rd = ($valor[1] < 2) ? '' : $d[$valor[1]];
        $ru = ($valor > 0) ? (($valor[1] == 1) ? $d10[$valor[2]] : $u[$valor[2]]) : '';
    
        $r = $rc.(($rc && ($rd || $ru)) ? " e " : "").$rd.(($rd && $ru) ? " e " : "").$ru;
        $t = count($inteiro)-1-$i;
        $r .= $r ? " ".($valor > 1 ? $plural[$t] : $singular[$t]) : "";
        
        if ($valor == "000")$z++; elseif ($z > 0) $z--;
        
        if (($t==1) && ($z>0) && ($inteiro[0] > 0)) $r .= (($z>1) ? " de " : "").$plural[$t]; 
        
        if ($r) $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? ( ($i < $fim) ? ", " : " e ") : " ") . $r;
    }

    return($rt ? $rt : "zero");
}

/**
 * Formata um CPF ou CNPJ com os pontos e traços
 * 
 * @param string $documento CPF (11 dígitos) ou CNPJ (14 dígitos)
 * @return string Documento formatado
 */
function formatarDocumento($documento) {
    $documento = preg_replace("/[^0-9]/", "", $documento);
    
    if (strlen($documento) === 11) {
        // CPF
        return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $documento);
    } elseif (strlen($documento) === 14) {
        // CNPJ
        return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $documento);
    }
    
    return $documento;
}

/**
 * Calcula juros simples sobre um valor
 * 
 * @param float $valor Valor principal
 * @param float $taxa Taxa de juros (em percentual)
 * @param int $periodo Período em dias
 * @return float Valor do juros
 */
function calcularJuros($valor, $taxa, $periodo) {
    return $valor * ($taxa / 100) * $periodo;
}

/**
 * Calcula o valor total com juros
 * 
 * @param float $valor Valor principal
 * @param float $taxa Taxa de juros (em percentual)
 * @param int $periodo Período em dias
 * @return float Valor total com juros
 */
function valorComJuros($valor, $taxa, $periodo) {
    return $valor + calcularJuros($valor, $taxa, $periodo);
}

/**
 * Calcula o valor de uma parcela com base no valor total e número de parcelas
 * 
 * @param float $valorTotal Valor total
 * @param int $numParcelas Número de parcelas
 * @return float Valor de cada parcela
 */
function calcularValorParcela($valorTotal, $numParcelas) {
    if ($numParcelas <= 0) return $valorTotal;
    
    return round($valorTotal / $numParcelas, 2);
} 