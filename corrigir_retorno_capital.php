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
echo "<title>Correção de Retorno de Capital</title>";
echo "<style>
    body {font-family: Arial, sans-serif; padding: 20px; line-height: 1.6;}
    h1, h2 {color: #333;}
    .error {color: red; font-weight: bold;}
    .success {color: green;}
    .warning {color: orange;}
    .progress {background-color: #f3f3f3; border-radius: 4px; padding: 3px;}
    .progress-bar {background-color: #4CAF50; height: 24px; border-radius: 4px; text-align: center; line-height: 24px; color: white;}
</style>";
echo "</head><body>";

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/autenticacao.php';

// Verificar se a requisição veio de um administrador
if (!temPermissao('administrador')) {
    die("<p class='error'>Acesso negado. Apenas administradores podem executar esta operação.</p></body></html>");
}

echo "<h1>Correção de Retorno de Capital</h1>";

// Verificar se estamos no modo de execução
$executar = isset($_GET['executar']) && $_GET['executar'] == 1;

if (!$executar) {
    echo "<p>Este script irá corrigir os registros de retorno de capital e comissões para empréstimos quitados que ainda não tiveram esses valores creditados.</p>";
    echo "<p>O script fará o seguinte:</p>";
    echo "<ol>";
    echo "<li>Identificar empréstimos completamente quitados</li>";
    echo "<li>Verificar se já existe um registro de retorno de capital para cada empréstimo</li>";
    echo "<li>Criar registros de retorno de capital e comissões faltantes</li>";
    echo "</ol>";
    echo "<p class='warning'><strong>Atenção:</strong> Este processo afetará o saldo das contas. Certifique-se de ter um backup do banco de dados antes de continuar.</p>";
    echo "<p><a href='?executar=1' class='btn btn-primary' style='padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Executar Correção</a></p>";
    echo "<p><a href='verificar_fluxo_capital.php' style='margin-right: 10px;'>Voltar para a verificação</a></p>";
} else {
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Buscar empréstimos quitados
        $sql = "SELECT 
                    e.id, 
                    e.cliente_id, 
                    c.nome as cliente_nome,
                    e.valor_emprestado, 
                    e.parcelas,
                    e.valor_parcela,
                    SUM(IFNULL(p.valor_pago, 0)) as total_pago,
                    COUNT(CASE WHEN p.status = 'pago' THEN 1 END) as parcelas_pagas,
                    c.indicacao as investidor_id,
                    u.nome as investidor_nome,
                    (SELECT COUNT(*) FROM movimentacoes_contas mc WHERE mc.descricao LIKE CONCAT('Retorno de capital - Empréstimo #', e.id, '%')) as tem_retorno
                FROM 
                    emprestimos e
                JOIN 
                    clientes c ON e.cliente_id = c.id
                JOIN 
                    parcelas p ON e.id = p.emprestimo_id
                LEFT JOIN 
                    usuarios u ON c.indicacao = u.id
                GROUP BY 
                    e.id
                HAVING 
                    COUNT(CASE WHEN p.status = 'pago' THEN 1 END) = e.parcelas
                    AND tem_retorno = 0";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Erro ao consultar empréstimos quitados: " . $conn->error);
        }
        
        $total_emprestimos = $result->num_rows;
        echo "<p>Encontrados $total_emprestimos empréstimos quitados sem registro de retorno de capital.</p>";
        
        $count = 0;
        $total_capital_retornado = 0;
        $total_comissoes = 0;
        
        if ($total_emprestimos > 0) {
            echo "<div class='progress'><div class='progress-bar' style='width: 0%'>0%</div></div>";
            
            while ($emprestimo = $result->fetch_assoc()) {
                $count++;
                $progresso = round(($count / $total_emprestimos) * 100);
                
                echo "<script>
                    document.getElementsByClassName('progress-bar')[0].style.width = '$progresso%';
                    document.getElementsByClassName('progress-bar')[0].innerText = '$progresso%';
                </script>";
                ob_flush();
                flush();
                
                // Verificar se o investidor tem uma conta
                $sql_conta = "SELECT id FROM contas WHERE usuario_id = ?";
                $stmt = $conn->prepare($sql_conta);
                $stmt->bind_param("i", $emprestimo['investidor_id']);
                $stmt->execute();
                $resultado_conta = $stmt->get_result();
                
                if ($resultado_conta->num_rows === 0) {
                    echo "<p class='warning'>Investidor ID {$emprestimo['investidor_id']} ({$emprestimo['investidor_nome']}) não possui conta. Criando conta...</p>";
                    
                    // Criar uma conta para o investidor
                    $sql_criar_conta = "INSERT INTO contas (usuario_id, nome, descricao, saldo_inicial, status) VALUES (?, ?, ?, 0, 'ativo')";
                    $stmt = $conn->prepare($sql_criar_conta);
                    $nome_conta = "Conta Principal";
                    $descricao = "Conta criada automaticamente";
                    $stmt->bind_param("iss", $emprestimo['investidor_id'], $nome_conta, $descricao);
                    $stmt->execute();
                    
                    $conta_id = $conn->insert_id;
                    echo "<p class='success'>Conta ID $conta_id criada para o investidor {$emprestimo['investidor_nome']}</p>";
                } else {
                    $conta = $resultado_conta->fetch_assoc();
                    $conta_id = $conta['id'];
                }
                
                // Calcular comissão (40% do lucro)
                $lucro = $emprestimo['total_pago'] - $emprestimo['valor_emprestado'];
                $comissao = $lucro * 0.4; // 40% do lucro
                
                echo "<p>Processando empréstimo #{$emprestimo['id']} - Cliente: {$emprestimo['cliente_nome']}</p>";
                
                // 1. Registrar retorno de capital
                $data_atual = date('Y-m-d');
                $descricao_retorno = "Retorno de capital - Empréstimo #{$emprestimo['id']} - Cliente: {$emprestimo['cliente_nome']}";
                
                $sql_retorno = "INSERT INTO movimentacoes_contas (conta_id, tipo, valor, descricao, data_movimentacao) VALUES (?, 'entrada', ?, ?, ?)";
                $stmt = $conn->prepare($sql_retorno);
                $stmt->bind_param("idss", $conta_id, $emprestimo['valor_emprestado'], $descricao_retorno, $data_atual);
                $stmt->execute();
                
                $total_capital_retornado += $emprestimo['valor_emprestado'];
                echo "<p class='success'>- Retorno de capital registrado: R$ " . number_format($emprestimo['valor_emprestado'], 2, ',', '.') . "</p>";
                
                // 2. Registrar comissão
                if ($comissao > 0) {
                    $descricao_comissao = "Comissão - Empréstimo #{$emprestimo['id']} - Cliente: {$emprestimo['cliente_nome']}";
                    
                    $sql_comissao = "INSERT INTO movimentacoes_contas (conta_id, tipo, valor, descricao, data_movimentacao) VALUES (?, 'entrada', ?, ?, ?)";
                    $stmt = $conn->prepare($sql_comissao);
                    $stmt->bind_param("idss", $conta_id, $comissao, $descricao_comissao, $data_atual);
                    $stmt->execute();
                    
                    $total_comissoes += $comissao;
                    echo "<p class='success'>- Comissão registrada: R$ " . number_format($comissao, 2, ',', '.') . "</p>";
                }
                
                // 3. Registrar na tabela retorno_capital se ela existir
                $sql_check_table = "SHOW TABLES LIKE 'retorno_capital'";
                $result_check = $conn->query($sql_check_table);
                
                if ($result_check->num_rows > 0) {
                    // A tabela existe, inserir o registro
                    $sql_insert_retorno = "INSERT INTO retorno_capital (
                        emprestimo_id, 
                        conta_id, 
                        valor_principal, 
                        valor_comissao, 
                        data_retorno, 
                        processado
                    ) VALUES (?, ?, ?, ?, ?, 1)";
                    
                    $stmt = $conn->prepare($sql_insert_retorno);
                    $stmt->bind_param("iidds", $emprestimo['id'], $conta_id, $emprestimo['valor_emprestado'], $comissao, $data_atual);
                    $stmt->execute();
                    
                    echo "<p class='success'>- Registro inserido na tabela retorno_capital</p>";
                }
            }
            
            // Comittar as alterações
            $conn->commit();
            
            echo "<div class='progress'><div class='progress-bar' style='width: 100%'>100%</div></div>";
            echo "<h2>Resumo da Operação</h2>";
            echo "<p class='success'>Operação concluída com sucesso!</p>";
            echo "<p>Total de empréstimos processados: $total_emprestimos</p>";
            echo "<p>Total de capital retornado: R$ " . number_format($total_capital_retornado, 2, ',', '.') . "</p>";
            echo "<p>Total de comissões: R$ " . number_format($total_comissoes, 2, ',', '.') . "</p>";
        } else {
            echo "<p>Nenhum empréstimo para processar.</p>";
        }
    } catch (Exception $e) {
        // Em caso de erro, reverte as alterações
        $conn->rollback();
        echo "<p class='error'>ERRO: " . $e->getMessage() . "</p>";
    }
    
    echo "<p><a href='verificar_fluxo_capital.php' style='padding: 10px 20px; background-color: #008CBA; color: white; text-decoration: none; border-radius: 5px;'>Voltar para Verificação</a></p>";
}

echo "</body></html>";
?> 