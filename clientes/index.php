<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Verificar permissões administrativas
apenasAdmin();

require_once __DIR__ . '/../includes/head.php';

// Funções de formatação
function formatarCPF($cpf_cnpj) {
    $cpf_cnpj = preg_replace('/[^0-9]/', '', $cpf_cnpj);
    if (strlen($cpf_cnpj) === 11) {
        return substr($cpf_cnpj, 0, 3) . '.' . substr($cpf_cnpj, 3, 3) . '.' . substr($cpf_cnpj, 6, 3) . '-' . substr($cpf_cnpj, 9, 2);
    } elseif (strlen($cpf_cnpj) === 14) {
        return substr($cpf_cnpj, 0, 2) . '.' . substr($cpf_cnpj, 2, 3) . '.' . substr($cpf_cnpj, 5, 3) . '/' . substr($cpf_cnpj, 8, 4) . '-' . substr($cpf_cnpj, 12, 2);
    }
    return $cpf_cnpj;
}

function formatarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

$mensagem = '';
$ultimo_id = $_SESSION['ultimo_id'] ?? null;
$id_editado = $_SESSION['id_editado'] ?? null;

if (!empty($_SESSION['sucesso'])) {
    $mensagem = 'Cliente cadastrado com sucesso!';
    unset($_SESSION['sucesso']);
}
if (!empty($_SESSION['sucesso_edicao'])) {
    $mensagem = 'Cliente atualizado com sucesso!';
    unset($_SESSION['sucesso_edicao']);
}

// Query modificada para contar empréstimos por cliente e somar valores
$sql = "SELECT c.id, c.nome, c.cpf_cnpj, c.telefone, 
        COUNT(e.id) as total_emprestimos,
        IFNULL(SUM(e.valor_emprestado), 0) as valor_total_emprestimos
        FROM clientes c 
        LEFT JOIN emprestimos e ON c.id = e.cliente_id 
        GROUP BY c.id, c.nome, c.cpf_cnpj, c.telefone 
        ORDER BY c.id DESC";
$resultado = $conn->query($sql);
$clientes = $resultado->fetch_all(MYSQLI_ASSOC);
?>

<body>
<div class="container py-4">

    <?php if ($mensagem): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Clientes</h3>
        <a href="novo.php" class="btn btn-primary">+ Novo Cliente</a>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" id="filtro-nome" class="form-control" placeholder="Filtrar por nome">
        </div>
        <div class="col-md-4">
            <input type="text" id="filtro-cpf" class="form-control" placeholder="Filtrar por CPF/CNPJ">
        </div>
        <div class="col-md-4 text-end">
            <button id="btnExcluirSelecionados" class="btn btn-danger" disabled>Excluir Selecionados</button>
        </div>
    </div>

    <div class="mb-2 text-end">
        <label class="form-label me-2">Mostrar:</label>
        <select id="linhasPorPagina" class="form-select d-inline-block w-auto">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="-1">Todos</option>
        </select>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Tabela para Desktop -->
            <div class="d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabela-clientes">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" id="check-todos"></th>
                                <th>Nome</th>
                                <th>CPF / CNPJ</th>
                                <th>Telefone</th>
                                <th>Empréstimos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $c): ?>
                                <?php
                                    $classe_linha = '';
                                    if ($c['id'] == $ultimo_id) {
                                        $classe_linha = 'table-success fw-bold';
                                    } elseif ($c['id'] == $id_editado) {
                                        $classe_linha = 'table-warning fw-bold';
                                    }
                                ?>
                                <tr class="<?= $classe_linha ?>">
                                    <td><input type="checkbox" class="check-item" value="<?= $c['id'] ?>"></td>
                                    <td class="col-nome"><?= htmlspecialchars($c['nome']) ?></td>
                                    <td class="col-cpf"><?= htmlspecialchars($c['cpf_cnpj']) ?></td>
                                    <td><?= htmlspecialchars($c['telefone']) ?></td>
                                    <td>
                                        <?php if ($c['total_emprestimos'] > 0): ?>
                                            <span class="badge bg-info"><?= $c['total_emprestimos'] ?> empréstimo(s)</span>
                                            <div class="mt-1">
                                                <span class="badge bg-success">R$ <?= number_format($c['valor_total_emprestimos'], 2, ',', '.') ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Ações
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <form action="visualizar.php" method="POST" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                        <button type="submit" class="dropdown-item">🕵️ Detalhes</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form action="editar.php" method="POST" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                        <button type="submit" class="dropdown-item">✏️ Editar</button>
                                                    </form>
                                                </li>
                                                <li><button class="dropdown-item btn-excluir" data-id="<?= $c['id'] ?>">❌ Excluir</button></li>
                                                <li style="border-top: solid 1px #333;">
                                                    <form action="../emprestimos/novo.php" method="POST" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                        <button type="submit" class="dropdown-item">💵 Liberar Empréstimos</button>
                                                    </form>
                                                </li>
                                            </ul> 
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; unset($_SESSION['ultimo_id'], $_SESSION['id_editado']); ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Cards para Mobile -->
            <div class="d-md-none">
                <div class="cliente-cards">
                    <?php foreach ($clientes as $c): 
                        $destaque_class = '';
                        if ($c['id'] == $ultimo_id) {
                            $destaque_class = 'border-success bg-success bg-opacity-10';
                        } elseif ($c['id'] == $id_editado) {
                            $destaque_class = 'border-warning bg-warning bg-opacity-10';
                        }
                    ?>
                        <div class="cliente-card mb-3 <?= $destaque_class ?>" data-id="<?= $c['id'] ?>">
                            <div class="card">
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center p-3 border-bottom">
                                        <div class="flex-shrink-0">
                                            <div class="avatar-circle">
                                                <?= strtoupper(substr($c['nome'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($c['nome']) ?></h5>
                                            <p class="text-muted small mb-0">
                                                <?= formatarCPF($c['cpf_cnpj']) ?>
                                            </p>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input check-item-mobile" type="checkbox" value="<?= $c['id'] ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="p-3">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="info-group">
                                                    <label class="text-muted small d-block">Telefone</label>
                                                    <span class="fw-medium"><?= formatarTelefone($c['telefone']) ?></span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="info-group">
                                                    <label class="text-muted small d-block">Empréstimos</label>
                                                    <?php if ($c['total_emprestimos'] > 0): ?>
                                                        <span class="fw-medium"><?= $c['total_emprestimos'] ?> empréstimo(s)</span>
                                                    <?php else: ?>
                                                        <span class="text-secondary">Nenhum</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($c['total_emprestimos'] > 0): ?>
                                            <div class="col-12">
                                                <div class="info-group">
                                                    <label class="text-muted small d-block">Valor Total</label>
                                                    <span class="fw-bold text-success">R$ <?= number_format($c['valor_total_emprestimos'], 2, ',', '.') ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer bg-light p-2">
                                        <div class="d-flex justify-content-between">
                                            <form action="visualizar.php" method="POST">
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Detalhes
                                                </button>
                                            </form>
                                            
                                            <div class="btn-group">
                                                <form action="editar.php" method="POST">
                                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </form>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-excluir" data-id="<?= $c['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                
                                                <form action="../emprestimos/novo.php" method="POST">
                                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="bi bi-cash-coin"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmação -->
<div class="modal fade" id="modalConfirmarExclusao" tabindex="-1" aria-labelledby="modalConfirmarExclusaoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="excluir.php">
        <div class="modal-header">
          <h5 class="modal-title">Confirmar Exclusão</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p>Tem certeza que deseja excluir o(s) cliente(s) selecionado(s)? Esta ação não pode ser desfeita.</p>
          <div id="inputs-exclusao"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Sim, Excluir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<style>
/* Estilos para os cards de clientes em dispositivos móveis */
.cliente-card {
    transition: all 0.2s ease-in-out;
    border-radius: 8px;
    overflow: hidden;
}

.cliente-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.avatar-circle {
    width: 40px;
    height: 40px;
    background-color: #6c757d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: bold;
}

.info-group {
    margin-bottom: 0.5rem;
}

.btn-group .btn {
    margin: 0 2px;
}

.card-footer {
    border-top: 1px solid rgba(0,0,0,.125);
}
</style>

<script>
// Funções para formatação
function formatarCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    if (cpf.length === 11) {
        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
    } else if (cpf.length === 14) {
        return cpf.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
    }
    return cpf;
}

function formatarTelefone(telefone) {
    telefone = telefone.replace(/\D/g, '');
    if (telefone.length === 11) {
        return telefone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
    } else if (telefone.length === 10) {
        return telefone.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
    }
    return telefone;
}

// Script personalizado para filtros e funcionalidades da tabela de clientes
document.addEventListener('DOMContentLoaded', function() {
    // Função para aplicar filtros à tabela e cards
    function aplicarFiltros() {
        const filtroNome = document.getElementById('filtro-nome').value.toLowerCase();
        const filtroCpf = document.getElementById('filtro-cpf').value.toLowerCase();
        
        // Aplicar filtros à tabela (desktop)
        document.querySelectorAll('#tabela-clientes tbody tr').forEach(function(linha) {
            const colNome = linha.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const colCpf = linha.querySelector('td:nth-child(3)').textContent.toLowerCase();
            
            let exibe = true;
            if (filtroNome && !colNome.includes(filtroNome)) exibe = false;
            if (filtroCpf && !colCpf.includes(filtroCpf)) exibe = false;
            
            linha.style.display = exibe ? '' : 'none';
        });
        
        // Aplicar filtros aos cards (mobile)
        document.querySelectorAll('.cliente-card').forEach(function(card) {
            const cardNome = card.querySelector('h5').textContent.toLowerCase();
            const cardCpf = card.querySelector('p.text-muted').textContent.toLowerCase();
            
            let exibe = true;
            if (filtroNome && !cardNome.includes(filtroNome)) exibe = false;
            if (filtroCpf && !cardCpf.includes(filtroCpf)) exibe = false;
            
            card.style.display = exibe ? '' : 'none';
        });
    }
    
    // Configurar eventos para filtros
    document.getElementById('filtro-nome').addEventListener('input', aplicarFiltros);
    document.getElementById('filtro-cpf').addEventListener('input', aplicarFiltros);
    
    // Paginação simples
    const linhasPorPagina = document.getElementById('linhasPorPagina');
    if (linhasPorPagina) {
        linhasPorPagina.addEventListener('change', function() {
            const valor = parseInt(this.value);
            
            // Aplicar à tabela (desktop)
            const linhas = document.querySelectorAll('#tabela-clientes tbody tr');
            
            // Aplicar aos cards (mobile)
            const cards = document.querySelectorAll('.cliente-card');
            
            if (valor === -1) {
                // Mostrar todos
                linhas.forEach(linha => linha.classList.remove('d-none'));
                cards.forEach(card => card.classList.remove('d-none'));
            } else {
                // Mostrar apenas o número selecionado
                linhas.forEach((linha, index) => {
                    if (index < valor) {
                        linha.classList.remove('d-none');
                    } else {
                        linha.classList.add('d-none');
                    }
                });
                
                cards.forEach((card, index) => {
                    if (index < valor) {
                        card.classList.remove('d-none');
                    } else {
                        card.classList.add('d-none');
                    }
                });
            }
        });
    }
    
    // Gerenciar o checkbox principal
    const checkTodos = document.getElementById('check-todos');
    if (checkTodos) {
        checkTodos.addEventListener('change', function() {
            const isChecked = this.checked;
            // Atualizar os checkboxes da tabela
            document.querySelectorAll('.check-item').forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            // Atualizar os checkboxes dos cards
            document.querySelectorAll('.check-item-mobile').forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            atualizarBotaoExcluir();
        });
    }
    
    // Atualizar estado do botão de exclusão baseado nos checkboxes selecionados
    function atualizarBotaoExcluir() {
        const btnExcluir = document.getElementById('btnExcluirSelecionados');
        const checkboxesMarcados = document.querySelectorAll('.check-item:checked, .check-item-mobile:checked');
        
        if (btnExcluir) {
            btnExcluir.disabled = checkboxesMarcados.length === 0;
        }
    }
    
    // Adicionar evento change para todos os checkboxes individuais
    document.querySelectorAll('.check-item, .check-item-mobile').forEach(checkbox => {
        checkbox.addEventListener('change', atualizarBotaoExcluir);
    });
    
    // Configurar o botão de exclusão em massa
    const btnExcluirSelecionados = document.getElementById('btnExcluirSelecionados');
    if (btnExcluirSelecionados) {
        btnExcluirSelecionados.addEventListener('click', function() {
            const checkboxesMarcados = document.querySelectorAll('.check-item:checked, .check-item-mobile:checked');
            
            if (checkboxesMarcados.length > 0) {
                const modal = document.getElementById('modalConfirmarExclusao');
                const container = document.getElementById('inputs-exclusao');
                
                // Limpar conteúdo anterior
                container.innerHTML = '';
                
                // Adicionar inputs hidden para cada ID selecionado
                checkboxesMarcados.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = checkbox.value;
                    container.appendChild(input);
                });
                
                // Exibir o modal
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            }
        });
    }
    
    // Configurar botões de excluir individuais
    document.querySelectorAll('.btn-excluir').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const modal = document.getElementById('modalConfirmarExclusao');
            const container = document.getElementById('inputs-exclusao');
            
            // Limpar conteúdo anterior
            container.innerHTML = '';
            
            // Adicionar input hidden para o ID
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            container.appendChild(input);
            
            // Exibir o modal
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        });
    });
});
</script>
</body>
</html>