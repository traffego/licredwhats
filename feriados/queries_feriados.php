<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';

/**
 * Função para buscar todos os feriados de um ano específico
 * Inclui feriados fixos de outros anos
 */
function buscarFeriadosPorAno(mysqli $conn, int $ano): array {
    // Buscar feriados do ano específico + feriados fixos de outros anos
    $sql = "SELECT * FROM feriados WHERE 
            ano = ? OR 
            (tipo = 'fixo' AND MONTH(data) IN (
                SELECT DISTINCT MONTH(data) FROM feriados WHERE tipo = 'fixo'
            ) AND DAY(data) IN (
                SELECT DISTINCT DAY(data) FROM feriados WHERE tipo = 'fixo'
            ))
            ORDER BY data ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ano);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $feriados = [];
    $feriados_adicionados = []; // Para controle de duplicidade
    
    while ($feriado = $resultado->fetch_assoc()) {
        $data_feriado = new DateTime($feriado['data']);
        $mes_dia = $data_feriado->format('m-d');
        
        // Se for feriado fixo de outro ano, ajusta para o ano atual
        if ($feriado['tipo'] == 'fixo' && $feriado['ano'] != $ano) {
            // Verificar se já existe um feriado com a mesma data no ano desejado
            if (in_array($mes_dia, $feriados_adicionados)) {
                continue; // Pula se já existir
            }
            
            // Ajustar a data para o ano solicitado
            $nova_data = $ano . '-' . $data_feriado->format('m-d');
            $feriado['data'] = $nova_data;
            $feriado['ano'] = $ano; // Atualiza o ano para exibição
        }
        
        // Adiciona ao controle para evitar duplicatas
        $feriados_adicionados[] = $mes_dia;
        $feriados[] = $feriado;
    }
    
    // Ordena novamente pela data ajustada
    usort($feriados, function($a, $b) {
        return strtotime($a['data']) - strtotime($b['data']);
    });
    
    return $feriados;
}

/**
 * Função para buscar um feriado específico pelo ID
 */
function buscarFeriadoPorId(mysqli $conn, int $id): array|null {
    $stmt = $conn->prepare("SELECT * FROM feriados WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        return null;
    }
    
    return $resultado->fetch_assoc();
}

/**
 * Função para inserir um novo feriado
 */
function inserirFeriado(mysqli $conn, array $dados): int|false {
    $stmt = $conn->prepare("INSERT INTO feriados (nome, data, tipo, evitar, local, ano) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", 
        $dados['nome'], 
        $dados['data'], 
        $dados['tipo'], 
        $dados['evitar'], 
        $dados['local'], 
        $dados['ano']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        return false;
    }
}

/**
 * Função para atualizar um feriado existente
 */
function atualizarFeriado(mysqli $conn, int $id, array $dados): bool {
    $stmt = $conn->prepare("UPDATE feriados SET nome = ?, data = ?, tipo = ?, evitar = ?, local = ?, ano = ? WHERE id = ?");
    $stmt->bind_param("sssssii", 
        $dados['nome'], 
        $dados['data'], 
        $dados['tipo'], 
        $dados['evitar'], 
        $dados['local'], 
        $dados['ano'],
        $id
    );
    
    return $stmt->execute();
}

/**
 * Função para excluir um feriado
 */
function excluirFeriado(mysqli $conn, int $id): bool {
    $stmt = $conn->prepare("DELETE FROM feriados WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

/**
 * Função para verificar se uma data é feriado
 */
function verificarSeDataEFeriado(mysqli $conn, string $data): array|null {
    $stmt = $conn->prepare("SELECT * FROM feriados WHERE data = ?");
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        return null;
    }
    
    return $resultado->fetch_assoc();
}

/**
 * Função para buscar feriados a serem evitados
 */
function buscarFeriadosParaEvitar(mysqli $conn): array {
    $stmt = $conn->prepare("SELECT * FROM feriados WHERE evitar = 'sim_evitar' ORDER BY data ASC");
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $feriados = [];
    while ($feriado = $resultado->fetch_assoc()) {
        $feriados[] = $feriado;
    }
    
    return $feriados;
}

/**
 * Função para atualizar o campo "evitar" de um feriado
 */
function atualizarEvitarFeriado(mysqli $conn, int $id, string $evitar): bool {
    $stmt = $conn->prepare("UPDATE feriados SET evitar = ? WHERE id = ?");
    $stmt->bind_param("si", $evitar, $id);
    return $stmt->execute();
} 