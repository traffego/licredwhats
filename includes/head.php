<!DOCTYPE html>
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/funcoes_data.php';
require_once __DIR__ . '/funcoes_moeda.php';

// Verificar se estamos na página de login antes de incluir autenticacao.php
$pagina_atual = basename($_SERVER['PHP_SELF']);
if ($pagina_atual !== 'login.php') {
    require_once __DIR__ . '/autenticacao.php';
    
    // Verificar e atualizar parcelas vencidas (no máximo a cada 10 minutos)
    require_once __DIR__ . '/verificar_parcelas.php';
    $verificacao_parcelas = verificarAtualizarParcelasVencidas();
    
    // Se ocorreu algum erro sério, registra para debug
    if (isset($verificacao_parcelas['erros']) && $verificacao_parcelas['erros'] > 0) {
        error_log("Erro ao verificar parcelas: " . json_encode($verificacao_parcelas));
    }
}
?>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Empréstimos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tiny-slider/2.9.4/tiny-slider.css">
    <script src="<?= BASE_URL ?>assets/js/mascaras.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilo2.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilo.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/volt.css">
    <script src="<?= BASE_URL ?>assets/js/logo-detector.js"></script>
    <?php if (isset($scripts_header)) echo $scripts_header; ?>
</head>
<?php 

$pagina_atual = basename($_SERVER['PHP_SELF']);
if ($pagina_atual !== 'login.php') {
    require_once 'navbar.php';
}

?>

<body>