<?php
// Configurações de ambiente
define('ENVIRONMENT', 'development'); // development, production
define('DEBUG_MODE', true);

// Configurações de banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_emprestimos');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuração de timezone
date_default_timezone_set('America/Sao_Paulo');

// Definir o caminho base da URL - independente do diretório atual
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// Definimos o caminho base baseado no DOCUMENT_ROOT em vez do SCRIPT_NAME
// Isso garante que o caminho base seja sempre o mesmo, independente da página acessada
if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
    // Obtém o caminho raiz do sistema
    $project_root = str_replace('\\', '/', dirname(__FILE__));
    $server_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    
    // Compara os caminhos para obter o caminho relativo do projeto ao servidor
    $base_path = str_replace($server_root, '', $project_root);
} else {
    // Fallback se DOCUMENT_ROOT não estiver disponível
    $base_path = '';
}

// Garantir que o caminho base termine com uma barra
$base_path = $base_path !== '' ? $base_path.'/' : '/';

define('BASE_URL', $protocol . $host . $base_path);

// Configurações de sessão
define('SESSION_NAME', 'SISTEMA_EMPRESTIMOS');
define('SESSION_LIFETIME', 7200); // 2 horas em segundos
define('SESSION_PATH', '/'); // Caminho fixo para permitir acesso de qualquer subdiretório
define('SESSION_DOMAIN', '');
define('SESSION_SECURE', false);
define('SESSION_HTTPONLY', true);

// Configurações de upload
define('UPLOAD_MAX_SIZE', 5242880); // 5MB em bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Configurações de email
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@example.com');
define('SMTP_FROM_NAME', 'Sistema de Empréstimos');

// Configurações de segurança
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL_CHAR', true);

// Configurações de cache
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hora em segundos
define('CACHE_DIR', __DIR__ . '/cache/');
