<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexao.php';

// TRATAR EMPRESTIMOS

function buscarResumoEmprestimoId(mysqli $conn, int $id): array|null {
    $stmt = $conn->prepare("SELECT id, tipo, valor_emprestado FROM emprestimos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($linha = $resultado->fetch_assoc()) {
        $valor_emprestado = (float) $linha['valor_emprestado'];
        
        // Busca as parcelas na tabela parcelas em vez do JSON
        $stmt_parcelas = $conn->prepare("SELECT 
            numero, valor, status, valor_pago, data_pagamento, forma_pagamento 
            FROM parcelas WHERE emprestimo_id = ? ORDER BY numero");
        $stmt_parcelas->bind_param("i", $id);
        $stmt_parcelas->execute();
        $result_parcelas = $stmt_parcelas->get_result();
        
        $parcelas = [];
        $total_previsto = 0;
        $total_pago = 0;
        
        while ($p = $result_parcelas->fetch_assoc()) {
            $parcelas[] = $p;
            $valor = (float) $p['valor'];
            $total_previsto += $valor;
            
            if ($p['status'] === 'pago' || $p['status'] === 'parcial') {
                $total_pago += (float) ($p['valor_pago'] ?? $p['valor']);
            }
        }

        $lucro_previsto = $total_previsto - $valor_emprestado;
        $falta = $total_previsto - $total_pago;

        return [
            'id' => $linha['id'],
            'valor_emprestado' => $valor_emprestado,
            'total_previsto' => $total_previsto,
            'total_pago' => $total_pago,
            'lucro_previsto' => $lucro_previsto,
            'falta' => $falta,
            'parcelas' => $parcelas
        ];
    }

    return null;
}

function buscarTodosEmprestimosComCliente(mysqli $conn, int $pagina = 1, int $por_pagina = 10, array $filtros = []): array {
    // Calcula o offset
    $offset = ($pagina - 1) * $por_pagina;
    
    // Prepara as condições WHERE base
    $where_conditions = ["(e.status != 'inativo' OR e.status IS NULL)"];
    $params = [];
    $param_types = "";
    
    // Adiciona filtro de busca por cliente
    if (!empty($filtros['busca'])) {
        $where_conditions[] = "c.nome LIKE ?";
        $params[] = "%" . $filtros['busca'] . "%";
        $param_types .= "s";
    }
    
    // Adiciona filtro de tipo de cobrança
    if (!empty($filtros['tipo'])) {
        $where_conditions[] = "e.tipo_de_cobranca = ?";
        $params[] = $filtros['tipo'];
        $param_types .= "s";
    }
    
    // Primeiro, vamos buscar o total de registros para a paginação
    $sql_total = "SELECT COUNT(*) as total 
                  FROM emprestimos e 
                  JOIN clientes c ON e.cliente_id = c.id
                  WHERE " . implode(" AND ", $where_conditions);
    
    $stmt_total = $conn->prepare($sql_total);
    if (!empty($params)) {
        $stmt_total->bind_param($param_types, ...$params);
    }
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $row_total = $result_total->fetch_assoc();
    $total_registros = $row_total['total'];
    
    // Query principal com LIMIT e OFFSET
    $sql = "SELECT 
                e.id,
                e.cliente_id,
                e.investidor_id,
                e.tipo_de_cobranca,
                e.valor_emprestado,
                e.parcelas,
                e.valor_parcela,
                e.juros_percentual,
                e.data_inicio,
                e.configuracao,
                e.data_criacao,
                e.data_atualizacao,
                e.status,
                c.nome AS cliente_nome,
                u.nome AS investidor_nome
            FROM emprestimos e 
            JOIN clientes c ON e.cliente_id = c.id
            LEFT JOIN usuarios u ON e.investidor_id = u.id
            WHERE " . implode(" AND ", $where_conditions);

    // Adiciona ordenação
    if (!empty($filtros['ordem'])) {
        $sql .= " ORDER BY " . $filtros['ordem'];
    } else {
        $sql .= " ORDER BY e.id DESC";
    }
    
    // Adiciona LIMIT apenas se por_pagina não for -1 (todos)
    if ($por_pagina > 0) {
        $sql .= " LIMIT ? OFFSET ?";
        $param_types .= "ii";
        $params[] = $por_pagina;
        $params[] = $offset;
    }
            
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $lista = [];

    while ($e = $result->fetch_assoc()) {
        // Adiciona os dados básicos
        $emprestimo = $e;
        
        // Busca as parcelas na tabela parcelas
        $stmt_parcelas = $conn->prepare("SELECT 
            status, valor, valor_pago
            FROM parcelas 
            WHERE emprestimo_id = ?");
        $stmt_parcelas->bind_param("i", $e['id']);
        $stmt_parcelas->execute();
        $result_parcelas = $stmt_parcelas->get_result();
        
        $total_previsto = 0;
        $total_pago = 0;
        $parcelas_pagas = 0;
        
        while ($p = $result_parcelas->fetch_assoc()) {
            $valor = (float) $p['valor'];
            $total_previsto += $valor;
            
            if ($p['status'] === 'pago') {
                $total_pago += $valor;
                $parcelas_pagas++;
            } elseif ($p['status'] === 'parcial' && isset($p['valor_pago'])) {
                $total_pago += (float) $p['valor_pago'];
            }
        }
        
        // Adiciona os totais calculados
        $emprestimo['total_previsto'] = $total_previsto;
        $emprestimo['total_pago'] = $total_pago;
        $emprestimo['parcelas_pagas'] = $parcelas_pagas;
        
        // Aplica filtro de status se necessário
        if (!empty($filtros['status'])) {
            $status_atual = calcularStatusEmprestimo($emprestimo);
            if ($status_atual !== $filtros['status']) {
                continue;
            }
        }
        
        $lista[] = $emprestimo;
    }

    return [
        'emprestimos' => $lista,
        'total_registros' => $total_registros,
        'pagina_atual' => $pagina,
        'por_pagina' => $por_pagina,
        'total_paginas' => $por_pagina > 0 ? ceil($total_registros / $por_pagina) : 1
    ];
}

// Função auxiliar para calcular o status do empréstimo
function calcularStatusEmprestimo($emprestimo) {
    if ($emprestimo['total_pago'] >= $emprestimo['total_previsto']) {
        return 'quitado';
    }
    
    // Verifica se tem parcelas atrasadas
    $stmt = $GLOBALS['conn']->prepare("
        SELECT COUNT(*) as total 
        FROM parcelas 
        WHERE emprestimo_id = ? 
        AND status IN ('pendente', 'parcial') 
        AND vencimento < CURRENT_DATE
    ");
    $stmt->bind_param("i", $emprestimo['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['total'] > 0) {
        return 'atrasado';
    }
    
    return 'ativo';
}

function calcularTotalParcelasAtrasadas(mysqli $conn) {
    $total_atrasado = 0;
    
    $sql = "SELECT 
                SUM(valor) as total 
            FROM 
                parcelas 
            WHERE 
                status = 'pendente' 
                AND vencimento < CURRENT_DATE";
                
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($linha = $resultado->fetch_assoc()) {
        $total_atrasado = (float) $linha['total'];
    }

    return $total_atrasado;
}

function contarEmprestimosAtivos(mysqli $conn) {
    // Primeiro, vamos buscar todos os empréstimos que não estão explicitamente inativos
    $sql = "SELECT 
                e.id,
                e.status,
                COUNT(p.id) as total_parcelas,
                SUM(CASE WHEN p.status = 'pago' THEN 1 ELSE 0 END) as parcelas_pagas,
                SUM(CASE 
                    WHEN p.status IN ('pendente', 'parcial') AND p.vencimento < CURRENT_DATE THEN 1 
                    ELSE 0 
                END) as parcelas_atrasadas
            FROM 
                emprestimos e
            LEFT JOIN 
                parcelas p ON e.id = p.emprestimo_id
            WHERE 
                (e.status = 'ativo' OR e.status IS NULL)
            GROUP BY 
                e.id, e.status
            HAVING 
                parcelas_pagas < total_parcelas";
                
    $resultado = $conn->query($sql);
    
    if (!$resultado) {
        error_log("Erro ao contar empréstimos ativos: " . $conn->error);
        return 0;
    }
    
    return $resultado->num_rows;
}

function buscarTodosClientes(mysqli $conn): array {
    $sql = "SELECT c.id, c.nome, c.indicacao, u.nome as investidor_nome 
           FROM clientes c 
           LEFT JOIN usuarios u ON c.indicacao = u.id 
           ORDER BY c.nome ASC";
    
    $stmt = $conn->query($sql);
    return $stmt->fetch_all(MYSQLI_ASSOC);
}

function buscarEmprestimoPorId(mysqli $conn, int $id) {
    $sql = "SELECT 
                e.*,
                c.nome as cliente_nome,
                u.nome as investidor_nome
            FROM emprestimos e 
            INNER JOIN clientes c ON e.cliente_id = c.id 
            LEFT JOIN usuarios u ON e.investidor_id = u.id
            WHERE e.id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        return null;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return null;
    }
    
    $emprestimo = $result->fetch_assoc();
    
    // Adiciona informação se é inativo
    if ($emprestimo['status'] === 'inativo') {
        $emprestimo['esta_inativo'] = true;
    } else {
        $emprestimo['esta_inativo'] = false;
    }
    
    return $emprestimo;
}

function contarTotalEmprestimos(mysqli $conn) {
    $sql = "SELECT COUNT(id) as total FROM emprestimos";
    $resultado = $conn->query($sql);
    
    if (!$resultado) {
        error_log("Erro ao contar total de empréstimos: " . $conn->error);
        return 0;
    }
    
    $linha = $resultado->fetch_assoc();
    return (int) $linha['total'];
}