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

// Verificar se é uma atualização (quando recebe ID) ou inserção
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$is_update = !empty($id);

// Validações básicas
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$tipo = $_POST['tipo'] ?? '';
$nivel_autoridade = $_POST['nivel_autoridade'] ?? '';
$senha = $_POST['senha'] ?? '';
$confirmar_senha = $_POST['confirmar_senha'] ?? '';

// Validação de campos
if (empty($nome) || empty($email) || empty($tipo) || empty($nivel_autoridade)) {
    $_SESSION['erro'] = 'Todos os campos são obrigatórios.';
    if ($is_update) {
        header('Location: editar.php?id=' . $id);
    } else {
        header('Location: novo.php');
    }
    exit;
}

// Validar email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erro'] = 'E-mail inválido.';
    if ($is_update) {
        header('Location: editar.php?id=' . $id);
    } else {
        header('Location: novo.php');
    }
    exit;
}

// Se não for superadmin, só pode criar/editar investidores
if ($nivel_usuario !== 'superadmin' && ($tipo !== 'investidor' || $nivel_autoridade !== 'investidor')) {
    $_SESSION['erro'] = 'Você só pode criar ou editar usuários do tipo Investidor.';
    if ($is_update) {
        header('Location: editar.php?id=' . $id);
    } else {
        header('Location: novo.php');
    }
    exit;
}

// Validar combinação tipo/nível
if ($tipo === 'investidor' && $nivel_autoridade !== 'investidor') {
    $_SESSION['erro'] = 'Um usuário do tipo Investidor deve ter nível de autoridade Investidor.';
    if ($is_update) {
        header('Location: editar.php?id=' . $id);
    } else {
        header('Location: novo.php');
    }
    exit;
}

// Verificar se o email já existe (exceto para o próprio usuário sendo editado)
$sql_verificar = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
$stmt_verificar = $conn->prepare($sql_verificar);
$id_atual = $is_update ? $id : 0; // Usa 0 para novo usuário, que nunca vai coincidir com um ID existente
$stmt_verificar->bind_param("si", $email, $id_atual);
$stmt_verificar->execute();
$resultado = $stmt_verificar->get_result();

if ($resultado->num_rows > 0) {
    $_SESSION['erro'] = 'Este e-mail já está em uso por outro usuário.';
    if ($is_update) {
        header('Location: editar.php?id=' . $id);
    } else {
        header('Location: novo.php');
    }
    exit;
}

try {
    // INSERÇÃO DE NOVO USUÁRIO
    if (!$is_update) {
        // Validar senha
        if (empty($senha) || strlen($senha) < 6) {
            $_SESSION['erro'] = 'A senha deve ter pelo menos 6 caracteres.';
            header('Location: novo.php');
            exit;
        }
        
        if ($senha !== $confirmar_senha) {
            $_SESSION['erro'] = 'As senhas não coincidem.';
            header('Location: novo.php');
            exit;
        }
        
        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Inserir novo usuário
        $sql = "INSERT INTO usuarios (nome, email, senha, tipo, nivel_autoridade) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro na preparação da consulta: " . $conn->error);
        }
        
        $stmt->bind_param("sssss", $nome, $email, $senha_hash, $tipo, $nivel_autoridade);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao inserir usuário: " . $stmt->error);
        }
        
        // Se for um investidor, criar automaticamente uma conta para ele
        if ($tipo === 'investidor') {
            $novo_usuario_id = $conn->insert_id;
            
            // Criar conta principal para o investidor
            $sql_conta = "INSERT INTO contas (usuario_id, nome, descricao, saldo_inicial, comissao, status, criado_em, atualizado_em) 
                         VALUES (?, 'Conta Principal', 'Conta padrão para operações de investimento', 0.00, 40.00, 'ativo', NOW(), NOW())";
            $stmt_conta = $conn->prepare($sql_conta);
            
            if (!$stmt_conta) {
                // Log do erro, mas não impede a criação do usuário
                error_log("Erro ao preparar a criação da conta para o investidor: " . $conn->error);
            } else {
                $stmt_conta->bind_param("i", $novo_usuario_id);
                
                if (!$stmt_conta->execute()) {
                    // Log do erro, mas não impede a criação do usuário
                    error_log("Erro ao criar conta para o investidor: " . $stmt_conta->error);
                }
            }
        }
        
        $_SESSION['sucesso'] = 'Usuário cadastrado com sucesso!';
        header('Location: index.php');
        exit;
    }
    // ATUALIZAÇÃO DE USUÁRIO EXISTENTE
    else {
        // Buscar usuário atual para verificações
        $stmt_usuario = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt_usuario->bind_param("i", $id);
        $stmt_usuario->execute();
        $usuario_atual = $stmt_usuario->get_result()->fetch_assoc();
        
        if (!$usuario_atual) {
            throw new Exception("Usuário não encontrado.");
        }
        
        // Não permitir que um usuário comum (não superadmin) edite um superadmin
        if ($nivel_usuario !== 'superadmin' && $usuario_atual['nivel_autoridade'] === 'superadmin') {
            $_SESSION['erro'] = 'Você não tem permissão para editar um super administrador.';
            header('Location: index.php');
            exit;
        }
        
        // Se a senha for fornecida, atualiza a senha, caso contrário mantém a atual
        if (!empty($senha)) {
            // Validar senha
            if (strlen($senha) < 6) {
                $_SESSION['erro'] = 'A senha deve ter pelo menos 6 caracteres.';
                header('Location: editar.php?id=' . $id);
                exit;
            }
            
            if ($senha !== $confirmar_senha) {
                $_SESSION['erro'] = 'As senhas não coincidem.';
                header('Location: editar.php?id=' . $id);
                exit;
            }
            
            // Hash da nova senha
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            // Atualizar usuário com nova senha
            $sql = "UPDATE usuarios SET nome = ?, email = ?, senha = ?, tipo = ?, nivel_autoridade = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $nome, $email, $senha_hash, $tipo, $nivel_autoridade, $id);
        } else {
            // Atualizar usuário sem mudar a senha
            $sql = "UPDATE usuarios SET nome = ?, email = ?, tipo = ?, nivel_autoridade = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $nome, $email, $tipo, $nivel_autoridade, $id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao atualizar usuário: " . $stmt->error);
        }
        
        $_SESSION['sucesso'] = 'Usuário atualizado com sucesso!';
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro: ' . $e->getMessage();
    if ($is_update) {
        header('Location: editar.php?id=' . $id);
    } else {
        header('Location: novo.php');
    }
    exit;
} 