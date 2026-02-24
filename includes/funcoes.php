<?php
/**
 * Arquivo de funções utilitárias para o sistema de empréstimos
 */

/**
 * Calcula o valor da parcela com base no valor emprestado, número de parcelas e taxa de juros
 * 
 * @param float $valor_emprestado Valor total emprestado
 * @param int $num_parcelas Número de parcelas do empréstimo
 * @param float $juros_percentual Taxa de juros em percentual (ex: 10 para 10%)
 * @return float Valor da parcela calculada
 */
function calcularValorParcela($valor_emprestado, $num_parcelas, $juros_percentual) {
    // Implementação básica do cálculo de parcelas com juros simples
    $juros_decimal = $juros_percentual / 100;
    $valor_com_juros = $valor_emprestado * (1 + $juros_decimal);
    return $valor_com_juros / $num_parcelas;
}

/**
 * Calcula a data de vencimento de uma parcela a partir da data de início
 * 
 * @param string $data_inicio Data de início no formato Y-m-d
 * @param int $numero_parcela Número da parcela (1, 2, 3...)
 * @return string Data de vencimento no formato Y-m-d
 */
function calcularDataVencimento($data_inicio, $numero_parcela) {
    $data = new DateTime($data_inicio);
    $data->modify('+' . $numero_parcela . ' months');
    return $data->format('Y-m-d');
}

/**
 * Formata um valor para exibição em formato monetário (R$)
 * 
 * @param float $valor Valor a ser formatado
 * @return string Valor formatado no padrão brasileiro
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formata uma data para o padrão brasileiro
 * 
 * @param string $data Data no formato Y-m-d
 * @return string Data formatada (dd/mm/aaaa)
 */
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

/**
 * Calcula o valor total de um empréstimo (soma de todas as parcelas)
 * 
 * @param float $valor_parcela Valor de cada parcela
 * @param int $num_parcelas Número total de parcelas
 * @return float Valor total do empréstimo
 */
function calcularValorTotal($valor_parcela, $num_parcelas) {
    return $valor_parcela * $num_parcelas;
}

/**
 * Calcula o lucro de um empréstimo
 * 
 * @param float $valor_total Valor total a receber
 * @param float $valor_emprestado Valor inicialmente emprestado
 * @return float Lucro previsto
 */
function calcularLucro($valor_total, $valor_emprestado) {
    return $valor_total - $valor_emprestado;
}

/**
 * Verifica se uma parcela está atrasada
 * 
 * @param string $data_vencimento Data de vencimento no formato Y-m-d
 * @param string $status Status atual da parcela
 * @return bool True se a parcela estiver atrasada
 */
function parcelaAtrasada($data_vencimento, $status) {
    $hoje = new DateTime();
    $vencimento = new DateTime($data_vencimento);
    return $status == 'pendente' && $vencimento < $hoje;
} 