<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Verifica se o usuário tem permissão de administrador ou superadmin
$nivel_usuario = $_SESSION['nivel_autoridade'] ?? '';
if ($nivel_usuario !== 'administrador' && $nivel_usuario !== 'superadmin') {
    $_SESSION['erro'] = 'Você não tem permissão para realizar esta operação.';
    header('Location: index.php');
    exit;
}

// Verifica se o ID foi fornecido
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $_SESSION['erro'] = 'ID do usuário não fornecido.';
    header('Location: index.php');
    exit;
}

// Verifica se o usuário está tentando excluir a si mesmo
if ($_SESSION['usuario_id'] == $id) {
    $_SESSION['erro'] = 'Você não pode excluir seu próprio usuário.';
    header('Location: index.php');
    exit;
}

try {
    // Busca informações do usuário para verificar se pode ser excluído
    $stmt = $conn->prepare("SELECT tipo, nivel_autoridade FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    
    if (!$usuario) {
        throw new Exception("Usuário não encontrado.");
    }
    
    // Verifica se um usuário não-superadmin está tentando excluir um administrador ou superadmin
    if ($nivel_usuario !== 'superadmin' && 
        ($usuario['tipo'] !== 'investidor' || $usuario['nivel_autoridade'] !== 'investidor')) {
        $_SESSION['erro'] = 'Você não tem permissão para excluir este usuário.';
        header('Location: index.php');
        exit;
    }
    
    // Verifica se o usuário tem clientes vinculados (como investidor)
    $stmt_clientes = $conn->prepare("SELECT COUNT(*) FROM clientes WHERE indicacao = ?");
    $stmt_clientes->bind_param("i", $id);
    $stmt_clientes->execute();
    $stmt_clientes->bind_result($total_clientes);
    $stmt_clientes->fetch();
    $stmt_clientes->close();
    
    if ($total_clientes > 0) {
        throw new Exception("Este usuário não pode ser excluído porque possui {$total_clientes} cliente(s) vinculado(s) a ele como investidor.");
    }
    
    // Executa a exclusão
    $stmt_excluir = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt_excluir->bind_param("i", $id);
    
    if (!$stmt_excluir->execute()) {
        throw new Exception("Erro ao excluir usuário: " . $stmt_excluir->error);
    }
    
    if ($stmt_excluir->affected_rows === 0) {
        throw new Exception("Usuário não encontrado ou já foi excluído.");
    }
    
    $_SESSION['sucesso'] = 'Usuário excluído com sucesso!';
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro: ' . $e->getMessage();
    header('Location: index.php');
    exit;
} 