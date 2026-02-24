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
echo "<title>Correção de Comissões</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;}</style>";
echo "</head><body>";

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/autenticacao.php';

// Verificar se a requisição veio de um administrador
if (!temPermissao('administrador')) {
    die("<p class='error'>Acesso negado. Apenas administradores podem executar esta operação.</p></body></html>");
}

echo "<h1>Correção de Comissões</h1>";

// Registrar log
$log_file = __DIR__ . '/logs/correcao_comissoes.log';
function log_message($message, $type = 'info') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    
    $class = '';
    switch($type) {
        case 'error':
            $class = 'error';
            break;
        case 'warning':
            $class = 'warning';
            break;
        case 'success':
            $class = 'success';
            break;
    }
    
    echo "<p" . ($class ? " class='$class'" : "") . ">$message</p>";
    // Forçar output para o navegador
    ob_flush();
    flush();
}

log_message("Iniciando correção de comissões...");

// Iniciar transação
$conn->begin_transaction();

try {
    // 1. Buscar todas as comissões já processadas
    $sql = "SELECT 
                cc.id, 
                cc.parcela_id, 
                cc.usuario_id, 
                cc.conta_id, 
                p.emprestimo_id,  
                cc.valor_comissao,
                p.valor as valor_parcela,
                p.valor_pago,
                e.valor_emprestado,
                e.parcelas,
                mc.id as movimentacao_id
            FROM 
                controle_comissoes cc
            JOIN 
                parcelas p ON cc.parcela_id = p.id
            JOIN 
                emprestimos e ON p.emprestimo_id = e.id
            LEFT JOIN 
                movimentacoes_contas mc ON 
                mc.descricao LIKE CONCAT('Comissão - Parcela #', p.numero, ' do empréstimo #', p.emprestimo_id, '%')
                AND mc.conta_id = cc.conta_id
            ORDER BY 
                cc.id";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Erro ao buscar comissões: " . $conn->error);
    }
    
    $total_corrigido = 0;
    $total_diferenca = 0;
    
    log_message("Total de comissões encontradas: " . $result->num_rows);
    
    while ($comissao = $result->fetch_assoc()) {
        // Calcular o valor do principal para esta parcela
        $valor_principal_parcela = floatval($comissao['valor_emprestado']) / floatval($comissao['parcelas']);
        
        // Valor pago
        $valor_pago = isset($comissao['valor_pago']) && $comissao['valor_pago'] !== null ? 
                     floatval($comissao['valor_pago']) : 
                     floatval($comissao['valor_parcela']);
        
        // Calcular o lucro correto
        $lucro_correto = max(0, $valor_pago - $valor_principal_parcela);
        
        // Usar o percentual de 40% para comissão
        $percentual_comissao = 40;
        
        $comissao_correta = $lucro_correto * ($percentual_comissao / 100);
        
        // Comissão atual
        $comissao_atual = floatval($comissao['valor_comissao']);
        
        // Calcular a diferença
        $diferenca = $comissao_correta - $comissao_atual;
        
        // Se houver diferença, atualizar os valores
        if (abs($diferenca) > 0.01) { // Usar uma margem pequena para evitar problemas de arredondamento
            // Atualizar a tabela controle_comissoes
            $stmt = $conn->prepare("UPDATE controle_comissoes SET valor_comissao = ? WHERE id = ?");
            $stmt->bind_param("di", $comissao_correta, $comissao['id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar comissão ID {$comissao['id']}: " . $stmt->error);
            }
            
            // Verificar se temos o ID da movimentação
            if (!empty($comissao['movimentacao_id'])) {
                // Atualizar a tabela movimentacoes_contas
                $stmt = $conn->prepare("UPDATE movimentacoes_contas SET valor = ? WHERE id = ?");
                $stmt->bind_param("di", $comissao_correta, $comissao['movimentacao_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Erro ao atualizar movimentação ID {$comissao['movimentacao_id']}: " . $stmt->error);
                }
            } else {
                log_message("AVISO: Não foi encontrada movimentação para comissão ID {$comissao['id']}", 'warning');
            }
            
            $total_corrigido++;
            $total_diferenca += $diferenca;
            
            log_message("Corrigida comissão ID {$comissao['id']}: de R$ " . 
                      number_format($comissao_atual, 2, ',', '.') . " para R$ " . 
                      number_format($comissao_correta, 2, ',', '.') . " (diferença: R$ " . 
                      number_format($diferenca, 2, ',', '.') . ")");
        }
    }
    
    // 2. Atualizar o saldo das contas afetadas
    log_message("Atualizando saldos de contas...");
    
    // Não precisamos atualizar o saldo das contas pois ele é calculado dinamicamente
    // com base nas movimentações sempre que é consultado
    
    // Comittar as alterações
    $conn->commit();
    
    log_message("Correção concluída com sucesso! Total de comissões corrigidas: $total_corrigido", 'success');
    log_message("Diferença total nos saldos: R$ " . number_format($total_diferenca, 2, ',', '.'), 'success');
    
} catch (Exception $e) {
    // Em caso de erro, reverte as alterações
    $conn->rollback();
    log_message("ERRO: " . $e->getMessage(), 'error');
}

echo "<br><a href='investidor.php' class='btn btn-primary'>Voltar</a>";
echo "</body></html>";
?> 