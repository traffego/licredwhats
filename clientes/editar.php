<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo '<div class="container py-4"><div class="alert alert-danger">ID do cliente não recebido.</div></div>';
    exit;
}

$id = (int) $_POST['id'];
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$cliente = $resultado->fetch_assoc();
$stmt->close();

if (!$cliente) {
    echo '<div class="container py-4"><div class="alert alert-danger">Cliente não encontrado.</div></div>';
    exit;
}

// Obter dados do usuário logado
$usuario_logado_id = $_SESSION['usuario_id'] ?? null;
$usuario_logado_nivel = $_SESSION['nivel_autoridade'] ?? null;

function selecionado($valor, $comparar) {
    return $valor == $comparar ? 'selected' : '';
}
?>

<body>
<div class="container py-4">
    <h3 class="mb-4">Editar Cliente</h3>

    <form action="salvar.php" method="POST">
        <input type="hidden" name="id" value="<?= $cliente['id'] ?>">


        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo Pessoa</label>
                <select name="tipo_pessoa" class="form-select">
                    <option value="1" <?= $cliente['tipo_pessoa'] === 'Física' ? 'selected' : '' ?>>Física</option>
                    <option value="2" <?= $cliente['tipo_pessoa'] === 'Jurídica' ? 'selected' : '' ?>>Jurídica</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">CPF / CNPJ</label>
                <input type="text" name="cpf_cnpj" class="form-control" value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label">Nascimento</label>
                <input type="text" name="nascimento" class="form-control" value="<?= $cliente['nascimento'] ? date('d/m/Y', strtotime($cliente['nascimento'])) : '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" class="form-control" value="<?= htmlspecialchars($cliente['cep'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Endereço</label>
                <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($cliente['bairro'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Cidade</label>
                <input type="text" name="cidade" class="form-control" value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <input type="text" name="estado" class="form-control" value="<?= htmlspecialchars($cliente['estado'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Ativo" <?= selecionado($cliente['status'], 'Ativo') ?>>Ativo</option>
                    <option value="Inativo" <?= selecionado($cliente['status'], 'Inativo') ?>>Inativo</option>
                    <option value="Alerta" <?= selecionado($cliente['status'], 'Alerta') ?>>Alerta</option>
                    <option value="Atenção" <?= selecionado($cliente['status'], 'Atenção') ?>>Atenção</option>
                </select>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Chave Pix</label>
                <input type="text" name="chave_pix" class="form-control" value="<?= htmlspecialchars($cliente['chave_pix'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Nome Secundário</label>
                <input type="text" name="nome_secundario" class="form-control" value="<?= htmlspecialchars($cliente['nome_secundario'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefone Secundário</label>
                <input type="text" name="telefone_secundario" class="form-control" value="<?= htmlspecialchars($cliente['telefone_secundario'] ?? '') ?>">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Endereço Secundário</label>
            <input type="text" name="endereco_secundario" class="form-control" value="<?= htmlspecialchars($cliente['endereco_secundario'] ?? '') ?>">
        </div>

        <div class="mb-4">
            <label class="form-label">Observações</label>
            <textarea name="observacoes" class="form-control" rows="4"><?= htmlspecialchars($cliente['observacoes'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
</body>
</html>
