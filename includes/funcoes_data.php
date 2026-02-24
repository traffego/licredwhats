<?php
/**
 * Funções úteis para manipulação de datas
 */

/**
 * Formata uma data para o formato brasileiro (DD/MM/YYYY)
 * 
 * @param string $data Data no formato aceito pelo strtotime
 * @param string $formato Formato da data (padrão: d/m/Y)
 * @return string Data formatada
 */
if (!function_exists('formatarData')) {
    function formatarData($data, $formato = 'd/m/Y') {
        if (!$data) return '';
        
        return date($formato, strtotime($data));
    }
}

/**
 * Converte uma data do formato brasileiro (DD/MM/YYYY) para o formato do banco (YYYY-MM-DD)
 * 
 * @param string $data Data no formato brasileiro
 * @return string Data no formato do banco
 */
function converterDataParaBanco($data) {
    if (!$data) return null;
    
    $partes = explode('/', $data);
    if (count($partes) !== 3) return null;
    
    return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
}

/**
 * Retorna a data atual no formato brasileiro
 * 
 * @return string Data atual formatada
 */
function dataAtual() {
    return date('d/m/Y');
}

/**
 * Verifica se uma data é válida
 * 
 * @param string $data Data a ser verificada
 * @param string $formato Formato da data (padrão: d/m/Y)
 * @return bool True se a data for válida, false caso contrário
 */
function validarData($data, $formato = 'd/m/Y') {
    $d = DateTime::createFromFormat($formato, $data);
    return $d && $d->format($formato) === $data;
}

/**
 * Calcula a diferença em dias entre duas datas
 * 
 * @param string $data1 Primeira data
 * @param string $data2 Segunda data (padrão: data atual)
 * @return int Número de dias de diferença
 */
function diferencaDias($data1, $data2 = null) {
    $d1 = new DateTime($data1);
    $d2 = $data2 ? new DateTime($data2) : new DateTime();
    
    $diff = $d1->diff($d2);
    return abs($diff->days);
}

/**
 * Adiciona dias a uma data
 * 
 * @param string $data Data base
 * @param int $dias Número de dias a adicionar
 * @param string $formato Formato de retorno (padrão: Y-m-d)
 * @return string Nova data
 */
function adicionarDias($data, $dias, $formato = 'Y-m-d') {
    $d = new DateTime($data);
    $d->modify("+{$dias} days");
    return $d->format($formato);
}

/**
 * Retorna o mês por extenso
 * 
 * @param int $mes Número do mês (1-12)
 * @return string Nome do mês
 */
function mesExtenso($mes) {
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
    
    return isset($meses[$mes]) ? $meses[$mes] : '';
}

/**
 * Formata uma data por extenso (ex: 1 de Janeiro de 2023)
 * 
 * @param string $data Data a ser formatada
 * @return string Data por extenso
 */
if (!function_exists('dataPorExtenso')) {
    function dataPorExtenso($data) {
        $dt = new DateTime($data);
        $dia = $dt->format('d');
        $mes = mesExtenso((int)$dt->format('m'));
        $ano = $dt->format('Y');
        
        return "$dia de $mes de $ano";
    }
}

/**
 * Verifica se uma data já passou
 * 
 * @param string $data Data a ser verificada
 * @return bool True se a data já passou, false caso contrário
 */
function dataPassou($data) {
    $dt = new DateTime($data);
    $hoje = new DateTime();
    $hoje->setTime(0, 0, 0);
    
    return $dt < $hoje;
}

/**
 * Calcula a data de vencimento com base no período e data inicial
 * 
 * @param string $data_inicial Data inicial
 * @param string $periodo Período (diario, semanal, quinzenal, mensal)
 * @param int $num_parcelas Número de parcelas
 * @return array Array de datas de vencimento
 */
function calcularVencimentos($data_inicial, $periodo, $num_parcelas) {
    $data_base = new DateTime($data_inicial);
    $vencimentos = [];
    
    for ($i = 0; $i < $num_parcelas; $i++) {
        $data_vencimento = clone $data_base;
        
        switch ($periodo) {
            case 'diario':
                $data_vencimento->modify("+{$i} days");
                break;
            case 'semanal':
                $data_vencimento->modify("+{$i} weeks");
                break;
            case 'quinzenal':
                $data_vencimento->modify("+" . ($i * 15) . " days");
                break;
            case 'mensal':
            default:
                $data_vencimento->modify("+{$i} months");
                break;
        }
        
        $vencimentos[] = $data_vencimento->format('Y-m-d');
    }
    
    return $vencimentos;
} 