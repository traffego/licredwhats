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
echo "<title>Atualizar Saldo de Contas</title>";
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

echo "<h1>Atualizar Saldo de Contas</h1>";

// Verificar se estamos no modo de execução
$executar = isset($_GET['executar']) && $_GET['executar'] == 1;

if (!$executar) {
    echo "<p>Este script irá recalcular e atualizar o saldo de todas as contas com base nas movimentações registradas, incluindo comissões.</p>";
    echo "<p>O script fará o seguinte:</p>";
    echo "<ol>";
    echo "<li>Listar todas as contas cadastradas</li>";
    echo "<li>Para cada conta, calcular o saldo com base na soma de todas as movimentações</li>";
    echo "<li>Atualizar o campo 'saldo' na tabela de contas</li>";
    echo "</ol>";
    echo "<p class='warning'><strong>Atenção:</strong> Este processo modificará o saldo das contas. Certifique-se de ter um backup do banco de dados antes de continuar.</p>";
    echo "<p><a href='?executar=1' class='btn btn-primary' style='padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Executar Atualização</a></p>";
    echo "<p><a href='index.php' style='margin-right: 10px;'>Voltar para a página inicial</a></p>";
} else {
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Buscar todas as contas
        $sql = "SELECT id, nome, saldo FROM contas ORDER BY id";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Erro ao buscar contas: " . $conn->error);
        }
        
        echo "<h2>Resultado da Atualização</h2>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Nome da Conta</th><th>Saldo Anterior</th><th>Saldo Calculado</th><th>Diferença</th><th>Status</th></tr>";
        
        $total_contas = $result->num_rows;
        $contas_atualizadas = 0;
        
        while ($conta = $result->fetch_assoc()) {
            $conta_id = $conta['id'];
            $nome_conta = $conta['nome'];
            $saldo_anterior = floatval($conta['saldo']);
            
            // Calcular saldo com base nas movimentações
            $sql_movimentacoes = "SELECT 
                                     SUM(CASE 
                                         WHEN tipo = 'entrada' THEN valor 
                                         WHEN tipo = 'saida' THEN -valor
                                         ELSE 0 
                                     END) as saldo_calculado 
                                  FROM 
                                     movimentacoes_contas 
                                  WHERE 
                                     conta_id = ?";
            
            $stmt = $conn->prepare($sql_movimentacoes);
            $stmt->bind_param("i", $conta_id);
            $stmt->execute();
            $result_saldo = $stmt->get_result();
            $row_saldo = $result_saldo->fetch_assoc();
            
            $saldo_calculado = floatval($row_saldo['saldo_calculado']);
            $diferenca = $saldo_calculado - $saldo_anterior;
            
            // Se houver diferença, atualizar o saldo
            if (abs($diferenca) > 0.01) {
                $sql_update = "UPDATE contas SET saldo = ? WHERE id = ?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("di", $saldo_calculado, $conta_id);
                $resultado_update = $stmt->execute();
                
                $status = $resultado_update ? "<span class='success'>Atualizado</span>" : "<span class='error'>Erro</span>";
                
                if ($resultado_update) {
                    $contas_atualizadas++;
                }
            } else {
                $status = "<span>Sem alteração</span>";
            }
            
            echo "<tr>";
            echo "<td>$conta_id</td>";
            echo "<td>" . htmlspecialchars($nome_conta) . "</td>";
            echo "<td>R$ " . number_format($saldo_anterior, 2, ',', '.') . "</td>";
            echo "<td>R$ " . number_format($saldo_calculado, 2, ',', '.') . "</td>";
            echo "<td>" . ($diferenca >= 0 ? "+" : "") . "R$ " . number_format($diferenca, 2, ',', '.') . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Comittar as alterações
        $conn->commit();
        
        echo "<h2>Resumo da Operação</h2>";
        echo "<p class='success'>Operação concluída com sucesso!</p>";
        echo "<p>Total de contas verificadas: $total_contas</p>";
        echo "<p>Contas com saldo atualizado: $contas_atualizadas</p>";
        
    } catch (Exception $e) {
        // Em caso de erro, reverte as alterações
        $conn->rollback();
        echo "<p class='error'>ERRO: " . $e->getMessage() . "</p>";
    }
    
    echo "<p><a href='verificar_fluxo_capital.php' style='margin-right: 10px; padding: 10px 20px; background-color: #008CBA; color: white; text-decoration: none; border-radius: 5px;'>Verificar Fluxo de Capital</a></p>";
    echo "<p><a href='index.php' style='padding: 10px 20px; background-color: #555555; color: white; text-decoration: none; border-radius: 5px;'>Voltar para a Página Inicial</a></p>";
}

echo "</body></html>";
?> 