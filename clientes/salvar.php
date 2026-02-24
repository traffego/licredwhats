<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';

try {
    $tipo_pessoa = $_POST['tipo_pessoa'] == '2' ? 'Jurídica' : 'Física';
    $nascimento = !empty($_POST['nascimento']) ? DateTime::createFromFormat('d/m/Y', $_POST['nascimento']) : null;
    $nascimento_sql = $nascimento ? $nascimento->format('Y-m-d') : null;
    
    // Tratando o investidor
    $investidor_id = isset($_POST['investidor_id']) ? $_POST['investidor_id'] : null;
    if (empty($investidor_id) && (isset($_SESSION['nivel_autoridade']) && ($_SESSION['nivel_autoridade'] == 'administrador' || $_SESSION['nivel_autoridade'] == 'superadmin'))) {
        $investidor_id = $_SESSION['usuario_id'] ?? null;
    }

    if (!empty($_POST['id'])) {
        // UPDATE
        $sql = "UPDATE clientes SET
            nome = ?, email = ?, telefone = ?, tipo_pessoa = ?, cpf_cnpj = ?, nascimento = ?,
            cep = ?, endereco = ?, bairro = ?, cidade = ?, estado = ?, status = ?,
            chave_pix = ?, indicacao = ?, nome_secundario = ?, telefone_secundario = ?,
            endereco_secundario = ?, observacoes = ?
            WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro ao preparar UPDATE: " . $conn->error);
        }

        $stmt->bind_param(
            "ssssssssssssssssssi",
            $_POST['nome'],
            $_POST['email'],
            $_POST['telefone'],
            $tipo_pessoa,
            $_POST['cpf_cnpj'],
            $nascimento_sql,
            $_POST['cep'],
            $_POST['endereco'],
            $_POST['bairro'],
            $_POST['cidade'],
            $_POST['estado'],
            $_POST['status'],
            $_POST['chave_pix'],
            $investidor_id,
            $_POST['nome_secundario'],
            $_POST['telefone_secundario'],
            $_POST['endereco_secundario'],
            $_POST['observacoes'],
            $_POST['id']
        );

        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar UPDATE: " . $stmt->error);
        }

        $_SESSION['sucesso_edicao'] = true;
        $_SESSION['id_editado'] = $_POST['id'];

        $stmt->close();
        $conn->close();
        header('Location: index.php');
        exit;

    } else {
        // INSERT
        $sql = "INSERT INTO clientes (
            nome, email, telefone, tipo_pessoa, cpf_cnpj, nascimento,
            cep, endereco, bairro, cidade, estado, status,
            chave_pix, indicacao, nome_secundario, telefone_secundario,
            endereco_secundario, observacoes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro ao preparar INSERT: " . $conn->error);
        }

        $stmt->bind_param(
            "ssssssssssssssssss",
            $_POST['nome'],
            $_POST['email'],
            $_POST['telefone'],
            $tipo_pessoa,
            $_POST['cpf_cnpj'],
            $nascimento_sql,
            $_POST['cep'],
            $_POST['endereco'],
            $_POST['bairro'],
            $_POST['cidade'],
            $_POST['estado'],
            $_POST['status'],
            $_POST['chave_pix'],
            $investidor_id,
            $_POST['nome_secundario'],
            $_POST['telefone_secundario'],
            $_POST['endereco_secundario'],
            $_POST['observacoes']
        );

        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar INSERT: " . $stmt->error);
        }

        $_SESSION['sucesso'] = true;
        $_SESSION['ultimo_id'] = $conn->insert_id;

        $stmt->close();
        $conn->close();

        header('Location: index.php');
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo "<pre>Erro interno:\n" . $e->getMessage() . "</pre>";
    exit;
}
