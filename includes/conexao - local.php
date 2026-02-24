<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Configurações do banco de dados
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "sistema_emprestimosv1_8";

try {
    // Criando a conexão
    $conn = new mysqli($host, $usuario, $senha, $banco);

    // Verificando erros
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão: " . $conn->connect_error);
    }

    // Configurando charset
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Erro ao configurar charset: " . $conn->error);
    }

    // Configurando timezone
    $conn->query("SET time_zone = '-03:00'");

} catch (Exception $e) {
    error_log("Erro de conexão: " . $e->getMessage());
    die("Erro de conexão com o banco de dados. Por favor, tente novamente mais tarde.");
}
