<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo '<div class="container py-4"><div class="alert alert-danger">ID do cliente não recebido.</div></div>';
    exit;
}

$cliente_id = (int) $_POST['id'];
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cliente) {
    echo '<div class="container py-4"><div class="alert alert-danger">Cliente não encontrado.</div></div>';
    exit;
}

// Buscar informações reais dos empréstimos
$stmtEmp = $conn->prepare("SELECT COUNT(*) AS total, SUM(valor_emprestado) AS total_valor FROM emprestimos WHERE cliente_id = ?");
$stmtEmp->bind_param("i", $cliente_id);
$stmtEmp->execute();
$resumo = $stmtEmp->get_result()->fetch_assoc();
$stmtEmp->close();

$total_emprestimos = (int) $resumo['total'];
$valor_total = (float) $resumo['total_valor'] ?? 0.00;
$total_parcelas = 0; // Ainda mockado até a tabela parcelas estar pronta
?>

<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0"><?= htmlspecialchars($cliente['nome']) ?></h3>
            <span class="badge bg-<?= $cliente['status'] === 'Ativo' ? 'success' : 'secondary' ?>"><?= $cliente['status'] ?></span>
        </div>
        <div>
            <form action="editar.php" method="POST" style="display:inline;">
                <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
                <button type="submit" class="btn btn-sm btn-warning">Editar</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary card-clickable">
                <div class="card-body text-center">
                    <h5 class="card-title">Empréstimos</h5>
                    <p class="display-6 mb-0"><?= $total_emprestimos ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info card-clickable">
                <div class="card-body text-center">
                    <h5 class="card-title">Parcelas</h5>
                    <p class="display-6 mb-0"><?= $total_parcelas ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success card-clickable">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Emprestado</h5>
                    <p class="display-6 mb-0">R$ <?= number_format($valor_total, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Dados Pessoais</div>
                <div class="card-body">
                    <p><strong>Tipo:</strong> <?= $cliente['tipo_pessoa'] ?></p>
                    <p><strong>CPF/CNPJ:</strong> <?= $cliente['cpf_cnpj'] ?></p>
                    <p><strong>Nascimento:</strong> <?= $cliente['nascimento'] ? date('d/m/Y', strtotime($cliente['nascimento'])) : '' ?></p>
                    <p><strong>Telefone:</strong> <?= $cliente['telefone'] ?></p>
                    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Endereço</div>
                <div class="card-body">
                    <p><strong>CEP:</strong> <?= $cliente['cep'] ?></p>
                    <p><strong>Endereço:</strong> <?= $cliente['endereco'] ?></p>
                    <p><strong>Bairro:</strong> <?= $cliente['bairro'] ?></p>
                    <p><strong>Cidade:</strong> <?= $cliente['cidade'] ?> - <?= $cliente['estado'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Outras informações</div>
                <div class="card-body">
                    <p><strong>Chave Pix:</strong> <?= $cliente['chave_pix'] ?></p>
                    <p><strong>Nome Secundário:</strong> <?= $cliente['nome_secundario'] ?></p>
                    <p><strong>Telefone Secundário:</strong> <?= $cliente['telefone_secundario'] ?></p>
                    <p><strong>Endereço Secundário:</strong> <?= $cliente['endereco_secundario'] ?></p>
                    <p><strong>Observações:</strong> <?= nl2br(htmlspecialchars($cliente['observacoes'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modais com dados mockados -->
    <div class="modal fade" id="modalEmprestimos" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-body">
        <h5>Empréstimos</h5>
        <ul>
            <li>Empréstimo #1 - R$ 2.000,00 - 6 parcelas</li>
            <li>Empréstimo #2 - R$ 3.500,00 - 8 parcelas</li>
            <li>Empréstimo #3 - R$ 3.000,75 - 10 parcelas</li>
        </ul>
      </div></div></div>
    </div>

    <div class="modal fade" id="modalParcelas" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-body">
        <h5>Parcelas</h5>
        <ul>
            <li>Parcela 1 - R$ 500,00 - Paga</li>
            <li>Parcela 2 - R$ 500,00 - Em aberto</li>
            <li>Parcela 3 - R$ 500,00 - Em aberto</li>
            <li>Parcela 4 - R$ 500,00 - Paga</li>
        </ul>
      </div></div></div>
    </div>

    <div class="modal fade" id="modalValores" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-body">
        <h5>Resumo Financeiro</h5>
        <p>Total Emprestado: R$ 8.500,75</p>
        <p>Total Quitado: R$ 5.000,00</p>
        <p>Total em Aberto: R$ 3.500,75</p>
      </div></div></div>
    </div>
</div>

<style>
.card-clickable { cursor: pointer; transition: 0.2s; }
.card-clickable:hover { box-shadow: 0 0 10px rgba(0,0,0,0.15); }
</style>
</body>
</html>
