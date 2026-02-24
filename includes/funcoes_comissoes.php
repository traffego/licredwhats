<?php
/**
 * Funções para cálculo e processamento de comissões
 */

/**
 * Calcula a previsão de comissões para um empréstimo
 * Retorna informações sobre valores previstos e realizados
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $emprestimo_id ID do empréstimo
 * @return array|null Array com informações ou null se empréstimo não encontrado
 */
function calcularPrevisaoComissoes($conn, $emprestimo_id) {
    // Busca informações do empréstimo e investidor
    $sql = "
        SELECT 
            e.id,
            e.valor_emprestado,
            e.investidor_id,
            c.id as conta_id,
            c.comissao as percentual_comissao,
            (SELECT SUM(valor) FROM parcelas WHERE emprestimo_id = e.id) as valor_total_parcelas,
            (SELECT SUM(valor_pago) FROM parcelas WHERE emprestimo_id = e.id AND status = 'pago') as valor_ja_pago,
            (SELECT COUNT(*) FROM parcelas WHERE emprestimo_id = e.id AND status = 'pago') as parcelas_pagas,
            (SELECT COUNT(*) FROM parcelas WHERE emprestimo_id = e.id) as total_parcelas,
            (
                SELECT COUNT(*) 
                FROM movimentacoes_contas 
                WHERE descricao LIKE CONCAT('Comissão total - Empréstimo #', e.id, '%')
                AND conta_id = c.id
            ) as comissao_processada
        FROM 
            emprestimos e
        JOIN 
            usuarios u ON e.investidor_id = u.id
        JOIN 
            contas c ON u.id = c.usuario_id
        WHERE 
            e.id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $emprestimo_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        return null;
    }

    // Calcula valores
    $valor_emprestado = floatval($result['valor_emprestado']);
    $valor_total_parcelas = floatval($result['valor_total_parcelas']);
    $valor_ja_pago = floatval($result['valor_ja_pago'] ?? 0);
    $percentual_comissao = floatval($result['percentual_comissao']);
    
    // Calcula lucros
    $lucro_total = $valor_total_parcelas - $valor_emprestado;
    $lucro_ja_realizado = max(0, $valor_ja_pago - $valor_emprestado);

    // Calcula comissões
    $comissao_total_prevista = $lucro_total * ($percentual_comissao / 100);
    $comissao_ja_realizada = $lucro_ja_realizado * ($percentual_comissao / 100);

    // Calcula parte do administrador
    $comissao_admin_prevista = $lucro_total - $comissao_total_prevista;
    $comissao_admin_realizada = $lucro_ja_realizado - $comissao_ja_realizada;

    return [
        'emprestimo' => [
            'id' => $result['id'],
            'valor_emprestado' => $valor_emprestado,
            'valor_total_parcelas' => $valor_total_parcelas,
            'valor_ja_pago' => $valor_ja_pago,
            'parcelas_pagas' => $result['parcelas_pagas'],
            'total_parcelas' => $result['total_parcelas']
        ],
        'investidor' => [
            'id' => $result['investidor_id'],
            'conta_id' => $result['conta_id'],
            'percentual_comissao' => $percentual_comissao,
            'comissao_prevista' => $comissao_total_prevista,
            'comissao_realizada' => $comissao_ja_realizada
        ],
        'administrador' => [
            'comissao_prevista' => $comissao_admin_prevista,
            'comissao_realizada' => $comissao_admin_realizada
        ],
        'lucro' => [
            'total_previsto' => $lucro_total,
            'ja_realizado' => $lucro_ja_realizado
        ],
        'status' => [
            'todas_parcelas_pagas' => ($result['parcelas_pagas'] == $result['total_parcelas']),
            'comissao_processada' => ($result['comissao_processada'] > 0)
        ]
    ];
}

/**
 * Verifica se há uma transação ativa na conexão
 * @param mysqli $conn Conexão com o banco de dados
 * @return bool True se houver uma transação ativa, false caso contrário
 */
function hasActiveTransaction($conn) {
    try {
        // Tenta iniciar uma transação. Se já houver uma, retornará false
        $result = $conn->begin_transaction();
        if ($result) {
            // Se conseguiu iniciar, faz rollback e retorna false (não havia transação)
            $conn->rollback();
            return false;
        }
        // Se não conseguiu iniciar, é porque já existe uma transação
        return true;
    } catch (Exception $e) {
        // Se der erro, assume que não há transação
        return false;
    }
}

/**
 * Verifica se já existe comissão processada para um empréstimo
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $emprestimo_id ID do empréstimo
 * @param int|null $parcela_id ID da parcela (opcional)
 * @return bool|array false se não existir, ou array com dados do processamento se existir
 */
function verificarComissaoProcessada($conn, $emprestimo_id, $parcela_id = null) {
    $sql = "SELECT 
            cc.*,
            u.nome as usuario_nome,
            e.valor_emprestado,
            cl.nome as cliente_nome
        FROM 
            controle_comissoes cc
        JOIN 
            usuarios u ON cc.usuario_id = u.id
        JOIN 
            emprestimos e ON cc.emprestimo_id = e.id
        JOIN 
            clientes cl ON e.cliente_id = cl.id
        WHERE 
            cc.emprestimo_id = ?";
    
    // Se uma parcela específica foi informada, verifica apenas ela
    $params = array($emprestimo_id);
    $types = "i";
    
    if ($parcela_id !== null) {
        $sql .= " AND cc.parcela_id = ?";
        $params[] = $parcela_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY cc.data_processamento DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $processamentos = [];
    while ($row = $result->fetch_assoc()) {
        $processamentos[] = $row;
    }
    
    return $processamentos;
}

/**
 * Registra o processamento de uma comissão
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param array $dados Dados do processamento
 * @return bool|string true se sucesso, string com erro se falhar
 */
function registrarProcessamentoComissao($conn, $dados) {
    try {
        // Verifica se já existe processamento para esta parcela específica
        $processamentos = verificarComissaoProcessada($conn, $dados['emprestimo_id'], $dados['parcela_id']);
        if ($processamentos) {
            return "Comissão já processada para esta parcela em " . $processamentos[0]['data_processamento'];
        }

        // Registra o processamento
        $stmt = $conn->prepare("
            INSERT INTO controle_comissoes (
                parcela_id,
                usuario_id,
                conta_id,
                emprestimo_id,
                valor_comissao,
                processado
            ) VALUES (?, ?, ?, ?, ?, 1)
        ");

        $stmt->bind_param(
            "iiidd",
            $dados['parcela_id'],
            $dados['usuario_id'],
            $dados['conta_id'],
            $dados['emprestimo_id'],
            $dados['valor_comissao']
        );

        if (!$stmt->execute()) {
            throw new Exception("Erro ao registrar processamento: " . $stmt->error);
        }

        return true;

    } catch (Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Processa o retorno do capital e as comissões quando todas as parcelas estão pagas
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $emprestimo_id ID do empréstimo
 * @return array Array com status do processamento
 */
function processarComissoesERetornos($conn, $emprestimo_id) {
    try {
        $start_transaction = !hasActiveTransaction($conn);
        
        if ($start_transaction) {
            $conn->begin_transaction();
        }

        // Verificar se já foi processado
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM movimentacoes_contas 
            WHERE descricao LIKE ? 
            AND tipo = 'entrada'
        ");
        $desc_check = "Retorno de capital - Empréstimo #" . $emprestimo_id . "%";
        $stmt_check->bind_param("s", $desc_check);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $ja_processado = $result_check->fetch_assoc()['total'] > 0;

        if ($ja_processado) {
            return array('success' => false, 'message' => 'Retorno de capital e comissões já foram processados para este empréstimo.');
        }

        // Buscar informações do empréstimo
        $sql = "SELECT 
                e.*,
                c.nome as cliente_nome,
                u.id as investidor_id,
                ct.id as conta_id,
                ct.comissao as percentual_comissao,
                (SELECT SUM(valor) FROM parcelas WHERE emprestimo_id = e.id) as total_previsto,
                (SELECT SUM(valor_pago) FROM parcelas WHERE emprestimo_id = e.id AND status = 'pago') as total_pago,
                (SELECT COUNT(*) FROM parcelas WHERE emprestimo_id = e.id) as total_parcelas,
                (SELECT COUNT(*) FROM parcelas WHERE emprestimo_id = e.id AND status = 'pago') as parcelas_pagas,
                (SELECT id FROM contas WHERE usuario_id = 1 AND status = 'ativo' LIMIT 1) as conta_admin_id,
                (SELECT COUNT(*) FROM movimentacoes_contas 
                 WHERE descricao LIKE CONCAT('Empréstimo #', e.id, '%')
                 AND tipo = 'saida'
                 AND conta_id = ct.id) as tem_saida_capital
                FROM emprestimos e
                JOIN clientes c ON e.cliente_id = c.id
                JOIN usuarios u ON e.investidor_id = u.id
                JOIN contas ct ON u.id = ct.usuario_id
                WHERE e.id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $emprestimo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $emprestimo = $result->fetch_assoc();

        if (!$emprestimo) {
            throw new Exception("Empréstimo não encontrado");
        }

        if (!$emprestimo['conta_admin_id']) {
            throw new Exception("Conta do administrador não encontrada");
        }

        // Verificar se todas as parcelas estão pagas
        if ($emprestimo['parcelas_pagas'] < $emprestimo['total_parcelas']) {
            return array('success' => false, 'message' => 'Nem todas as parcelas estão pagas');
        }

        // Se não houver registro da saída do capital, registrar agora
        if ($emprestimo['tem_saida_capital'] == 0) {
            $descricao_saida = sprintf(
                "Empréstimo #%d para %s em %s",
                $emprestimo_id,
                $emprestimo['cliente_nome'],
                date('d/m/Y', strtotime($emprestimo['data_inicio']))
            );

            $stmt_saida = $conn->prepare("INSERT INTO movimentacoes_contas 
                                        (conta_id, tipo, valor, descricao, data_movimentacao) 
                                        VALUES (?, 'saida', ?, ?, ?)");
            $stmt_saida->bind_param("idss", 
                $emprestimo['conta_id'],
                $emprestimo['valor_emprestado'],
                $descricao_saida,
                $emprestimo['data_inicio']
            );
            
            if (!$stmt_saida->execute()) {
                throw new Exception("Erro ao registrar saída de capital: " . $stmt_saida->error);
            }
        }

        // Calcular valores
        $valor_emprestado = floatval($emprestimo['valor_emprestado']);
        $total_pago = floatval($emprestimo['total_pago']);
        $lucro_total = $total_pago - $valor_emprestado;
        $percentual_comissao = floatval($emprestimo['percentual_comissao']);
        
        // Verificar se já existe movimentação de retorno de capital
        $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM movimentacoes_contas 
                                    WHERE conta_id = ? 
                                    AND descricao LIKE ?
                                    AND tipo = 'entrada'");
        $desc_check = "Retorno de capital - Empréstimo #" . $emprestimo_id . "%";
        $stmt_check->bind_param("is", $emprestimo['conta_id'], $desc_check);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $ja_processado = $result_check->fetch_assoc()['total'] > 0;

        if (!$ja_processado) {
            // Verificar se houve complemento de capital do administrador
            $stmt_check_complemento = $conn->prepare("
                SELECT valor as valor_recebido
                FROM movimentacoes_contas 
                WHERE conta_id = ? 
                AND descricao LIKE ? 
                AND tipo = 'entrada'
                LIMIT 1
            ");
            $desc_complemento = "Recebimento de capital do administrador para Empréstimo #" . $emprestimo_id . "%";
            $stmt_check_complemento->bind_param("is", $emprestimo['conta_id'], $desc_complemento);
            $stmt_check_complemento->execute();
            $result_complemento = $stmt_check_complemento->get_result();
            $valor_complemento = floatval($result_complemento->fetch_assoc()['valor_recebido']);

            // 1. Registrar retorno do capital para o investidor
            $descricao_retorno = sprintf(
                "Retorno de capital - Empréstimo #%d para %s (quitado)",
                $emprestimo_id,
                $emprestimo['cliente_nome']
            );

            $stmt = $conn->prepare("INSERT INTO movimentacoes_contas 
                                  (conta_id, tipo, valor, descricao, data_movimentacao) 
                                  VALUES (?, 'entrada', ?, ?, NOW())");
            $stmt->bind_param("ids", 
                $emprestimo['conta_id'],
                $valor_emprestado,
                $descricao_retorno
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao registrar retorno de capital: " . $stmt->error);
            }

            // 2. Registrar 100% da comissão para o investidor
            $descricao_comissao = sprintf(
                "Comissão total (%.1f%%) - Empréstimo #%d para %s (quitado) - Lucro: R$ %s",
                $percentual_comissao,
                $emprestimo_id,
                $emprestimo['cliente_nome'],
                number_format($lucro_total, 2, ',', '.')
            );

            $stmt = $conn->prepare("INSERT INTO movimentacoes_contas 
                                  (conta_id, tipo, valor, descricao, data_movimentacao) 
                                  VALUES (?, 'entrada', ?, ?, NOW())");
            $stmt->bind_param("ids", 
                $emprestimo['conta_id'],
                $lucro_total,
                $descricao_comissao
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao registrar comissão total do investidor: " . $stmt->error);
            }

            // 3. Se houve complemento do administrador, devolver o valor
            if ($valor_complemento > 0) {
                // Debitar da conta do investidor o valor que foi complementado
                $descricao_devolucao = sprintf(
                    "Devolução do capital complementar ao administrador - Empréstimo #%d para %s (quitado)",
                    $emprestimo_id,
                    $emprestimo['cliente_nome']
                );

                $stmt = $conn->prepare("INSERT INTO movimentacoes_contas 
                                      (conta_id, tipo, valor, descricao, data_movimentacao) 
                                      VALUES (?, 'saida', ?, ?, NOW())");
                $stmt->bind_param("ids", 
                    $emprestimo['conta_id'],
                    $valor_complemento,
                    $descricao_devolucao
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Erro ao registrar devolução do capital complementar: " . $stmt->error);
                }

                // Creditar na conta do administrador o valor devolvido
                $descricao_recebimento = sprintf(
                    "Recebimento da devolução do capital complementar - Empréstimo #%d para %s (quitado)",
                    $emprestimo_id,
                    $emprestimo['cliente_nome']
                );

                $stmt = $conn->prepare("INSERT INTO movimentacoes_contas 
                                      (conta_id, tipo, valor, descricao, data_movimentacao) 
                                      VALUES (?, 'entrada', ?, ?, NOW())");
                $stmt->bind_param("ids", 
                    $emprestimo['conta_admin_id'],
                    $valor_complemento,
                    $descricao_recebimento
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Erro ao registrar recebimento da devolução do capital: " . $stmt->error);
                }
            }

            // 4. Debitar 60% da comissão do investidor
            $valor_comissao_admin = round($lucro_total * 0.6, 2);
            $descricao_debito = sprintf(
                "Envio de comissão (60%%) ao administrador - Empréstimo #%d",
                $emprestimo_id
            );

            $stmt = $conn->prepare("INSERT INTO movimentacoes_contas 
                                  (conta_id, tipo, valor, descricao, data_movimentacao) 
                                  VALUES (?, 'saida', ?, ?, NOW())");
            $stmt->bind_param("ids", 
                $emprestimo['conta_id'],
                $valor_comissao_admin,
                $descricao_debito
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao registrar débito de comissão do investidor: " . $stmt->error);
            }

            // 5. Creditar 60% da comissão para o administrador
            $descricao_credito_admin = sprintf(
                "Recebimento de comissão (60%%) - Empréstimo #%d para %s",
                $emprestimo_id,
                $emprestimo['cliente_nome']
            );

            $stmt = $conn->prepare("INSERT INTO movimentacoes_contas 
                                  (conta_id, tipo, valor, descricao, data_movimentacao) 
                                  VALUES (?, 'entrada', ?, ?, NOW())");
            $stmt->bind_param("ids", 
                $emprestimo['conta_admin_id'],
                $valor_comissao_admin,
                $descricao_credito_admin
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao registrar crédito de comissão do administrador: " . $stmt->error);
            }
        }

        if ($start_transaction) {
            $conn->commit();
        }
        
        return array('success' => true, 'message' => 'Processamento concluído com sucesso');
        
    } catch (Exception $e) {
        if ($start_transaction) {
            $conn->rollback();
        }
        return array('success' => false, 'message' => $e->getMessage());
    }
}

/**
 * Busca o status de todas as comissões de um investidor
 * Retorna informações sobre comissões previstas, realizadas e processadas
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $usuario_id ID do usuário/investidor
 * @return array Array com status de todas as comissões
 */
function buscarStatusComissoes($conn, $usuario_id) {
    // Busca todos os empréstimos ativos do investidor
    $sql = "
        SELECT 
            e.id as emprestimo_id,
            e.valor_emprestado,
            e.cliente_id,
            cl.nome as cliente_nome,
            c.comissao as percentual_comissao,
            c.id as conta_id,
            (SELECT SUM(valor) FROM parcelas WHERE emprestimo_id = e.id) as valor_total_parcelas,
            (SELECT SUM(valor_pago) FROM parcelas WHERE emprestimo_id = e.id AND status = 'pago') as valor_ja_pago,
            (SELECT COUNT(*) FROM parcelas WHERE emprestimo_id = e.id AND status = 'pago') as parcelas_pagas,
            (SELECT COUNT(*) FROM parcelas WHERE emprestimo_id = e.id) as total_parcelas,
            COALESCE(
                (SELECT SUM(valor_comissao) 
                 FROM controle_comissoes 
                 WHERE emprestimo_id = e.id AND usuario_id = e.investidor_id AND processado = 1
                ), 0
            ) as comissao_ja_processada
        FROM 
            emprestimos e
        JOIN 
            clientes cl ON e.cliente_id = cl.id
        JOIN 
            usuarios u ON e.investidor_id = u.id
        JOIN 
            contas c ON u.id = c.usuario_id
        WHERE 
            e.investidor_id = ? 
            AND e.status = 'ativo'
        ORDER BY 
            e.data_inicio DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $resumo = [
        'total_emprestimos' => 0,
        'total_valor_emprestado' => 0,
        'total_comissao_prevista' => 0,
        'total_comissao_realizada' => 0,
        'total_comissao_processada' => 0,
        'total_comissao_pendente' => 0
    ];

    $emprestimos = [];
    while ($row = $result->fetch_assoc()) {
        // Calcula valores para este empréstimo
        $valor_emprestado = floatval($row['valor_emprestado']);
        $valor_total = floatval($row['valor_total_parcelas']);
        $valor_pago = floatval($row['valor_ja_pago'] ?? 0);
        $percentual = floatval($row['percentual_comissao']);
        
        // Calcula lucros
        $lucro_total = $valor_total - $valor_emprestado;
        $lucro_realizado = max(0, $valor_pago - $valor_emprestado);
        
        // Calcula comissões
        $comissao_prevista = $lucro_total * ($percentual / 100);
        $comissao_realizada = $lucro_realizado * ($percentual / 100);
        $comissao_processada = floatval($row['comissao_ja_processada']);
        $comissao_pendente = max(0, $comissao_realizada - $comissao_processada);

        // Atualiza totais
        $resumo['total_emprestimos']++;
        $resumo['total_valor_emprestado'] += $valor_emprestado;
        $resumo['total_comissao_prevista'] += $comissao_prevista;
        $resumo['total_comissao_realizada'] += $comissao_realizada;
        $resumo['total_comissao_processada'] += $comissao_processada;
        $resumo['total_comissao_pendente'] += $comissao_pendente;

        // Adiciona informações deste empréstimo
        $emprestimos[] = [
            'emprestimo' => [
                'id' => $row['emprestimo_id'],
                'cliente_nome' => $row['cliente_nome'],
                'valor_emprestado' => $valor_emprestado,
                'valor_total' => $valor_total,
                'valor_pago' => $valor_pago,
                'parcelas_pagas' => $row['parcelas_pagas'],
                'total_parcelas' => $row['total_parcelas']
            ],
            'comissoes' => [
                'percentual' => $percentual,
                'prevista' => $comissao_prevista,
                'realizada' => $comissao_realizada,
                'processada' => $comissao_processada,
                'pendente' => $comissao_pendente
            ],
            'lucro' => [
                'total_previsto' => $lucro_total,
                'realizado' => $lucro_realizado
            ],
            'status' => [
                'progresso' => ($row['parcelas_pagas'] * 100) / $row['total_parcelas'],
                'finalizado' => ($row['parcelas_pagas'] == $row['total_parcelas'])
            ]
        ];
    }

    return [
        'resumo' => $resumo,
        'emprestimos' => $emprestimos
    ];
} 