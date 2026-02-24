<?php
// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    // Verificar se não estamos já na página de login para evitar loops
    $pagina_atual = basename($_SERVER['PHP_SELF']);
    if ($pagina_atual !== 'login.php') {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }
}

/**
 * Verifica se o usuário tem a permissão especificada
 * 
 * @param string $permissao Nome da permissão a ser verificada
 * @return bool Retorna true se o usuário tem a permissão, false caso contrário
 */
function temPermissao($permissao) {
    // Usuário com ID 1 é sempre administrador e tem todas as permissões
    if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] == 1) {
        return true;
    }
    
    // Verifica se o usuário é administrador ou superadmin
    if (isset($_SESSION['nivel_autoridade']) && 
        ($_SESSION['nivel_autoridade'] === 'administrador' || $_SESSION['nivel_autoridade'] === 'superadmin')) {
        return true;
    }
    
    // Verifica permissões específicas para investidores
    if ($permissao === 'investidor' && isset($_SESSION['nivel_autoridade']) && $_SESSION['nivel_autoridade'] === 'investidor') {
        return true;
    }
    
    return false;
}

/**
 * Redireciona o usuário para sua página correspondente baseado no nível de autoridade
 */
function redirecionarPorNivel() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }
    
    // Redireciona com base no nível de autoridade
    if (isset($_SESSION['nivel_autoridade'])) {
        switch ($_SESSION['nivel_autoridade']) {
            case 'investidor':
                header("Location: " . BASE_URL . "investidor.php");
                break;
            case 'administrador':
            case 'superadmin':
            default:
                header("Location: " . BASE_URL . "dashboard.php");
                break;
        }
        exit();
    } else {
        // Se não tiver nível definido, vai para o dashboard padrão
        header("Location: " . BASE_URL . "dashboard.php");
        exit();
    }
}

/**
 * Verifica se o usuário é administrador ou superadmin e redireciona caso não seja
 * Use esta função no início de páginas que só devem ser acessadas por administradores
 */
function apenasAdmin() {
    // Verifica se o usuário tem nível de administrador
    if (!isset($_SESSION['nivel_autoridade']) || 
        ($_SESSION['nivel_autoridade'] !== 'administrador' && 
         $_SESSION['nivel_autoridade'] !== 'superadmin' && 
         $_SESSION['usuario_id'] != 1)) {
        
        // Usuário não é admin, verificar para onde redirecionar
        if (isset($_SESSION['nivel_autoridade']) && $_SESSION['nivel_autoridade'] === 'investidor') {
            // Armazenar mensagem de erro na sessão para destacar melhor
            $_SESSION['acesso_negado'] = true;
            $_SESSION['acesso_negado_mensagem'] = "Você não tem permissão para acessar a área administrativa! Esta tentativa foi registrada.";
            $_SESSION['pagina_tentativa'] = $_SERVER['REQUEST_URI'];
            
            // Registrar a tentativa de acesso não autorizado em log (opcional)
            $data = date('Y-m-d H:i:s');
            $usuario_id = $_SESSION['usuario_id'];
            $pagina = $_SERVER['REQUEST_URI'];
            error_log("[$data] Tentativa de acesso não autorizado: Usuário ID $usuario_id tentou acessar $pagina");
            
            // Redirecionar para a página de investidor com mensagem de erro destacada
            header("Location: " . BASE_URL . "investidor.php?erro=acesso_negado");
        } else {
            // Caso contrário, redireciona para o login
            header("Location: " . BASE_URL . "login.php");
        }
        exit();
    }
}