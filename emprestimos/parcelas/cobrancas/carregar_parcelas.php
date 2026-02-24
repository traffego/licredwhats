<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/autenticacao.php';
require_once __DIR__ . '/../../../includes/conexao.php';

// Parâmetros de paginação
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 25;
$offset = ($pagina - 1) * $por_pagina;

// Filtros
$filtros = [
    'status' => $_GET['status'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'cliente' => $_GET['cliente'] ?? '',
    'valor_min' => $_GET['valor_min'] ?? '',
    'valor_max' => $_GET['valor_max'] ?? '',
    'tipo_cobranca' => $_GET['tipo_cobranca'] ?? '',
    'ordem' => $_GET['ordem'] ?? 'prioridade', // prioridade, vencimento, valor, cliente
    'direcao' => $_GET['direcao'] ?? 'ASC'
];

// Prepara a query base
$sql = "
    SELECT 
        p.id as parcela_id,
        p.numero as parcela_numero,
        p.valor,
        p.valor_pago,
        p.vencimento,
        p.status,
        p.data_pagamento,
        p.forma_pagamento,
        e.id as emprestimo_id,
        e.valor_emprestado,
        e.parcelas,
        e.valor_parcela,
        e.tipo_de_cobranca,
        c.id as cliente_id,
        c.nome as cliente_nome,
        c.telefone
    FROM 
        parcelas p
    INNER JOIN 
        emprestimos e ON p.emprestimo_id = e.id
    INNER JOIN 
        clientes c ON e.cliente_id = c.id
    WHERE 
        1=1
";

// Query para contar o total
$sql_count = "
    SELECT COUNT(*) as total
    FROM 
        parcelas p
    INNER JOIN 
        emprestimos e ON p.emprestimo_id = e.id
    INNER JOIN 
        clientes c ON e.cliente_id = c.id
    WHERE 
        1=1
";

$parametros = [];
$tipos = '';

// Adiciona filtros
if (!empty($filtros['status'])) {
    $sql .= " AND p.status = ?";
    $sql_count .= " AND p.status = ?";
    $parametros[] = $filtros['status'];
    $tipos .= 's';
}

if (!empty($filtros['data_inicio'])) {
    $sql .= " AND p.vencimento >= ?";
    $sql_count .= " AND p.vencimento >= ?";
    $parametros[] = $filtros['data_inicio'];
    $tipos .= 's';
}

if (!empty($filtros['data_fim'])) {
    $sql .= " AND p.vencimento <= ?";
    $sql_count .= " AND p.vencimento <= ?";
    $parametros[] = $filtros['data_fim'];
    $tipos .= 's';
}

if (!empty($filtros['cliente'])) {
    $sql .= " AND c.nome LIKE ?";
    $sql_count .= " AND c.nome LIKE ?";
    $parametros[] = "%{$filtros['cliente']}%";
    $tipos .= 's';
}

if (!empty($filtros['valor_min'])) {
    $sql .= " AND p.valor >= ?";
    $sql_count .= " AND p.valor >= ?";
    $parametros[] = floatval($filtros['valor_min']);
    $tipos .= 'd';
}

if (!empty($filtros['valor_max'])) {
    $sql .= " AND p.valor <= ?";
    $sql_count .= " AND p.valor <= ?";
    $parametros[] = floatval($filtros['valor_max']);
    $tipos .= 'd';
}

if (!empty($filtros['tipo_cobranca'])) {
    $sql .= " AND e.tipo_de_cobranca = ?";
    $sql_count .= " AND e.tipo_de_cobranca = ?";
    $parametros[] = $filtros['tipo_cobranca'];
    $tipos .= 's';
}

// Adiciona ordenação
switch ($filtros['ordem']) {
    case 'vencimento':
        $sql .= " ORDER BY p.vencimento " . $filtros['direcao'];
        break;
    case 'valor':
        $sql .= " ORDER BY p.valor " . $filtros['direcao'];
        break;
    case 'cliente':
        $sql .= " ORDER BY c.nome " . $filtros['direcao'];
        break;
    default: // prioridade
        $sql .= " ORDER BY 
            CASE 
                WHEN p.status = 'atrasado' THEN 1
                WHEN p.status = 'pendente' AND p.vencimento < CURDATE() THEN 1
                WHEN p.status = 'pendente' THEN 3
                WHEN p.status = 'parcial' THEN 4
                WHEN p.status = 'pago' THEN 5
                ELSE 6
            END,
            p.vencimento ASC";
}

// Adiciona paginação
$sql .= " LIMIT ? OFFSET ?";
$parametros[] = $por_pagina;
$parametros[] = $offset;
$tipos .= 'ii';

// Executa query de contagem
$stmt_count = $conn->prepare($sql_count);
if (!empty($parametros)) {
    // Remove os últimos dois parâmetros que são da paginação
    $params_count = array_slice($parametros, 0, -2);
    $tipos_count = substr($tipos, 0, -2);
    if (!empty($params_count)) {
        $stmt_count->bind_param($tipos_count, ...$params_count);
    }
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Executa query principal
$stmt = $conn->prepare($sql);
if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$parcelas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Formata os dados para retorno
$dados = [];
foreach ($parcelas as $p) {
    $status_class = match($p['status']) {
        'pago' => 'success',
        'parcial' => 'warning',
        'atrasado' => 'danger',
        default => 'secondary'
    };
    
    $dados[] = [
        'id' => $p['parcela_id'],
        'cliente' => [
            'nome' => $p['cliente_nome'],
            'telefone' => $p['telefone']
        ],
        'emprestimo' => [
            'id' => $p['emprestimo_id'],
            'tipo' => $p['tipo_de_cobranca'],
            'total_parcelas' => $p['parcelas']
        ],
        'parcela' => [
            'numero' => $p['parcela_numero'],
            'valor' => number_format($p['valor'], 2, ',', '.'),
            'valor_pago' => $p['valor_pago'] ? number_format($p['valor_pago'], 2, ',', '.') : null,
            'vencimento' => date('d/m/Y', strtotime($p['vencimento'])),
            'status' => $p['status'],
            'status_class' => $status_class
        ]
    ];
}

// Retorna os dados em formato JSON
header('Content-Type: application/json');
echo json_encode([
    'dados' => $dados,
    'paginacao' => [
        'pagina_atual' => $pagina,
        'total_paginas' => $total_paginas,
        'total_registros' => $total_registros,
        'por_pagina' => $por_pagina
    ]
]);