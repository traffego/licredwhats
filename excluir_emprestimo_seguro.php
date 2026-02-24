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
echo "<title>Exclusão Segura de Empréstimo</title>";
echo "<style>
    body {font-family: Arial, sans-serif; padding: 20px; line-height: 1.6;}
    h1, h2 {color: #333;}
    .error {color: red; font-weight: bold;}
    .success {color: green; font-weight: bold;}
    .warning {color: orange; font-weight: bold;}
    .code {background-color: #f5f5f5; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace;}
    .table {width: 100%; border-collapse: collapse; margin: 15px 0;}
    .table th, .table td {border: 1px solid #ddd; padding: 8px; text-align: left;}
    .table th {background-color: #f2f2f2;}
    .btn {padding: 10px 15px; margin: 5px 0; cursor: pointer; border: none; border-radius: 5px; text-decoration: none; display: inline-block;}
    .btn-danger {background-color: #dc3545; color: white;}
    .btn-primary {background-color: #007bff; color: white;}
    .btn-secondary {background-color: #6c757d; color: white;}
</style>";
echo "</head><body>";

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/autenticacao.php';

// Verificar se o usuário tem permissão para acessar esta página
if (!temPermissao('administrador')) {
    echo "<div class='error'>Acesso negado. Você precisa ser um administrador para acessar esta página.</div>";
    echo "<p><a href='index.php' class='btn btn-secondary'>Voltar para a página inicial</a></p>";
    echo "</body></html>";
    exit;
}

// Função para listar as tabelas e contagens
function listarDependencias($conn, $emprestimo_id) {
    $tabelas = [
        'parcelas' => 'emprestimo_id',
        'controle_comissoes' => ['parcela_id' => 'SELECT id FROM parcelas WHERE emprestimo_id = ?'],
        'movimentacoes_contas' => ['descricao' => "Retorno de capital - Empréstimo #$emprestimo_id%", 'like' => true]
    ];
    
    $resultados = [];
    
    foreach ($tabelas as $tabela => $campo) {
        if (is_array($campo)) {
            // Caso especial para consultas mais complexas
            $chave = key($campo);
            $valor = $campo[$chave];
            
            if (isset($campo['like']) && $campo['like']) {
                // Para busca com LIKE
                $sql = "SELECT COUNT(*) as total FROM $tabela WHERE $chave LIKE ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $valor);
            } else {
                // Para consulta com subconsulta
                $stmt = $conn->prepare($valor);
                $stmt->bind_param("i", $emprestimo_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $ids = [];
                
                while ($row = $result->fetch_assoc()) {
                    $ids[] = $row['id'];
                }
                
                if (empty($ids)) {
                    $resultados[$tabela] = 0;
                    continue;
                }
                
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "SELECT COUNT(*) as total FROM $tabela WHERE $chave IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $types = str_repeat('i', count($ids));
                
                // Converter array para referências para bind_param
                $params = array_merge([$types], $ids);
                $refs = [];
                foreach($params as $key => $value) $refs[$key] = &$params[$key];
                call_user_func_array([$stmt, 'bind_param'], $refs);
            }
        } else {
            // Consulta simples direta
            $sql = "SELECT COUNT(*) as total FROM $tabela WHERE $campo = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $emprestimo_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $resultados[$tabela] = $result['total'];
    }
    
    return $resultados;
}

// Processar a exclusão se o ID foi enviado e confirmado
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $emprestimo_id = (int)$_GET['id'];
    
    // Buscar informações do empréstimo para exibição
    $sql = "SELECT e.*, c.nome as cliente_nome, u.nome as investidor_nome 
            FROM emprestimos e 
            LEFT JOIN clientes c ON e.cliente_id = c.id 
            LEFT JOIN usuarios u ON e.investidor_id = u.id 
            WHERE e.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $emprestimo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<div class='error'>Empréstimo não encontrado.</div>";
        echo "<p><a href='emprestimos/index.php' class='btn btn-secondary'>Voltar para a lista de empréstimos</a></p>";
        echo "</body></html>";
        exit;
    }
    
    $emprestimo = $result->fetch_assoc();
    
    // Verificar registros dependentes
    $dependencias = listarDependencias($conn, $emprestimo_id);
    
    // Se o usuário confirmou a exclusão, processar
    if (isset($_GET['confirmar']) && $_GET['confirmar'] == 1) {
        // Iniciar transação
        $conn->begin_transaction();
        
        try {
            // 1. Remover registros de controle_comissoes relacionados às parcelas deste empréstimo
            $sql = "DELETE cc FROM controle_comissoes cc 
                   INNER JOIN parcelas p ON cc.parcela_id = p.id 
                   WHERE p.emprestimo_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $emprestimo_id);
            $stmt->execute();
            
            // 2. Remover parcelas do empréstimo
            $sql = "DELETE FROM parcelas WHERE emprestimo_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $emprestimo_id);
            $stmt->execute();
            
            // 3. Remover movimentações relacionadas a este empréstimo
            $sql = "DELETE FROM movimentacoes_contas WHERE descricao LIKE ?";
            $stmt = $conn->prepare($sql);
            $descricao_like = "Retorno de capital - Empréstimo #$emprestimo_id%";
            $stmt->bind_param("s", $descricao_like);
            $stmt->execute();
            
            // 4. Finalmente, remover o empréstimo
            $sql = "DELETE FROM emprestimos WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $emprestimo_id);
            $stmt->execute();
            
            // Commit da transação
            $conn->commit();
            
            echo "<div class='success'>O empréstimo #$emprestimo_id foi excluído com sucesso, junto com todos os registros relacionados.</div>";
            echo "<p><a href='emprestimos/index.php' class='btn btn-primary'>Voltar para a lista de empréstimos</a></p>";
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            $conn->rollback();
            echo "<div class='error'>Erro ao excluir o empréstimo: " . $e->getMessage() . "</div>";
            echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
            echo "<p><a href='emprestimos/index.php' class='btn btn-secondary'>Voltar para a lista de empréstimos</a></p>";
        }
        
    } else {
        // Exibir informações do empréstimo e pedir confirmação
        echo "<h1>Excluir Empréstimo #$emprestimo_id</h1>";
        echo "<div class='warning'>Esta operação é irreversível. Todos os dados relacionados a este empréstimo serão excluídos.</div>";
        
        echo "<h2>Informações do Empréstimo</h2>";
        echo "<table class='table'>";
        echo "<tr><th>ID</th><td>$emprestimo_id</td></tr>";
        echo "<tr><th>Cliente</th><td>" . htmlspecialchars($emprestimo['cliente_nome']) . "</td></tr>";
        echo "<tr><th>Investidor</th><td>" . htmlspecialchars($emprestimo['investidor_nome']) . "</td></tr>";
        echo "<tr><th>Valor Emprestado</th><td>R$ " . number_format($emprestimo['valor_emprestado'], 2, ',', '.') . "</td></tr>";
        echo "<tr><th>Valor da Parcela</th><td>R$ " . number_format($emprestimo['valor_parcela'], 2, ',', '.') . "</td></tr>";
        echo "<tr><th>Total de Parcelas</th><td>" . $emprestimo['parcelas'] . "</td></tr>";
        echo "</table>";
        
        echo "<h2>Registros Relacionados</h2>";
        echo "<p>Os seguintes registros serão excluídos junto com este empréstimo:</p>";
        
        echo "<table class='table'>";
        echo "<tr><th>Tabela</th><th>Registros</th></tr>";
        foreach ($dependencias as $tabela => $total) {
            echo "<tr><td>$tabela</td><td>$total</td></tr>";
        }
        echo "</table>";
        
        echo "<div class='warning'>Atenção: A exclusão afetará todos os registros listados acima!</div>";
        
        echo "<p>
            <a href='excluir_emprestimo_seguro.php?id=$emprestimo_id&confirmar=1' class='btn btn-danger'>Confirmar Exclusão</a>
            <a href='emprestimos/index.php' class='btn btn-secondary'>Cancelar</a>
        </p>";
    }
    
} else {
    echo "<div class='error'>ID do empréstimo não especificado.</div>";
    echo "<p><a href='emprestimos/index.php' class='btn btn-secondary'>Voltar para a lista de empréstimos</a></p>";
}

echo "</body></html>";
?> 