<?php
// Configurações básicas de segurança da sessão
ini_set('session.cookie_httponly', 1);

// Verifica se estamos em ambiente de produção (com HTTPS)
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Desativa cookie_secure para permitir login em HTTP e HTTPS
ini_set('session.cookie_secure', 0);

// Ajustar SameSite para permitir mais compatibilidade
ini_set('session.cookie_samesite', 'Lax');

session_start();

require_once __DIR__ . '/config.php';             // define BASE_URL
require_once __DIR__ . '/includes/conexao.php';   // Adicionando conexão

// Completamente desativando a limpeza da sessão para permitir acesso simultâneo
// if (isset($_SESSION['usuario_id']) && !isset($_GET['manterSessao'])) {
//     // Limpa apenas variáveis de sessão específicas, em vez de destruir a sessão
//     unset($_SESSION['usuario_id']);
//     unset($_SESSION['usuario_email']);
// }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitização dos dados
        $email = filter_var(
            trim($_POST['email'] ?? ''),
            FILTER_VALIDATE_EMAIL
        );
        
        $senha = trim($_POST['senha'] ?? '');
        $csrf_token = trim($_POST['csrf_token'] ?? '');

        // Validações
        if (!$email) {
            throw new Exception("Por favor, insira um email válido.");
        }
        
        if (empty($senha)) {
            throw new Exception("Por favor, insira sua senha.");
        }
        
        if ($csrf_token !== $_SESSION['csrf_token']) {
            // Registrar informações de depuração para problema de CSRF
            error_log("ERRO CSRF: Token recebido: " . substr($csrf_token, 0, 10) . "... / Token sessão: " . 
                      substr($_SESSION['csrf_token'] ?? 'vazio', 0, 10) . "... / Session ID: " . session_id());
            throw new Exception("Erro de segurança. Por favor, tente novamente.");
        }

        // Verifica se a conexão está disponível
        if (!isset($conn) || !$conn instanceof mysqli) {
            throw new Exception("Erro de conexão com o banco de dados.");
        }

        // Prepara e executa a consulta
        $stmt = $conn->prepare("SELECT id, senha, nivel_autoridade, nome FROM usuarios WHERE email = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Erro ao preparar a consulta: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar a consulta: " . $stmt->error);
        }

        $resultado = $stmt->get_result();
        $usuario = $resultado->fetch_assoc();

        if (!$usuario) {
            throw new Exception("Email ou senha incorretos.");
        }

        if (!password_verify($senha, $usuario['senha'])) {
            throw new Exception("Email ou senha incorretos.");
        }

        // Login bem-sucedido
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_email'] = $email;
        $_SESSION['nivel_autoridade'] = $usuario['nivel_autoridade'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        
        // Log de login para depuração em dispositivos móveis
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
        $is_mobile = preg_match('/(android|iphone|ipad|mobile)/i', $user_agent);
        $log_info = [
            'time' => date('Y-m-d H:i:s'),
            'email' => $email,
            'is_mobile' => $is_mobile ? 'Sim' : 'Não',
            'user_agent' => $user_agent,
            'session_id' => session_id(),
            'csrf_token' => strlen($_SESSION['csrf_token'])
        ];
        error_log("LOGIN BEM-SUCEDIDO: " . json_encode($log_info, JSON_UNESCAPED_UNICODE));

        $stmt->close();
        
        // Redirecionar com base no nível de autoridade
        require_once __DIR__ . '/includes/autenticacao.php';
        redirecionarPorNivel();
        exit();

    } catch (Exception $e) {
        error_log("Erro no login: " . $e->getMessage());
        $erro = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistema de Empréstimos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#007bff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilo2.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilo.css">
</head>
<body class="bg-light d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow p-4" style="width: 100%; max-width: 400px;">
        <div class="text-center mb-4">
            <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="Logo do Sistema" class="img-fluid" style="max-height: 100px;">
        </div>
        <h4 class="mb-4 text-center">Acesso ao Sistema</h4>
        <?php if ($erro): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <form method="POST" id="loginForm">
            <!-- Adicionando campo oculto para identificar dispositivo móvel -->
            <input type="hidden" name="is_mobile" value="1">
            
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Digite seu email" 
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                           aria-describedby="emailHelp"
                           autocomplete="email"
                           inputmode="email"
                           required>
                </div>
                <div id="emailHelp" class="form-text">Digite o email cadastrado no sistema</div>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="senha" name="senha" class="form-control" 
                           placeholder="Digite sua senha"
                           autocomplete="current-password"
                           required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="text-end mt-2">
                    <a href="<?php echo BASE_URL; ?>/recuperar_senha.php" class="text-decoration-none">
                        <i class="bi bi-question-circle"></i> Esqueceu a senha?
                    </a>
                </div>
            </div>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <button type="submit" class="btn btn-primary w-100 mb-3" id="submitButton">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <span class="button-text">Entrar</span>
            </button>
            <div class="text-center">
                <a href="<?php echo BASE_URL; ?>/index.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Voltar à página inicial
                </a>
            </div>
        </form>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function(e) {
            e.preventDefault(); // Previne comportamento padrão do botão
            const senha = document.getElementById('senha');
            const icon = this.querySelector('i');
            if (senha.type === 'password') {
                senha.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                senha.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Show loading state on form submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const button = document.getElementById('submitButton');
            const spinner = button.querySelector('.spinner-border');
            const text = button.querySelector('.button-text');
            
            spinner.classList.remove('d-none');
            text.textContent = 'Entrando...';
            button.disabled = true;
        });
    </script>
</body>
</html>
