<?php
/**
 * Arquivo com funções para manipulação de parcelas
 */

/**
 * Busca parcelas com base nos filtros fornecidos
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string $data_inicial Data inicial do período
 * @param string $data_final Data final do período
 * @param int $cliente_id ID do cliente (opcional)
 * @param string $status_filtro Status para filtrar (opcional)
 * @return array Array com as parcelas encontradas
 */
function buscarParcelas($conn, $data_inicial, $data_final, $cliente_id = 0, $status_filtro = '') {
    $sql = "
        SELECT DISTINCT
            p.id,
            p.emprestimo_id, 
            p.numero,
            p.vencimento,
            p.valor,
            p.valor_pago,
            p.status,
            e.cliente_id,
            c.nome as cliente_nome
        FROM 
            parcelas p
        INNER JOIN 
            emprestimos e ON p.emprestimo_id = e.id
        INNER JOIN 
            clientes c ON e.cliente_id = c.id
        WHERE 
            p.vencimento BETWEEN ? AND ?
    ";
    
    $params = [$data_inicial, $data_final];
    $tipos = 'ss';
    
    if ($cliente_id > 0) {
        $sql .= " AND e.cliente_id = ?";
        $params[] = $cliente_id;
        $tipos .= 'i';
    }
    
    $sql .= " ORDER BY p.vencimento ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $parcelas = [];
    $processedIds = []; // Para evitar duplicatas
    $hoje = date('Y-m-d');
    
    while ($row = $result->fetch_assoc()) {
        // Verificar se o ID já foi processado para evitar duplicatas
        if (in_array($row['id'], $processedIds)) {
            continue;
        }
        
        $processedIds[] = $row['id'];
        
        // Se a parcela já foi paga, mantém o status pago
        if ($row['status'] == 'pago') {
            $row['status'] = 'pago';
        }
        // Se a parcela tem valor pago menor que o valor total, é parcial
        elseif ($row['valor_pago'] > 0 && $row['valor_pago'] < $row['valor']) {
            $row['status'] = 'parcial';
        }
        // Se a parcela não foi paga, verifica o vencimento
        else {
            $data_vencimento = new DateTime($row['vencimento']);
            $data_hoje = new DateTime($hoje);
            
            // Zeramos as horas para comparar apenas as datas
            $data_vencimento->setTime(0, 0, 0);
            $data_hoje->setTime(0, 0, 0);
            
            if ($data_vencimento < $data_hoje) {
                $row['status'] = 'atrasado';
            } else {
                $row['status'] = 'pendente';
            }
        }
        
        $parcelas[] = $row;
    }
    
    // Aplicar filtro de status se necessário
    if ($status_filtro != '') {
        $parcelas = array_filter($parcelas, function($parcela) use ($status_filtro) {
            return $parcela['status'] == $status_filtro;
        });
        
        // Reindexar array após filtragem
        $parcelas = array_values($parcelas);
    }
    
    return $parcelas;
}

/**
 * Calcula o progresso de um empréstimo com base nas parcelas
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $emprestimo_id ID do empréstimo
 * @return array Array com informações do progresso
 */
function calcularProgressoEmprestimo($conn, $emprestimo_id) {
    $sql = "SELECT COUNT(*) as total_parcelas, 
                  SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as parcelas_pagas,
                  SUM(valor) as valor_total
             FROM parcelas 
             WHERE emprestimo_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $emprestimo_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Busca parcelas de um empréstimo específico
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $emprestimo_id ID do empréstimo
 * @return array Array com as parcelas do empréstimo
 */
function buscarParcelasEmprestimo($conn, $emprestimo_id) {
    $sql = "SELECT DISTINCT * FROM parcelas WHERE emprestimo_id = ? ORDER BY numero";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $emprestimo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $parcelas = [];
    $processedIds = []; // Para evitar duplicatas
    $hoje = date('Y-m-d');
    
    while ($row = $result->fetch_assoc()) {
        // Verificar se o ID já foi processado para evitar duplicatas
        if (in_array($row['id'], $processedIds)) {
            continue;
        }
        
        $processedIds[] = $row['id'];
        
        // Se a parcela já foi paga, mantém o status pago
        if ($row['status'] == 'pago') {
            $row['status'] = 'pago';
        }
        // Se a parcela tem valor pago menor que o valor total, é parcial
        elseif ($row['valor_pago'] > 0 && $row['valor_pago'] < $row['valor']) {
            $row['status'] = 'parcial';
        }
        // Se a parcela não foi paga, verifica o vencimento
        else {
            if (strtotime($row['vencimento']) < strtotime($hoje)) {
                $row['status'] = 'atrasado';
            } else {
                $row['status'] = 'pendente';
            }
        }
        
        $parcelas[] = $row;
    }
    
    return $parcelas;
}

/**
 * Atualiza o status de uma parcela
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $parcela_id ID da parcela
 * @param string $status Novo status da parcela
 * @param float $valor_pago Valor pago (opcional)
 * @return bool True se a atualização foi bem sucedida
 */
function atualizarStatusParcela($conn, $parcela_id, $status, $valor_pago = null) {
    $sql = "UPDATE parcelas SET status = ?";
    $params = [$status];
    $tipos = 's';
    
    if ($valor_pago !== null) {
        $sql .= ", valor_pago = ?";
        $params[] = $valor_pago;
        $tipos .= 'd';
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $parcela_id;
    $tipos .= 'i';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipos, ...$params);
    return $stmt->execute();
}

/**
 * Busca parcelas atrasadas
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $dias_atraso Número de dias de atraso (opcional)
 * @return array Array com as parcelas atrasadas
 */
function buscarParcelasAtrasadas($conn, $dias_atraso = 0) {
    $sql = "
        SELECT DISTINCT
            p.*,
            e.cliente_id,
            c.nome as cliente_nome,
            c.telefone,
            c.email
        FROM 
            parcelas p
        INNER JOIN 
            emprestimos e ON p.emprestimo_id = e.id
        INNER JOIN 
            clientes c ON e.cliente_id = c.id
        WHERE 
            p.status != 'pago'
            AND p.vencimento < CURDATE()
    ";
    
    if ($dias_atraso > 0) {
        $sql .= " AND DATEDIFF(CURDATE(), p.vencimento) >= ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $dias_atraso);
    } else {
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $parcelas = [];
    $processedIds = []; // Para evitar duplicatas
    
    while ($row = $result->fetch_assoc()) {
        // Verificar se o ID já foi processado para evitar duplicatas
        if (in_array($row['id'], $processedIds)) {
            continue;
        }
        
        $processedIds[] = $row['id'];
        $row['status'] = 'atrasado';
        $parcelas[] = $row;
    }
    
    return $parcelas;
} 