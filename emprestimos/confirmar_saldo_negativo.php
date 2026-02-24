<?php
ob_start(); // Ativa o buffer de saída para evitar erros de headers already sent
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Verificar se é administrador
$nivel_usuario = $_SESSION['nivel_autoridade'] ?? '';
if ($nivel_usuario !== 'administrador' && $nivel_usuario !== 'superadmin') {
    echo '<div class="container py-4"><div class="alert alert-danger">Você não tem permissão para acessar esta página.</div></div>';
    exit;
}

// Verificar se existem dados de empréstimo na sessão
if (!isset($_SESSION['emprestimo_dados'])) {
    header("Location: index.php");
    exit;
}

$dados = $_SESSION['emprestimo_dados'];

// Buscar nome do cliente
$stmt_cliente = $conn->prepare("SELECT nome FROM clientes WHERE id = ?");
$stmt_cliente->bind_param("i", $dados['cliente_id']);
$stmt_cliente->execute();
$cliente = $stmt_cliente->get_result()->fetch_assoc();

// Buscar nome do investidor
$stmt_investidor = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
$stmt_investidor->bind_param("i", $dados['investidor_id']);
$stmt_investidor->execute();
$investidor = $stmt_investidor->get_result()->fetch_assoc();

// Processar confirmação
if (isset($_POST['confirmar'])) {
    $autorizar = $_POST['autorizar'] ?? '';
    
    if ($autorizar === 'sim') {
        // Continuar com o empréstimo mesmo com saldo negativo
        // Preparar a query de inserção do empréstimo
        $sql = "INSERT INTO emprestimos (
            cliente_id,
            investidor_id,
            tipo_de_cobranca,
            valor_emprestado,
            parcelas,
            valor_parcela,
            juros_percentual,
            data_inicio,
            configuracao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Erro ao preparar a query: " . $conn->error);
        }

        $configuracao_json = json_encode($dados['configuracao']);

        $stmt->bind_param(
            "iissiidss",
            $dados['cliente_id'],
            $dados['investidor_id'],
            $dados['tipo_cobranca'],
            $dados['valor_emprestado'],
            $dados['parcelas'],
            $dados['valor_parcela'],
            $dados['juros_percentual'],
            $dados['data_inicio'],
            $configuracao_json
        );
        
        // Inicia a transação
        $conn->begin_transaction();
        
        try {
            // Insere o empréstimo
            if (!$stmt->execute()) {
                throw new Exception("Erro ao inserir empréstimo: " . $stmt->error);
            }
            
            $emprestimo_id = $conn->insert_id;
            
            // Buscar a conta do administrador
            $stmt_admin = $conn->prepare("SELECT id FROM contas WHERE usuario_id = 1 AND status = 'ativo' LIMIT 1");
            $stmt_admin->execute();
            $result_admin = $stmt_admin->get_result();
            $conta_admin = $result_admin->fetch_assoc();
            
            if (!$conta_admin) {
                throw new Exception("Conta do administrador não encontrada");
            }
            
            $conta_admin_id = $conta_admin['id'];
            
            // Calcular valores
            $valor_disponivel = max(0, $dados['saldo_atual']);
            $valor_necessario = $dados['valor_emprestado'] - $valor_disponivel;
            
            // Log dos valores para debug
            error_log("Debug empréstimo #{$emprestimo_id}:");
            error_log("Valor total do empréstimo: " . $dados['valor_emprestado']);
            error_log("Saldo atual do investidor: " . $dados['saldo_atual']);
            error_log("Valor disponível: " . $valor_disponivel);
            error_log("Valor necessário do admin: " . $valor_necessario);
            error_log("Conta do investidor: " . $dados['conta_id']);
            error_log("Conta do admin: " . $conta_admin_id);
            
            // 1. Se o investidor tem algum saldo, debitar o que tem disponível
            if ($valor_disponivel > 0) {
                $descricao = "Empréstimo #{$emprestimo_id} para {$cliente['nome']} (capital próprio) em " . date('d/m/Y', strtotime($dados['data_inicio']));
                
                $stmt_mov = $conn->prepare("INSERT INTO movimentacoes_contas 
                                          (conta_id, tipo, valor, descricao, data_movimentacao) 
                                          VALUES (?, 'saida', ?, ?, ?)");
                $stmt_mov->bind_param("idss", 
                                    $dados['conta_id'], 
                                    $valor_disponivel, 
                                    $descricao,
                                    $dados['data_inicio']);
                
                if (!$stmt_mov->execute()) {
                    throw new Exception("Erro ao registrar movimentação na conta do investidor: " . $stmt_mov->error);
                }
                error_log("Movimentação 1 - Débito do investidor realizada: " . $valor_disponivel);
            }
            
            // 2. Creditar o valor do administrador na conta do investidor ANTES de debitar da conta do admin
            $descricao_credito = "Recebimento de capital do administrador para Empréstimo #{$emprestimo_id} em " . date('d/m/Y', strtotime($dados['data_inicio']));
            
            // Verificar se a movimentação de crédito já existe
            $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM movimentacoes_contas 
                                        WHERE conta_id = ? AND tipo = 'entrada' 
                                        AND valor = ? AND data_movimentacao = ?");
            $stmt_check->bind_param("ids", $dados['conta_id'], $valor_necessario, $dados['data_inicio']);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $exists = $result_check->fetch_assoc()['total'] > 0;
            
            if (!$exists) {
                $stmt_mov = $conn->prepare("INSERT INTO movimentacoes_contas 
                                          (conta_id, tipo, valor, descricao, data_movimentacao) 
                                          VALUES (?, 'entrada', ?, ?, ?)");
                $stmt_mov->bind_param("idss", 
                                    $dados['conta_id'], 
                                    $valor_necessario, 
                                    $descricao_credito,
                                    $dados['data_inicio']);
                
                if (!$stmt_mov->execute()) {
                    throw new Exception("Erro ao registrar crédito na conta do investidor: " . $stmt_mov->error);
                }
            }

            // 2.1 Registrar a saída do valor total do empréstimo da conta do investidor
            $descricao_saida = "Empréstimo #{$emprestimo_id} para {$cliente['nome']} em " . date('d/m/Y', strtotime($dados['data_inicio']));
            
            $stmt_mov = $conn->prepare("INSERT INTO movimentacoes_contas 
                                      (conta_id, tipo, valor, descricao, data_movimentacao) 
                                      VALUES (?, 'saida', ?, ?, ?)");
            $stmt_mov->bind_param("idss", 
                                $dados['conta_id'], 
                                $dados['valor_emprestado'], 
                                $descricao_saida,
                                $dados['data_inicio']);
            
            if (!$stmt_mov->execute()) {
                throw new Exception("Erro ao registrar saída do valor total na conta do investidor: " . $stmt_mov->error);
            }
            
            // 3. Debitar da conta do administrador
            $descricao_admin = "Empréstimo #{$emprestimo_id} para {$cliente['nome']} (complemento de capital) em " . date('d/m/Y', strtotime($dados['data_inicio']));
            
            $stmt_mov = $conn->prepare("INSERT INTO movimentacoes_contas 
                                      (conta_id, tipo, valor, descricao, data_movimentacao) 
                                      VALUES (?, 'saida', ?, ?, ?)");
            $stmt_mov->bind_param("idss", 
                                $conta_admin_id, 
                                $valor_necessario, 
                                $descricao_admin,
                                $dados['data_inicio']);
            
            if (!$stmt_mov->execute()) {
                throw new Exception("Erro ao registrar movimentação na conta do administrador: " . $stmt_mov->error);
            }
            error_log("Movimentação 3 - Débito do admin realizada: " . $valor_necessario);
            
            // Gerar as parcelas
            $parcelas_array = gerarParcelas(
                $dados['parcelas'], 
                $dados['data_inicio'], 
                $dados['configuracao']['periodo_pagamento'], 
                $dados['valor_parcela'], 
                $dados['configuracao']['dias_semana'], 
                $dados['configuracao']['considerar_feriados']
            );
            
            // Prepara a inserção de parcelas
            $stmt_parcela = $conn->prepare("INSERT INTO parcelas (
                emprestimo_id, 
                numero, 
                valor, 
                vencimento, 
                status, 
                valor_pago, 
                data_pagamento, 
                forma_pagamento, 
                observacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt_parcela) {
                throw new Exception("Erro ao preparar a query de parcelas: " . $conn->error);
            }
            
            // Insere cada parcela na nova tabela
            foreach ($parcelas_array as $parcela) {
                $numero = $parcela['numero'];
                $valor = $parcela['valor'];
                $vencimento = $parcela['vencimento'];
                $status = 'pendente';
                $valor_pago = null;
                $data_pagamento = null;
                $forma_pagamento = null;
                $observacao = "valor_original: {$valor}, diferenca_transacao: 0";
                
                $stmt_parcela->bind_param(
                    "iidssdsss",
                    $emprestimo_id,
                    $numero,
                    $valor,
                    $vencimento,
                    $status,
                    $valor_pago,
                    $data_pagamento,
                    $forma_pagamento,
                    $observacao
                );
                
                if (!$stmt_parcela->execute()) {
                    throw new Exception("Erro ao inserir parcela: " . $stmt_parcela->error);
                }
            }
            
            // Registrar que o administrador autorizou o saldo negativo
            $usuario_id = $_SESSION['usuario_id'] ?? 0;
            $descricao = "Empréstimo autorizado com saldo negativo pelo administrador. Saldo anterior: R$ " . 
                number_format($dados['saldo_atual'], 2, ',', '.');
            
            $stmt_log = $conn->prepare("INSERT INTO historico (emprestimo_id, tipo, descricao, valor, data, usuario_id, created_at) 
                                      VALUES (?, 'autorizacao_saldo_negativo', ?, ?, NOW(), ?, NOW())");
            $stmt_log->bind_param("isdi", $emprestimo_id, $descricao, $dados['valor_emprestado'], $usuario_id);
            $stmt_log->execute();
            
            // Confirma a transação
            $conn->commit();
            
            // Limpa os dados da sessão
            unset($_SESSION['emprestimo_dados']);
            
            // Redireciona com mensagem de sucesso
            header("Location: index.php?sucesso=1&id=" . $emprestimo_id . "&msg=" . urlencode("Empréstimo cadastrado com sucesso! (Saldo negativo autorizado)"));
            exit;
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $conn->rollback();
            // Limpa os dados da sessão
            unset($_SESSION['emprestimo_dados']);
            header("Location: index.php?erro=1&msg=" . urlencode("Erro ao salvar: " . $e->getMessage()));
            exit;
        }
    } else {
        // Administrador não autorizou o saldo negativo
        unset($_SESSION['emprestimo_dados']);
        header("Location: index.php?erro=1&msg=" . urlencode("Empréstimo cancelado: saldo insuficiente na conta do investidor."));
        exit;
    }
}

// Calcular o saldo que ficará após o empréstimo
$saldo_final = $dados['saldo_atual'] - $dados['valor_emprestado'];
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Atenção: Saldo Insuficiente</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <p>O investidor selecionado não possui saldo suficiente para este empréstimo.</p>
                        <p><strong>Este empréstimo deixará a conta com saldo negativo.</strong></p>
                    </div>

                    <h5 class="card-title mb-4">Detalhes do Empréstimo:</h5>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Cliente:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($cliente['nome']) ?></dd>
                                
                                <dt class="col-sm-5">Investidor:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($investidor['nome']) ?></dd>
                                
                                <dt class="col-sm-5">Valor:</dt>
                                <dd class="col-sm-7">R$ <?= number_format($dados['valor_emprestado'], 2, ',', '.') ?></dd>
                                
                                <dt class="col-sm-5">Parcelas:</dt>
                                <dd class="col-sm-7"><?= $dados['parcelas'] ?>x de R$ <?= number_format($dados['valor_parcela'], 2, ',', '.') ?></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Saldo Atual:</dt>
                                <dd class="col-sm-7 <?= $dados['saldo_atual'] < 0 ? 'text-danger' : 'text-success' ?>">
                                    R$ <?= number_format($dados['saldo_atual'], 2, ',', '.') ?>
                                </dd>
                                
                                <dt class="col-sm-5">Valor Empréstimo:</dt>
                                <dd class="col-sm-7 text-danger">- R$ <?= number_format($dados['valor_emprestado'], 2, ',', '.') ?></dd>
                                
                                <dt class="col-sm-5 fw-bold">Saldo Final:</dt>
                                <dd class="col-sm-7 fw-bold text-danger">
                                    R$ <?= number_format($saldo_final, 2, ',', '.') ?>
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Atenção Administrador:</strong> Apenas você pode autorizar operações com saldo negativo.
                    </div>

                    <form method="post" class="mt-4">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Deseja autorizar este empréstimo mesmo com saldo negativo?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="autorizar" id="autorizar_sim" value="sim">
                                <label class="form-check-label" for="autorizar_sim">
                                    Sim, autorizo o saldo negativo nesta conta
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="autorizar" id="autorizar_nao" value="nao" checked>
                                <label class="form-check-label" for="autorizar_nao">
                                    Não, cancelar este empréstimo
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Cancelar
                            </a>
                            <button type="submit" name="confirmar" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Confirmar Decisão
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Função para gerar parcelas (copiada do arquivo salvar.php para manter a funcionalidade)
function gerarParcelas(int $numero_parcelas, string $data_inicial, string $periodo, float $valor_parcela, array $dias_semana, bool $considerar_feriados): array {
    global $conn;
    
    $parcelas = [];
    $data_atual = new DateTime($data_inicial);
    
    // Gera as parcelas
    for ($i = 1; $i <= $numero_parcelas; $i++) {
        // Na primeira parcela, não altera a data
        if ($i > 1) {
            $data_atual = calcularProximaData($data_atual, $periodo, $dias_semana, $considerar_feriados);
        }
        
        $parcelas[] = [
            'numero' => $i,
            'valor' => number_format($valor_parcela, 2, '.', ''),
            'vencimento' => $data_atual->format('Y-m-d'),
            'status' => 'pendente'
        ];
    }
    
    return $parcelas;
}

// Função para calcular próxima data (copiada do arquivo salvar.php)
function calcularProximaData(DateTime $data_base, string $periodo, array $dias_semana, bool $considerar_feriados): DateTime {
    global $conn;
    
    $data = clone $data_base;
    
    // Adiciona dias conforme o período
    switch($periodo) {
        case 'diario':
            $data->modify('+1 day');
            break;
        case 'semanal':
            $data->modify('+7 days');
            break;
        case 'quinzenal':
            $data->modify('+15 days');
            break;
        case 'mensal':
            $data->modify('+1 month');
            break;
        case 'trimestral':
            $data->modify('+3 months');
            break;
    }
    
    // Verifica se a data cai em um dia a ser evitado
    while (diaASerEvitado($data, $dias_semana, $considerar_feriados)) {
        $data->modify('+1 day');
    }
    
    return $data;
}

// Função para verificar dias a evitar (copiada do arquivo salvar.php)
function diaASerEvitado(DateTime $data, array $dias_semana, bool $considerar_feriados): bool {
    global $conn;
    
    $dia_semana = $data->format('w'); // 0 (domingo) até 6 (sábado)
    
    // Verifica se o dia da semana deve ser evitado
    if (in_array((string)$dia_semana, $dias_semana)) {
        return true;
    }
    
    // Verifica se é feriado (se necessário)
    if ($considerar_feriados) {
        $data_sql = $data->format('Y-m-d');
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM feriados WHERE data = ?");
        $stmt->bind_param("s", $data_sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['total'] > 0) {
            return true;
        }
    }
    
    return false;
}

require_once __DIR__ . '/../includes/footer.php';
?> 