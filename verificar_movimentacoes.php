<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';

// Consultar as movimentações de contas
$sql = "SELECT id, conta_id, tipo, valor, descricao, data_movimentacao 
        FROM movimentacoes_contas 
        ORDER BY id DESC 
        LIMIT 20";
$result = $conn->query($sql);

echo "<h2>Movimentações de Conta (Últimas 20)</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Conta ID</th><th>Tipo</th><th>Valor</th><th>Descrição</th><th>Data</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['conta_id']}</td>";
    echo "<td>{$row['tipo']}</td>";
    echo "<td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td>";
    echo "<td>{$row['descricao']}</td>";
    echo "<td>{$row['data_movimentacao']}</td>";
    echo "</tr>";
}
echo "</table>";

// Consultar contas e seus saldos
$sql_contas = "SELECT 
                c.id, 
                c.nome, 
                c.usuario_id,
                c.saldo_inicial,
                (
                    SELECT COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0)
                    FROM movimentacoes_contas
                    WHERE conta_id = c.id
                ) as total_movimentacoes,
                c.saldo_inicial + (
                    SELECT COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0)
                    FROM movimentacoes_contas
                    WHERE conta_id = c.id
                ) as saldo_atual
               FROM contas c
               ORDER BY c.id";
$result_contas = $conn->query($sql_contas);

echo "<h2>Contas e Saldos</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nome</th><th>Usuário ID</th><th>Saldo Inicial</th><th>Total Movimentações</th><th>Saldo Atual</th></tr>";

while ($row = $result_contas->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['nome']}</td>";
    echo "<td>{$row['usuario_id']}</td>";
    echo "<td>R$ " . number_format($row['saldo_inicial'], 2, ',', '.') . "</td>";
    echo "<td>R$ " . number_format($row['total_movimentacoes'], 2, ',', '.') . "</td>";
    echo "<td>R$ " . number_format($row['saldo_atual'], 2, ',', '.') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Consultar empréstimos específicos
$sql_emprestimos = "SELECT 
                        e.id, 
                        e.cliente_id, 
                        e.investidor_id, 
                        e.valor_emprestado, 
                        e.data_criacao,
                        u.nome as investidor_nome,
                        c.nome as cliente_nome
                   FROM emprestimos e
                   JOIN usuarios u ON e.investidor_id = u.id
                   JOIN clientes c ON e.cliente_id = c.id
                   ORDER BY e.id DESC
                   LIMIT 10";
$result_emprestimos = $conn->query($sql_emprestimos);

echo "<h2>Empréstimos Recentes (Últimos 10)</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Cliente</th><th>Investidor</th><th>Valor</th><th>Data Criação</th></tr>";

while ($row = $result_emprestimos->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['cliente_nome']} (ID: {$row['cliente_id']})</td>";
    echo "<td>{$row['investidor_nome']} (ID: {$row['investidor_id']})</td>";
    echo "<td>R$ " . number_format($row['valor_emprestado'], 2, ',', '.') . "</td>";
    echo "<td>{$row['data_criacao']}</td>";
    echo "</tr>";
}
echo "</table>";

// Consultar pagamentos de parcelas
$sql_pagamentos = "SELECT 
                      p.id,
                      p.emprestimo_id,
                      p.valor,
                      p.valor_pago,
                      p.status,
                      p.data_pagamento,
                      e.valor_emprestado,
                      e.investidor_id
                  FROM parcelas p
                  JOIN emprestimos e ON p.emprestimo_id = e.id
                  WHERE p.status IN ('pago', 'parcial')
                  ORDER BY p.data_pagamento DESC
                  LIMIT 20";
$result_pagamentos = $conn->query($sql_pagamentos);

echo "<h2>Pagamentos Recentes (Últimos 20)</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID Parcela</th><th>Empréstimo ID</th><th>Valor Original</th><th>Valor Pago</th><th>Status</th><th>Data Pagamento</th><th>Investidor ID</th></tr>";

while ($row = $result_pagamentos->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['emprestimo_id']}</td>";
    echo "<td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td>";
    echo "<td>R$ " . number_format($row['valor_pago'] ?? 0, 2, ',', '.') . "</td>";
    echo "<td>{$row['status']}</td>";
    echo "<td>{$row['data_pagamento']}</td>";
    echo "<td>{$row['investidor_id']}</td>";
    echo "</tr>";
}
echo "</table>";

// Retornos de capital agora são controlados apenas pela tabela movimentacoes_contas
// Buscar movimentações de retorno de capital
$sql_retornos = "SELECT 
                    mc.id,
                    mc.conta_id,
                    mc.valor,
                    mc.descricao,
                    mc.data_movimentacao,
                    u.id as usuario_id,
                    u.nome as usuario_nome
                 FROM 
                    movimentacoes_contas mc
                 INNER JOIN
                    contas c ON mc.conta_id = c.id
                 INNER JOIN
                    usuarios u ON c.usuario_id = u.id
                 WHERE 
                    mc.tipo = 'entrada'
                    AND mc.descricao LIKE 'Retorno de capital - Empréstimo #%'
                 ORDER BY 
                    mc.id DESC 
                 LIMIT 10";

$result_retornos = $conn->query($sql_retornos);

echo "<h2>Retornos de Capital (Últimos 10)</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Usuário</th><th>Conta ID</th><th>Valor</th><th>Descrição</th><th>Data</th></tr>";

if ($result_retornos && $result_retornos->num_rows > 0) {
    while ($row = $result_retornos->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['usuario_nome']} (ID: {$row['usuario_id']})</td>";
        echo "<td>{$row['conta_id']}</td>";
        echo "<td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td>";
        echo "<td>{$row['descricao']}</td>";
        echo "<td>{$row['data_movimentacao']}</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6'>Nenhum retorno de capital registrado</td></tr>";
}
echo "</table>"; 