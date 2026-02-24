<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/autenticacao.php';
require_once __DIR__ . '/../../includes/conexao.php';

// Validação dos dados recebidos
$emprestimo_id = filter_input(INPUT_GET, 'emprestimo_id', FILTER_VALIDATE_INT);
$parcela_numero = filter_input(INPUT_GET, 'parcela_numero', FILTER_VALIDATE_INT);

if (!$emprestimo_id || !$parcela_numero) {
    die(json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados inválidos para cobrança'
    ]));
}

// Busca o empréstimo com dados do cliente
$stmt = $conn->prepare("
    SELECT e.*, c.nome AS cliente_nome, c.telefone 
    FROM emprestimos e 
    JOIN clientes c ON e.cliente_id = c.id 
    WHERE e.id = ?
");
$stmt->bind_param("i", $emprestimo_id);
$stmt->execute();
$resultado = $stmt->get_result();
$emprestimo = $resultado->fetch_assoc();

if (!$emprestimo) {
    die(json_encode([
        'status' => 'erro',
        'mensagem' => 'Empréstimo não encontrado'
    ]));
}

// Decodifica o JSON das parcelas
$parcelas = json_decode($emprestimo['json_parcelas'], true);

// Encontra a parcela
$parcela = null;
foreach ($parcelas as $p) {
    if ($p['numero'] == $parcela_numero) {
        $parcela = $p;
        break;
    }
}

if (!$parcela) {
    die(json_encode([
        'status' => 'erro',
        'mensagem' => 'Parcela não encontrada'
    ]));
}

// Formata o telefone (remove tudo que não for número)
$telefone = preg_replace('/[^0-9]/', '', $emprestimo['telefone']);

// Monta a mensagem de cobrança
$mensagem = "Olá {$emprestimo['cliente_nome']}, ";
$mensagem .= "lembrando sobre a parcela {$parcela['numero']} do seu empréstimo no valor de R$ " . number_format($parcela['valor'], 2, ',', '.');
$mensagem .= " com vencimento em " . date('d/m/Y', strtotime($parcela['vencimento'])) . ". ";

if (strtotime($parcela['vencimento']) < time()) {
    $mensagem .= "Esta parcela está vencida. ";
}

$mensagem .= "Por favor, entre em contato para regularizar.";

// Codifica a mensagem para URL
$mensagem = urlencode($mensagem);

// Gera o link do WhatsApp
$link = "https://api.whatsapp.com/send?phone=55{$telefone}&text={$mensagem}";

echo json_encode([
    'status' => 'sucesso',
    'link' => $link
]); 