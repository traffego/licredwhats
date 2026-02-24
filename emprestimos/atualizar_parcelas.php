<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Busca todos os empréstimos
$sql = "SELECT id, json_parcelas FROM emprestimos";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $parcelas = json_decode($row['json_parcelas'], true);
        $novas_parcelas = [];
        
        foreach ($parcelas as $p) {
            $nova_parcela = [
                'numero' => $p['numero'],
                'vencimento' => $p['vencimento'],
                'valor' => $p['valor'],
                'status' => $p['status'],
                'valor_pago' => $p['valor_pago'] ?? 0,
                'data_pagamento' => $p['data_pagamento'] ?? null,
                'forma_pagamento' => $p['forma_pagamento'] ?? null
            ];
            
            $novas_parcelas[] = $nova_parcela;
        }
        
        // Atualiza o registro
        $novo_json = json_encode($novas_parcelas);
        $stmt = $conn->prepare("UPDATE emprestimos SET json_parcelas = ? WHERE id = ?");
        $stmt->bind_param("si", $novo_json, $row['id']);
        $stmt->execute();
    }
    
    echo "Atualização concluída com sucesso!";
} else {
    echo "Nenhum empréstimo encontrado.";
} 