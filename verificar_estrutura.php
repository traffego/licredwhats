<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';

// Obter estrutura da tabela controle_comissoes
$sql = "DESCRIBE controle_comissoes";
$result = $conn->query($sql);

if (!$result) {
    die("Erro ao consultar estrutura: " . $conn->error);
}

echo "<h3>Estrutura da tabela controle_comissoes:</h3>";
echo "<table border='1'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Obter estrutura da tabela contas
$sql = "DESCRIBE contas";
$result = $conn->query($sql);

if (!$result) {
    die("Erro ao consultar estrutura de contas: " . $conn->error);
}

echo "<h3>Estrutura da tabela contas:</h3>";
echo "<table border='1'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Obter estrutura da tabela parcelas
$sql = "DESCRIBE parcelas";
$result = $conn->query($sql);

if (!$result) {
    die("Erro ao consultar estrutura de parcelas: " . $conn->error);
}

echo "<h3>Estrutura da tabela parcelas:</h3>";
echo "<table border='1'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Obter estrutura da tabela emprestimos
$sql = "DESCRIBE emprestimos";
$result = $conn->query($sql);

if (!$result) {
    die("Erro ao consultar estrutura de emprestimos: " . $conn->error);
}

echo "<h3>Estrutura da tabela emprestimos:</h3>";
echo "<table border='1'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Mostrar relação entre as tabelas
$sql = "SELECT p.id, p.emprestimo_id, p.numero, p.valor, p.status, cc.id as comissao_id 
        FROM parcelas p
        LEFT JOIN controle_comissoes cc ON p.id = cc.parcela_id
        LIMIT 5";
$result = $conn->query($sql);

if (!$result) {
    die("Erro ao consultar relação entre tabelas: " . $conn->error);
}

echo "<h3>Relação entre parcelas e controle_comissoes (primeiras 5 linhas):</h3>";
echo "<table border='1'>";
// Cabeçalho
echo "<tr>";
$fields = $result->fetch_fields();
foreach ($fields as $field) {
    echo "<th>" . $field->name . "</th>";
}
echo "</tr>";

// Dados
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $key => $value) {
        echo "<td>" . $value . "</td>";
    }
    echo "</tr>";
}
echo "</table>";
?> 