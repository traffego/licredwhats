<?php
// Forçar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/autenticacao.php';

// Verificar se a requisição veio de um administrador
if (!temPermissao('administrador')) {
    die("Acesso negado. Apenas administradores podem executar esta operação.");
}

// Testar conexão
echo "<h1>Teste de Comissões</h1>";
echo "<p>Conexão com o banco de dados: " . ($conn ? "OK" : "FALHA") . "</p>";

// Testar query simples
$sql = "SELECT COUNT(*) as total FROM controle_comissoes";
$result = $conn->query($sql);
if (!$result) {
    echo "<p>Erro ao consultar controle_comissoes: " . $conn->error . "</p>";
} else {
    $row = $result->fetch_assoc();
    echo "<p>Total de registros em controle_comissoes: " . $row['total'] . "</p>";
}

// Testar query completa
echo "<h2>Testando query de correção:</h2>";
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
            cc.id
        LIMIT 5";

$result = $conn->query($sql);
if (!$result) {
    echo "<p>Erro ao executar query de teste: " . $conn->error . "</p>";
} else {
    echo "<p>Query executada com sucesso. Resultados:</p>";
    echo "<table border='1'>";
    
    // Cabeçalho
    echo "<tr>";
    while ($field = $result->fetch_field()) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    // Dados
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                echo "<td>" . ($value === null ? "NULL" : $value) . "</td>";
            }
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='10'>Nenhum resultado encontrado</td></tr>";
    }
    
    echo "</table>";
}

// Testar o log
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    echo "<p>Criando diretório de logs...</p>";
    mkdir($log_dir, 0777, true);
}

$log_file = $log_dir . '/teste.log';
echo "<p>Tentando criar arquivo de log: $log_file</p>";
$success = file_put_contents($log_file, date('Y-m-d H:i:s') . " - Teste de log\n", FILE_APPEND);
echo "<p>Resultado: " . ($success !== false ? "OK" : "FALHA") . "</p>";

if (!$success) {
    echo "<p>Erro de permissão ao escrever no log. Verificando permissões:</p>";
    echo "<p>Permissões do diretório logs: " . substr(sprintf('%o', fileperms($log_dir)), -4) . "</p>";
    
    if (file_exists($log_file)) {
        echo "<p>Permissões do arquivo de log: " . substr(sprintf('%o', fileperms($log_file)), -4) . "</p>";
    }
}

echo "<br><a href='investidor.php' class='btn btn-primary'>Voltar</a>";
?> 