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
echo "<title>Verificação de Fluxo de Capital</title>";
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

echo "<h1>Verificação de Fluxo de Capital</h1>";

// 1. Verificar empréstimos quitados
echo "<h2>Empréstimos Quitados</h2>";
$sql = "SELECT 
            e.id, 
            e.cliente_id, 
            c.nome as cliente_nome,
            e.valor_emprestado, 
            e.parcelas,
            e.valor_parcela,
            SUM(IFNULL(p.valor_pago, 0)) as total_pago,
            COUNT(CASE WHEN p.status = 'pago' THEN 1 END) as parcelas_pagas,
            u.id as investidor_id,
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
            COUNT(CASE WHEN p.status = 'pago' THEN 1 END) = e.parcelas";

$result = $conn->query($sql);

if (!$result) {
    echo "<p class='error'>Erro ao consultar empréstimos quitados: " . $conn->error . "</p>";
} else {
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Investidor</th>
                <th>Valor Emprestado</th>
                <th>Parcelas</th>
                <th>Total Pago</th>
                <th>Lucro</th>
                <th>Retorno Registrado</th>
              </tr>";
        
        while ($row = $result->fetch_assoc()) {
            $lucro = $row['total_pago'] - $row['valor_emprestado'];
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['cliente_nome']}</td>";
            echo "<td>{$row['investidor_nome']} (ID: {$row['investidor_id']})</td>";
            echo "<td>R$ " . number_format($row['valor_emprestado'], 2, ',', '.') . "</td>";
            echo "<td>{$row['parcelas_pagas']}/{$row['parcelas']}</td>";
            echo "<td>R$ " . number_format($row['total_pago'], 2, ',', '.') . "</td>";
            echo "<td>R$ " . number_format($lucro, 2, ',', '.') . "</td>";
            echo "<td>" . ($row['tem_retorno'] > 0 ? "<span class='success'>Sim</span>" : "<span class='error'>Não</span>") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum empréstimo quitado encontrado.</p>";
    }
}

// 2. Verificar retornos de capital
echo "<h2>Retornos de Capital Registrados</h2>";
$sql = "SELECT 
            mc.id,
            mc.conta_id,
            c.nome as conta_nome,
            c.usuario_id,
            u.nome as usuario_nome,
            mc.tipo,
            mc.valor,
            mc.descricao,
            mc.data_movimentacao
        FROM 
            movimentacoes_contas mc
        JOIN
            contas c ON mc.conta_id = c.id
        JOIN
            usuarios u ON c.usuario_id = u.id
        WHERE 
            mc.descricao LIKE 'Retorno de capital - Empréstimo #%'
        ORDER BY 
            mc.data_movimentacao DESC
        LIMIT 20";

$result = $conn->query($sql);

if (!$result) {
    echo "<p class='error'>Erro ao consultar retornos de capital: " . $conn->error . "</p>";
} else {
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Conta</th>
                <th>Usuário</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Descrição</th>
                <th>Data</th>
              </tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['conta_nome']} (ID: {$row['conta_id']})</td>";
            echo "<td>{$row['usuario_nome']} (ID: {$row['usuario_id']})</td>";
            echo "<td>{$row['tipo']}</td>";
            echo "<td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td>";
            echo "<td>{$row['descricao']}</td>";
            echo "<td>{$row['data_movimentacao']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum retorno de capital encontrado.</p>";
    }
}

// 3. Verificar comissões
echo "<h2>Comissões Registradas</h2>";
$sql = "SELECT 
            mc.id,
            mc.conta_id,
            c.nome as conta_nome,
            c.usuario_id,
            u.nome as usuario_nome,
            mc.tipo,
            mc.valor,
            mc.descricao,
            mc.data_movimentacao
        FROM 
            movimentacoes_contas mc
        JOIN
            contas c ON mc.conta_id = c.id
        JOIN
            usuarios u ON c.usuario_id = u.id
        WHERE 
            mc.descricao LIKE 'Comissão%'
        ORDER BY 
            mc.data_movimentacao DESC
        LIMIT 20";

$result = $conn->query($sql);

if (!$result) {
    echo "<p class='error'>Erro ao consultar comissões: " . $conn->error . "</p>";
} else {
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Conta</th>
                <th>Usuário</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Descrição</th>
                <th>Data</th>
              </tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['conta_nome']} (ID: {$row['conta_id']})</td>";
            echo "<td>{$row['usuario_nome']} (ID: {$row['usuario_id']})</td>";
            echo "<td>{$row['tipo']}</td>";
            echo "<td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td>";
            echo "<td>{$row['descricao']}</td>";
            echo "<td>{$row['data_movimentacao']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhuma comissão encontrada.</p>";
    }
}

// Retorno de capital agora é controlado apenas pela tabela movimentacoes_contas

echo "<h2>Ações Disponíveis</h2>";
echo "<p><a href='corrigir_retorno_capital.php' class='btn btn-primary'>Corrigir Retorno de Capital</a> (Cria registros de retorno para empréstimos quitados)</p>";

echo "<br><a href='investidor.php' class='btn btn-primary'>Voltar</a>";
echo "</body></html>";
?> 