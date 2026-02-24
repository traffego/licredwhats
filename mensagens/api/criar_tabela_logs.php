<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/conexao.php';
require_once __DIR__ . '/../../includes/autenticacao.php';

// Verificar se o usuário tem permissão de administrador
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    echo "Acesso negado. Apenas administradores podem executar esta operação.";
    exit;
}

// SQL para criar a tabela mensagens_log
$sql_criar_tabela = "
CREATE TABLE IF NOT EXISTS mensagens_log (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    emprestimo_id INT(11) NULL,
    parcela_id INT(11) NULL,
    template_id INT(11) NULL,
    telefone VARCHAR(20) NOT NULL,
    mensagem TEXT NOT NULL,
    data_envio DATETIME NOT NULL,
    usuario_id INT(11) NOT NULL,
    status ENUM('sucesso', 'erro') NOT NULL DEFAULT 'sucesso',
    erro TEXT NULL,
    INDEX (emprestimo_id),
    INDEX (parcela_id),
    INDEX (template_id),
    INDEX (usuario_id),
    INDEX (data_envio),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Executar a query
try {
    if ($conn->query($sql_criar_tabela)) {
        echo "Tabela mensagens_log criada com sucesso!";
    } else {
        echo "Erro ao criar tabela: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
} 