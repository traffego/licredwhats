<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

// Verifica se o usuário tem permissão de administrador ou superadmin
$nivel_usuario = $_SESSION['nivel_autoridade'] ?? '';
if ($nivel_usuario !== 'administrador' && $nivel_usuario !== 'superadmin') {
    echo '<div class="container py-4"><div class="alert alert-danger">Você não tem permissão para acessar esta página.</div></div>';
    exit;
}

// Se for superadmin, pode cadastrar qualquer tipo de usuário
// Se for apenas administrador, só pode cadastrar investidores
$pode_cadastrar_admin = ($nivel_usuario === 'superadmin');
?>

<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Novo Usuário</h4>
                </div>
                <div class="card-body">
                    <form action="salvar.php" method="POST" id="form-usuario">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" required maxlength="255">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" required maxlength="150">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="senha" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="senha" name="senha" required minlength="6">
                                <div class="form-text">Mínimo de 6 caracteres</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required minlength="6">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tipo" class="form-label">Tipo de Usuário</label>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="investidor">Investidor</option>
                                    <?php if ($pode_cadastrar_admin): ?>
                                    <option value="administrador">Administrador</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="nivel_autoridade" class="form-label">Nível de Autoridade</label>
                                <select class="form-select" id="nivel_autoridade" name="nivel_autoridade" required>
                                    <option value="investidor">Investidor</option>
                                    <?php if ($pode_cadastrar_admin): ?>
                                    <option value="administrador">Administrador</option>
                                    <option value="superadmin">Super Administrador</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-usuario');
    const senha = document.getElementById('senha');
    const confirmarSenha = document.getElementById('confirmar_senha');
    const tipoSelect = document.getElementById('tipo');
    const nivelSelect = document.getElementById('nivel_autoridade');
    
    // Sincroniza o tipo com o nível de autoridade
    tipoSelect.addEventListener('change', function() {
        const tipo = this.value;
        if (tipo === 'investidor') {
            nivelSelect.value = 'investidor';
        } else if (tipo === 'administrador' && nivelSelect.value === 'investidor') {
            nivelSelect.value = 'administrador';
        }
    });
    
    // Validação do formulário
    form.addEventListener('submit', function(e) {
        // Verifica se as senhas coincidem
        if (senha.value !== confirmarSenha.value) {
            e.preventDefault();
            alert('As senhas não coincidem.');
            return false;
        }
        
        // Verifica se a combinação tipo/nível é válida
        if (tipoSelect.value === 'investidor' && nivelSelect.value !== 'investidor') {
            e.preventDefault();
            alert('Um usuário do tipo Investidor deve ter nível de autoridade Investidor.');
            return false;
        }
        
        return true;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 