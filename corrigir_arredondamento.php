<?php
// Forçar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Forçar cabeçalho HTML para exibição adequada
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<meta charset='UTF-8'>";
echo "<title>Correção de Arredondamento em Comissões</title>";
echo "<style>
    body {font-family: Arial, sans-serif; padding: 20px; line-height: 1.6;}
    h1, h2 {color: #333;}
    table {border-collapse: collapse; width: 100%; margin-bottom: 20px;}
    th, td {border: 1px solid #ddd; padding: 8px; text-align: left;}
    th {background-color: #f2f2f2;}
    tr:nth-child(even) {background-color: #f9f9f9;}
    .error {color: red; font-weight: bold;}
    .success {color: green;}
    .warning {color: orange;}
</style>";
echo "</head><body>";

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/autenticacao.php';

// Verificar se a requisição veio de um administrador
if (!temPermissao('administrador')) {
    die("<p class='error'>Acesso negado. Apenas administradores podem executar esta operação.</p></body></html>");
}

echo "<h1>Correção de Arredondamento em Comissões</h1>";

// Verificar se estamos no modo de execução
$executar = isset($_GET['executar']) && $_GET['executar'] == 1;

if (!$executar) {
    echo "<p>Este script irá corrigir os valores das comissões para garantir que a soma seja exatamente igual ao valor esperado (40% do lucro total).</p>";
    echo "<p>O script fará o seguinte:</p>";
    echo "<ol>";
    echo "<li>Identificar todas as comissões relacionadas ao mesmo empréstimo</li>";
    echo "<li>Recalcular os valores para que a soma total seja exatamente 40% do lucro</li>";
    echo "<li>Atualizar os registros no banco de dados</li>";
    echo "</ol>";
    echo "<p class='warning'><strong>Atenção:</strong> Este processo modificará os valores das comissões. Certifique-se de ter um backup do banco de dados antes de continuar.</p>";
    echo "<p><a href='?executar=1' class='btn btn-primary' style='padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Executar Correção</a></p>";
    echo "<p><a href='verificar_fluxo_capital.php' style='margin-right: 10px;'>Voltar para a verificação</a></p>";
} else {
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Identificar empréstimos com comissões
        $sql = "SELECT 
                    DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(descricao, 'do empréstimo #', -1), ' ', 1) as emprestimo_id
                FROM 
                    movimentacoes_contas
                WHERE 
                    descricao LIKE 'Comissão - Parcela #%'";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Erro ao identificar empréstimos: " . $conn->error);
        }
        
        $total_emprestimos = $result->num_rows;
        echo "<p>Encontrados $total_emprestimos empréstimos com comissões a corrigir.</p>";
        
        $emprestimos_corrigidos = 0;
        
        while ($row = $result->fetch_assoc()) {
            $emprestimo_id = $row['emprestimo_id'];
            
            // Buscar informações do empréstimo
            $sql_emprestimo = "SELECT 
                                  id, valor_emprestado, parcelas,
                                  (SELECT SUM(IFNULL(valor_pago, valor)) FROM parcelas WHERE emprestimo_id = e.id AND status = 'pago') as total_pago
                               FROM 
                                  emprestimos e
                               WHERE 
                                  id = ?";
            
            $stmt = $conn->prepare($sql_emprestimo);
            $stmt->bind_param("i", $emprestimo_id);
            $stmt->execute();
            $emprestimo = $stmt->get_result()->fetch_assoc();
            
            if (!$emprestimo) {
                echo "<p class='warning'>Empréstimo #$emprestimo_id não encontrado. Pulando...</p>";
                continue;
            }
            
            // Calcular o valor correto da comissão total (40% do lucro)
            $lucro = $emprestimo['total_pago'] - $emprestimo['valor_emprestado'];
            $comissao_total_correta = $lucro * 0.4;
            $comissao_por_parcela = $comissao_total_correta / $emprestimo['parcelas'];
            
            // Buscar todas as comissões deste empréstimo
            $sql_comissoes = "SELECT 
                                id, valor
                              FROM 
                                movimentacoes_contas
                              WHERE 
                                descricao LIKE CONCAT('Comissão - Parcela #% do empréstimo #', ?, '%')
                              ORDER BY 
                                id";
            
            $stmt = $conn->prepare($sql_comissoes);
            $stmt->bind_param("i", $emprestimo_id);
            $stmt->execute();
            $result_comissoes = $stmt->get_result();
            
            $comissoes = [];
            $total_atual = 0;
            
            while ($comissao = $result_comissoes->fetch_assoc()) {
                $comissoes[] = $comissao;
                $total_atual += $comissao['valor'];
            }
            
            $num_comissoes = count($comissoes);
            
            if ($num_comissoes == 0) {
                echo "<p class='warning'>Nenhuma comissão encontrada para o empréstimo #$emprestimo_id. Pulando...</p>";
                continue;
            }
            
            echo "<h2>Empréstimo #$emprestimo_id</h2>";
            echo "<p>Valor emprestado: R$ " . number_format($emprestimo['valor_emprestado'], 2, ',', '.') . "</p>";
            echo "<p>Total pago: R$ " . number_format($emprestimo['total_pago'], 2, ',', '.') . "</p>";
            echo "<p>Lucro: R$ " . number_format($lucro, 2, ',', '.') . "</p>";
            echo "<p>Comissão total esperada (40% do lucro): R$ " . number_format($comissao_total_correta, 2, ',', '.') . "</p>";
            echo "<p>Comissão total atual: R$ " . number_format($total_atual, 2, ',', '.') . "</p>";
            echo "<p>Diferença: R$ " . number_format($comissao_total_correta - $total_atual, 2, ',', '.') . "</p>";
            echo "<p>Número de comissões: $num_comissoes</p>";
            
            if (abs($comissao_total_correta - $total_atual) < 0.01) {
                echo "<p class='success'>As comissões já estão corretas. Nenhuma alteração necessária.</p>";
                continue;
            }
            
            // Calcular novos valores de comissão distribuídos igualmente
            $comissao_por_parcela_exata = $comissao_total_correta / $num_comissoes;
            $valor_arredondado = round($comissao_por_parcela_exata, 2);
            $total_distribuido = $valor_arredondado * ($num_comissoes - 1);
            $ultimo_valor = round($comissao_total_correta - $total_distribuido, 2);
            
            echo "<table>";
            echo "<tr><th>ID</th><th>Valor Atual</th><th>Novo Valor</th></tr>";
            
            // Atualizar as comissões
            for ($i = 0; $i < $num_comissoes; $i++) {
                $comissao_id = $comissoes[$i]['id'];
                $valor_atual = $comissoes[$i]['valor'];
                
                // A última comissão recebe o valor de ajuste
                $novo_valor = ($i == $num_comissoes - 1) ? $ultimo_valor : $valor_arredondado;
                
                $sql_update = "UPDATE movimentacoes_contas SET valor = ? WHERE id = ?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("di", $novo_valor, $comissao_id);
                $stmt->execute();
                
                echo "<tr>";
                echo "<td>$comissao_id</td>";
                echo "<td>R$ " . number_format($valor_atual, 2, ',', '.') . "</td>";
                echo "<td>R$ " . number_format($novo_valor, 2, ',', '.') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            // Verificar que a soma dos novos valores é igual à comissão total correta
            $sql_verificar = "SELECT SUM(valor) as total FROM movimentacoes_contas WHERE descricao LIKE CONCAT('Comissão - Parcela #% do empréstimo #', ?, '%')";
            $stmt = $conn->prepare($sql_verificar);
            $stmt->bind_param("i", $emprestimo_id);
            $stmt->execute();
            $total_verificado = $stmt->get_result()->fetch_assoc()['total'];
            
            echo "<p>Comissão total após correção: R$ " . number_format($total_verificado, 2, ',', '.') . "</p>";
            
            if (abs($total_verificado - $comissao_total_correta) < 0.01) {
                echo "<p class='success'>Correção aplicada com sucesso!</p>";
                $emprestimos_corrigidos++;
            } else {
                echo "<p class='error'>Erro na correção! A soma dos novos valores não é igual à comissão total esperada.</p>";
                throw new Exception("Erro na correção do empréstimo #$emprestimo_id");
            }
        }
        
        // Comittar as alterações
        $conn->commit();
        
        echo "<h2>Resumo da Operação</h2>";
        echo "<p class='success'>Operação concluída com sucesso!</p>";
        echo "<p>Total de empréstimos corrigidos: $emprestimos_corrigidos</p>";
        
    } catch (Exception $e) {
        // Em caso de erro, reverte as alterações
        $conn->rollback();
        echo "<p class='error'>ERRO: " . $e->getMessage() . "</p>";
    }
    
    echo "<p><a href='verificar_fluxo_capital.php' style='padding: 10px 20px; background-color: #008CBA; color: white; text-decoration: none; border-radius: 5px;'>Voltar para Verificação</a></p>";
}

echo "</body></html>";
?> 