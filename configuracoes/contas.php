<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/autenticacao.php';

// Verificar permissões administrativas
apenasAdmin();

require_once __DIR__ . '/../includes/head.php';

// Verificar se a coluna 'comissao' existe na tabela 'contas'
$check_comissao_column = $conn->query("SHOW COLUMNS FROM contas LIKE 'comissao'");
$comissao_column_exists = ($check_comissao_column && $check_comissao_column->num_rows > 0);

// Se a coluna não existir, criá-la
if (!$comissao_column_exists) {
    $add_comissao_column = "ALTER TABLE contas ADD COLUMN comissao DECIMAL(5,2) DEFAULT 40.00 COMMENT 'Percentual de comissão do investidor'";
    if ($conn->query($add_comissao_column)) {
        $mensagem = "Coluna de comissão adicionada à tabela de contas.";
        $tipo_alerta = "success";
    } else {
        $mensagem = "Erro ao adicionar coluna de comissão: " . $conn->error;
        $tipo_alerta = "danger";
    }
}

// Processamento de formulários
$mensagem = '';
$tipo_alerta = '';

// Exclusão de conta
if (isset($_POST['excluir']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Verificar se a conta não tem movimentações
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM movimentacoes_contas WHERE conta_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['total'] > 0) {
        $mensagem = "Não é possível excluir uma conta que possui movimentações.";
        $tipo_alerta = "danger";
    } else {
        $stmt = $conn->prepare("DELETE FROM contas WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $mensagem = "Conta excluída com sucesso!";
            $tipo_alerta = "success";
        } else {
            $mensagem = "Erro ao excluir conta: " . $conn->error;
            $tipo_alerta = "danger";
        }
    }
}

// Adicionar ou editar conta
if (isset($_POST['salvar'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $usuario_id = intval($_POST['usuario_id']);
    $descricao = trim($_POST['descricao']);
    $comissao = floatval(str_replace(',', '.', $_POST['comissao']));
    $status = $_POST['status'];
    
    if (empty($usuario_id)) {
        $mensagem = "Por favor, selecione um investidor.";
        $tipo_alerta = "danger";
    } else {
        // Verificar se o investidor já possui uma conta ativa (apenas para novos registros)
        if (!$id) {
            $stmt_verificar = $conn->prepare("SELECT COUNT(*) as total FROM contas WHERE usuario_id = ? AND status = 'ativo'");
            $stmt_verificar->bind_param("i", $usuario_id);
            $stmt_verificar->execute();
            $result_verificar = $stmt_verificar->get_result();
            $row_verificar = $result_verificar->fetch_assoc();
            
            if ($row_verificar['total'] > 0) {
                $mensagem = "Este investidor já possui uma conta ativa. Não é possível criar mais de uma conta por investidor.";
                $tipo_alerta = "danger";
                
                // Vai direto para o final do bloco, pulando as operações de insert/update
                goto fim_operacao;
            }
        }
        
        // Buscar o nome do investidor para usar como nome da conta
        $stmt_investidor = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt_investidor->bind_param("i", $usuario_id);
        $stmt_investidor->execute();
        $result_investidor = $stmt_investidor->get_result();
        $nome_investidor = $result_investidor->fetch_assoc()['nome'];
        
        // Nome da conta será "Conta de [Nome do Investidor]"
        $nome = "Conta de " . $nome_investidor;
        
        if ($id) {
            // Edição
            $stmt = $conn->prepare("UPDATE contas SET usuario_id = ?, nome = ?, descricao = ?, comissao = ?, status = ?, atualizado_em = NOW() WHERE id = ?");
            $stmt->bind_param("issdsi", $usuario_id, $nome, $descricao, $comissao, $status, $id);
        } else {
            // Inserção - saldo inicial será sempre 0
            $saldo_inicial = 0;
            $stmt = $conn->prepare("INSERT INTO contas (usuario_id, nome, descricao, saldo_inicial, comissao, status, criado_em, atualizado_em) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("issdds", $usuario_id, $nome, $descricao, $saldo_inicial, $comissao, $status);
        }
        
        if ($stmt->execute()) {
            $mensagem = ($id ? "Conta atualizada" : "Conta adicionada") . " com sucesso!";
            $tipo_alerta = "success";
        } else {
            $mensagem = "Erro: " . $stmt->error;
            $tipo_alerta = "danger";
        }
    }
    
    // Label para pular a operação de insert/update quando necessário
    fim_operacao:
}

// Consulta para todas as contas
$sql = "SELECT c.*, u.nome as usuario_nome, u.nivel_autoridade,
        COALESCE(c.saldo_inicial + SUM(CASE WHEN mc.tipo = 'entrada' THEN mc.valor ELSE -mc.valor END), c.saldo_inicial) as saldo_atual,
        COALESCE(c.comissao, 0) as comissao,
        CASE 
            WHEN u.nivel_autoridade IN ('admin', 'superadmin') THEN 1 
            ELSE 0 
        END as is_admin_user
       FROM contas c
       LEFT JOIN usuarios u ON c.usuario_id = u.id
       LEFT JOIN movimentacoes_contas mc ON c.id = mc.conta_id
       GROUP BY c.id, c.nome, c.descricao, c.status, c.saldo_inicial, c.comissao, c.usuario_id, 
                c.criado_em, c.atualizado_em, u.nome, u.nivel_autoridade
       ORDER BY c.status DESC, c.nome ASC";

$result = $conn->query($sql);
$contas = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Buscar as últimas 4 movimentações da conta
        $sql_movimentacoes = "SELECT tipo, valor, descricao, data_movimentacao 
                             FROM movimentacoes_contas 
                             WHERE conta_id = ? 
                             ORDER BY data_movimentacao DESC, id DESC 
                             LIMIT 4";
        $stmt_mov = $conn->prepare($sql_movimentacoes);
        $stmt_mov->bind_param("i", $row['id']);
        $stmt_mov->execute();
        $result_mov = $stmt_mov->get_result();
        
        $row['ultimas_movimentacoes'] = [];
        while ($mov = $result_mov->fetch_assoc()) {
            $row['ultimas_movimentacoes'][] = $mov;
        }
        
        $contas[] = $row;
    }
}

// Buscar clientes investidores para o select
$sql_investidores = "SELECT id, nome FROM usuarios WHERE tipo = 'investidor' OR id = 1 ORDER BY nome";
$result_investidores = $conn->query($sql_investidores);
$investidores = [];

if ($result_investidores && $result_investidores->num_rows > 0) {
    while ($row = $result_investidores->fetch_assoc()) {
        // Marcar o administrador (id=1)
        if ($row['id'] == 1) {
            $row['nome'] = $row['nome'] . ' (Administrador)';
        }
        $investidores[] = $row;
    }
}

// Se não houver resultados, verificar se a coluna 'tipo' existe na tabela 'usuarios'
if (empty($investidores)) {
    $check_column = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'tipo'");
    $tipo_column_exists = ($check_column && $check_column->num_rows > 0);
    
    if (!$tipo_column_exists) {
        // Se a coluna não existir, buscar todos os usuários
        $sql_usuarios = "SELECT id, nome FROM usuarios ORDER BY nome";
        $result_usuarios = $conn->query($sql_usuarios);
        
        if ($result_usuarios && $result_usuarios->num_rows > 0) {
            while ($row = $result_usuarios->fetch_assoc()) {
                // Marcar o administrador (id=1)
                if ($row['id'] == 1) {
                    $row['nome'] = $row['nome'] . ' (Administrador)';
                }
                $investidores[] = $row;
            }
        }
    }
}

// Garantir que pelo menos o administrador está na lista
$admin_user_id = 1;
$has_admin = false;

foreach ($investidores as $inv) {
    if ($inv['id'] == $admin_user_id) {
        $has_admin = true;
        break;
    }
}

if (!$has_admin) {
    // Adicionar o admin manualmente
    $investidores[] = [
        'id' => $admin_user_id,
        'nome' => 'Administrador do Sistema'
    ];
}

// Ordenar investidores por nome
usort($investidores, function($a, $b) {
    return strcmp($a['nome'], $b['nome']);
});
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Gerenciamento de Contas</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contaModal">
            <i class="bi bi-plus-circle"></i> Nova Conta
        </button>
    </div>
    
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Política de Contas:</strong> Cada investidor pode ter apenas uma conta ativa no sistema.
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    
    <?php if (count($investidores) <= 1): ?>
        <div class="alert alert-warning mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Atenção:</strong> Não há usuários investidores cadastrados (exceto o Administrador).
            <a href="../usuarios/novo.php" class="alert-link">Clique aqui</a> para cadastrar novos usuários investidores.
        </div>
    <?php endif; ?>
    
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
        <?php if (empty($contas)): ?>
            <div class="col-12">
                <div class="card shadow-sm border-secondary text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-wallet2 fs-1 text-secondary mb-3"></i>
                        <h5 class="card-title">Nenhuma conta cadastrada</h5>
                        <p class="card-text text-muted">Clique em "Nova Conta" para adicionar sua primeira conta.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($contas as $conta): 
                // Verificar se esta conta pertence ao administrador
                $isAdminConta = ($conta['usuario_id'] == $admin_user_id);
                $statusClass = $conta['status'] === 'ativo' ? 'success' : 'danger';
                $saldoClass = $conta['saldo_atual'] < 0 ? 'danger' : 'success';
                $borderClass = $isAdminConta ? 'border-primary' : '';
            ?>
                <div class="col">
                    <div class="card h-100 shadow-sm <?= $borderClass ?>">
                        <?php if ($isAdminConta): ?>
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-person-check-fill me-2"></i>Conta do Administrador
                            </div>
                        <?php else: ?>
                            <div class="card-header bg-light">
                                <i class="bi bi-person me-2"></i><?= htmlspecialchars($conta['usuario_nome']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <div class="mb-3">
                                <p class="card-text text-muted"><?= htmlspecialchars($conta['descricao'] ?: 'Sem descrição') ?></p>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="badge bg-<?= $statusClass ?>">
                                        <?= $conta['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-calendar me-1"></i><?= date('d/m/Y', strtotime($conta['criado_em'])) ?>
                                </div>
                            </div>
                            
                            <div class="p-2 border rounded bg-light mb-3">
                                <div class="small text-muted">Saldo Atual</div>
                                <div class="fw-bold text-<?= $saldoClass ?>">
                                    R$ <?= number_format($conta['saldo_atual'], 2, ',', '.') ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($conta['ultimas_movimentacoes'])): ?>
                            <div class="p-2 border rounded bg-light mb-3">
                                <div class="small text-muted mb-1">Últimas Movimentações</div>
                                <div style="font-size: 0.75rem;">
                                    <?php foreach ($conta['ultimas_movimentacoes'] as $mov): 
                                        $movClass = $mov['tipo'] === 'entrada' ? 'success' : 'danger';
                                        $movIcon = $mov['tipo'] === 'entrada' ? 'plus' : 'dash';
                                        $data = date('d/m/Y', strtotime($mov['data_movimentacao']));
                                        // Limitar a descrição a 30 caracteres
                                        $descricao = mb_strlen($mov['descricao']) > 30 ? mb_substr($mov['descricao'], 0, 30) . '...' : $mov['descricao'];
                                    ?>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="text-<?= $movClass ?>" style="white-space: nowrap;">
                                                    <i class="bi bi-<?= $movIcon ?>-circle-fill"></i>
                                                    R$ <?= number_format($mov['valor'], 2, ',', '.') ?>
                                                </div>
                                                <div class="text-muted" style="margin-left: 8px;"><?= $data ?></div>
                                            </div>
                                            <div style="font-size: 0.65rem; margin-top: -2px; margin-left: 16px; font-style: italic; color: #adb5bd;">
                                                <?= htmlspecialchars($descricao) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="p-2 border rounded bg-light mb-3">
                                <div class="small text-muted">Comissão do Investidor</div>
                                <div class="fw-bold">
                                    <?= number_format($conta['comissao'], 2, ',', '.') ?>%
                                    <span class="text-muted small">(Admin: <?= number_format(100 - $conta['comissao'], 2, ',', '.') ?>%)</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex gap-1 flex-grow-1">
                                    <a href="movimentacoes.php?conta_id=<?= $conta['id'] ?>" 
                                       class="btn btn-primary btn-sm px-1 flex-grow-1" 
                                       title="Ver movimentações da conta">
                                        <i class="bi bi-cash-coin"></i> Movimentações
                                    </a>
                                    
                                    <?php if ($conta['nivel_autoridade'] === 'admin' || $conta['nivel_autoridade'] === 'superadmin'): ?>
                                    <a href="../emprestimos_admin.php" 
                                       class="btn btn-sm btn-info px-1 flex-grow-1 text-white">
                                        <i class="fas fa-hand-holding-usd"></i> Meus Empréstimos
                                    </a>
                                    <?php endif; ?>
                                    
                                    <button type="button" 
                                            class="btn btn-warning btn-sm px-1 editar-conta flex-grow-1" 
                                            data-bs-toggle="modal"
                                            data-bs-target="#contaModal"
                                            data-id="<?= $conta['id'] ?>"
                                            data-usuario-id="<?= $conta['usuario_id'] ?>"
                                            data-descricao="<?= htmlspecialchars($conta['descricao']) ?>"
                                            data-comissao="<?= $conta['comissao'] ?>"
                                            data-status="<?= $conta['status'] ?>"
                                            title="Editar detalhes da conta">
                                        <i class="bi bi-pencil-square"></i> Detalhes
                                    </button>
                                    
                                    <?php if (!$isAdminConta): ?>
                                    <button type="button" 
                                            class="btn btn-danger excluir-conta"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmExcluirModal"
                                            data-id="<?= $conta['id'] ?>"
                                            data-nome="<?= htmlspecialchars($conta['nome']) ?>"
                                            title="Excluir conta">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Adicionar/Editar Conta -->
<div class="modal fade" id="contaModal" tabindex="-1" aria-labelledby="contaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contaModalLabel">Nova Conta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="id" id="conta_id">
                    
                    <div class="mb-3">
                        <label for="usuario_id" class="form-label">Investidor</label>
                        <select class="form-select" id="usuario_id" name="usuario_id" required>
                            <option value="">Selecione o investidor</option>
                            <?php foreach ($investidores as $investidor): ?>
                                <option value="<?= $investidor['id'] ?>"><?= htmlspecialchars($investidor['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="comissao" class="form-label">Comissão do Investidor (%)</label>
                        <input type="text" class="form-control" id="comissao" name="comissao" value="40.00">
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i> Percentual do lucro que o investidor receberá (o administrador ficará com o restante).
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="salvar" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="confirmExcluirModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir a conta <strong id="conta_nome_excluir"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Esta ação não poderá ser desfeita.
                </div>
            </div>
            <div class="modal-footer">
                <form method="post">
                    <input type="hidden" name="id" id="conta_id_excluir">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="excluir" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para campos monetários
    document.querySelectorAll('.money').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (parseInt(value) / 100).toFixed(2).replace('.', ',');
            e.target.value = value;
        });
    });
    
    // Formatação para campo de comissão
    document.getElementById('comissao').addEventListener('input', function(e) {
        let value = e.target.value.replace(/[^\d,]/g, '');
        
        // Se tiver mais de uma vírgula, mantém só a primeira
        value = value.replace(/,/g, function(match, offset, string) {
            return offset === string.indexOf(',') ? match : '';
        });
        
        // Limita a 100%
        let numValue = parseFloat(value.replace(',', '.')) || 0;
        if (numValue > 100) {
            numValue = 100;
        }
        
        // Formata com 2 casas decimais
        e.target.value = numValue.toFixed(2).replace('.', ',');
    });
    
    // Verificação de investidor ao criar nova conta
    const usuarioIdSelect = document.getElementById('usuario_id');
    const btnSalvar = document.querySelector('button[name="salvar"]');
    const avisoInvestidor = document.createElement('div');
    avisoInvestidor.className = 'mt-2 text-danger';
    usuarioIdSelect.parentNode.appendChild(avisoInvestidor);
    
    // Dados das contas existentes para verificação client-side
    const contasExistentes = <?= json_encode(array_map(function($conta) { 
        return ['usuario_id' => $conta['usuario_id'], 'status' => $conta['status']]; 
    }, $contas)) ?>;
    
    usuarioIdSelect.addEventListener('change', function() {
        const usuarioId = parseInt(this.value);
        const contaExistente = contasExistentes.find(c => c.usuario_id === usuarioId && c.status === 'ativo');
        
        // Apenas verificar se estivermos criando uma nova conta (não editando)
        if (!document.getElementById('conta_id').value && contaExistente) {
            avisoInvestidor.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Este investidor já possui uma conta ativa.';
            btnSalvar.disabled = true;
        } else {
            avisoInvestidor.innerHTML = '';
            btnSalvar.disabled = false;
        }
    });
    
    // Preencher modal para edição
    document.querySelectorAll('.editar-conta').forEach(function(button) {
        button.addEventListener('click', function() {
            const modal = document.getElementById('contaModal');
            
            // Alterar título do modal
            modal.querySelector('.modal-title').textContent = 'Editar Conta';
            
            // Preencher campos
            document.getElementById('conta_id').value = this.getAttribute('data-id');
            document.getElementById('usuario_id').value = this.getAttribute('data-usuario-id');
            document.getElementById('descricao').value = this.getAttribute('data-descricao');
            
            const comissao = parseFloat(this.getAttribute('data-comissao') || 40);
            document.getElementById('comissao').value = comissao.toFixed(2).replace('.', ',');
            
            document.getElementById('status').value = this.getAttribute('data-status');
        });
    });
    
    // Limpar modal para nova conta
    document.querySelector('[data-bs-target="#contaModal"]').addEventListener('click', function() {
        if (!this.classList.contains('editar-conta')) {
            const modal = document.getElementById('contaModal');
            
            // Alterar título do modal
            modal.querySelector('.modal-title').textContent = 'Nova Conta';
            
            // Limpar campos
            document.getElementById('conta_id').value = '';
            
            // Pré-selecionar o administrador se disponível
            const adminUserId = <?= json_encode($admin_user_id) ?>;
            if (adminUserId) {
                document.getElementById('usuario_id').value = adminUserId;
            } else {
                document.getElementById('usuario_id').value = '';
            }
            
            document.getElementById('descricao').value = '';
            document.getElementById('comissao').value = '40.00';
            document.getElementById('status').value = 'ativo';
            
            // Verificar se o investidor selecionado já tem conta
            usuarioIdSelect.dispatchEvent(new Event('change'));
        }
    });
    
    // Preencher modal de confirmação de exclusão
    document.querySelectorAll('.excluir-conta').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('conta_id_excluir').value = this.getAttribute('data-id');
            document.getElementById('conta_nome_excluir').textContent = this.getAttribute('data-nome');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?> 