<?php
$titulo_pagina = "Configurações do Sistema";
$scripts_header = '
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<style>
    /* Estilo para as abas de navegação */
    #configTabs {
        display: flex;
        justify-content: space-between;
        padding: 0 10px;
    }
    
    .nav-tabs .nav-item {
        margin-right: 5px;
    }
    
    .nav-tabs .nav-link {
        color: #fff;
        background-color: #1e293b;
        border: none;
        border-radius: 5px 5px 0 0;
        padding: 10px 15px;
    }
    
    .nav-tabs .nav-link:hover {
        background-color: #0d6efd;
    }
    
    .nav-tabs .nav-link.active {
        background-color: #0d6efd;
        color: #fff !important;
        font-weight: 500;
    }
    
    /* Estilo para campos de formulário */
    .form-control {
        width: 100%;
    }
    
    /* Estilo para botões de senha */
    .toggle-password {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
    
    .input-group {
        width: 100%;
    }
</style>
';

require_once '../config.php';
require_once '../includes/conexao.php';
require_once '../includes/autenticacao.php';

// Verificar se o usuário tem permissão de administrador
if (!isset($_SESSION['usuario_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// Considerando apenas o usuário com ID 1 como administrador
$usuario_id = $_SESSION['usuario_id'];
$is_admin = ($usuario_id == 1);

if (!$is_admin) {
    header("Location: " . BASE_URL . "dashboard.php?erro=sem_permissao");
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Verificar se a tabela de configurações existe
$verificar_tabela = $conn->query("SHOW TABLES LIKE 'configuracoes'");
if ($verificar_tabela->num_rows === 0) {
    // Criar a tabela se não existir
    $sql_criar_tabela = "
    CREATE TABLE configuracoes (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nome_empresa VARCHAR(255) NOT NULL DEFAULT 'Minha Empresa',
        email_sistema VARCHAR(255) NOT NULL DEFAULT 'contato@minhaempresa.com',
        telefone_sistema VARCHAR(20) NOT NULL DEFAULT '(00) 00000-0000',
        cpf_cnpj VARCHAR(20) NOT NULL DEFAULT '',
        endereco TEXT NOT NULL DEFAULT '',
        
        efi_client_id VARCHAR(255) DEFAULT NULL,
        efi_client_secret VARCHAR(255) DEFAULT NULL,
        efi_chave_aleatoria VARCHAR(255) DEFAULT NULL,
        efi_certificado TEXT DEFAULT NULL,
        
        mercadopago_public_key VARCHAR(255) DEFAULT NULL,
        mercadopago_access_token VARCHAR(255) DEFAULT NULL,
        
        menuia_endpoint VARCHAR(255) DEFAULT 'https://chatbot.menuia.com',
        menuia_app_key VARCHAR(255) DEFAULT NULL,
        menuia_auth_key VARCHAR(255) DEFAULT NULL,
        
        whatsapp_provider ENUM('menuia','zapi') DEFAULT 'menuia',
        zapi_instance_id VARCHAR(255) DEFAULT NULL,
        zapi_token VARCHAR(255) DEFAULT NULL,
        zapi_client_token VARCHAR(255) DEFAULT NULL,
        
        chave_pix VARCHAR(255) DEFAULT NULL,
        saldo_inicial DECIMAL(15,2) DEFAULT 0.00,
        
        logo VARCHAR(255) DEFAULT NULL,
        icone VARCHAR(255) DEFAULT NULL,
        
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($sql_criar_tabela)) {
        // Inserir configuração inicial
        $sql_inicial = "INSERT INTO configuracoes (
            nome_empresa, 
            email_sistema, 
            telefone_sistema, 
            cpf_cnpj, 
            endereco,
            menuia_endpoint
        ) VALUES (
            'Sistema de Empréstimos', 
            'contato@sistemaemprestimos.com', 
            '(00) 00000-0000', 
            '', 
            'Rua Exemplo, 123 - Centro - Cidade',
            'https://chatbot.menuia.com'
        )";
        
        if ($conn->query($sql_inicial)) {
            $mensagem = 'Tabela de configurações criada com sucesso!';
            $tipo_mensagem = 'success';
        } else {
            $mensagem = 'Erro ao inserir configuração inicial: ' . $conn->error;
            $tipo_mensagem = 'danger';
        }
    } else {
        $mensagem = 'Erro ao criar tabela de configurações: ' . $conn->error;
        $tipo_mensagem = 'danger';
    }
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
    // Extrair os dados do formulário
    $nome_empresa = trim($_POST['nome_empresa'] ?? '');
    $email_sistema = trim($_POST['email_sistema'] ?? '');
    $telefone_sistema = trim($_POST['telefone_sistema'] ?? '');
    $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    
    $efi_client_id = trim($_POST['efi_client_id'] ?? '');
    $efi_client_secret = trim($_POST['efi_client_secret'] ?? '');
    $efi_chave_aleatoria = trim($_POST['efi_chave_aleatoria'] ?? '');
    
    // Processar o arquivo de certificado EFI Bank, se enviado
    if (isset($_FILES['efi_certificado_file']) && $_FILES['efi_certificado_file']['size'] > 0) {
        $cert_tmp = $_FILES['efi_certificado_file']['tmp_name'];
        $cert_nome = $_FILES['efi_certificado_file']['name'];
        $cert_extensao = strtolower(pathinfo($cert_nome, PATHINFO_EXTENSION));
        
        // Verificar extensão do arquivo
        if (in_array($cert_extensao, ['pem', 'crt', 'key'])) {
            // Ler o conteúdo do arquivo
            $efi_certificado = file_get_contents($cert_tmp);
            
            if ($efi_certificado === false) {
                $mensagem .= 'Erro ao ler o arquivo de certificado. ';
                $tipo_mensagem = 'danger';
            }
        } else {
            $mensagem .= 'O arquivo de certificado deve ter extensão .pem, .crt ou .key. ';
            $tipo_mensagem = 'danger';
        }
    } else {
        // Usar valor atual do formulário
        $efi_certificado = trim($_POST['efi_certificado'] ?? '');
    }
    
    $mercadopago_public_key = trim($_POST['mercadopago_public_key'] ?? '');
    $mercadopago_access_token = trim($_POST['mercadopago_access_token'] ?? '');
    
    $menuia_endpoint = trim($_POST['menuia_endpoint'] ?? '');
    $menuia_app_key = trim($_POST['menuia_app_key'] ?? '');
    $menuia_auth_key = trim($_POST['menuia_auth_key'] ?? '');
    
    $whatsapp_provider = trim($_POST['whatsapp_provider'] ?? 'menuia');
    $zapi_instance_id = trim($_POST['zapi_instance_id'] ?? '');
    $zapi_token = trim($_POST['zapi_token'] ?? '');
    $zapi_client_token = trim($_POST['zapi_client_token'] ?? '');
    
    $chave_pix = trim($_POST['chave_pix'] ?? '');
    $saldo_inicial = str_replace(',', '.', trim($_POST['saldo_inicial'] ?? '0'));
    
    // Processar imagens enviadas
    $logo = null;
    $icone = null;
    $diretorio_upload = '../uploads/';
    
    // Buscar valores atuais de logo e ícone para manter se não houver novos uploads
    $sql_atual = "SELECT logo, icone FROM configuracoes WHERE id = 1";
    $result_atual = $conn->query($sql_atual);
    if ($result_atual && $result_atual->num_rows > 0) {
        $config_atual = $result_atual->fetch_assoc();
        $logo = $config_atual['logo'];
        $icone = $config_atual['icone'];
    }
    
    // Processar logo
    if (isset($_FILES['logo']) && $_FILES['logo']['size'] > 0) {
        $logo_tmp = $_FILES['logo']['tmp_name'];
        $logo_nome = $_FILES['logo']['name'];
        $logo_extensao = strtolower(pathinfo($logo_nome, PATHINFO_EXTENSION));
        
        if ($logo_extensao == 'png') {
            $logo_novo_nome = 'logo_' . time() . '.png';
            if (move_uploaded_file($logo_tmp, $diretorio_upload . $logo_novo_nome)) {
                $logo = $logo_novo_nome;
            } else {
                $mensagem .= 'Erro ao fazer upload da logo. ';
                $tipo_mensagem = 'danger';
            }
        } else {
            $mensagem .= 'O arquivo de logo deve ser PNG. ';
            $tipo_mensagem = 'danger';
        }
    }
    
    // Processar ícone
    if (isset($_FILES['icone']) && $_FILES['icone']['size'] > 0) {
        $icone_tmp = $_FILES['icone']['tmp_name'];
        $icone_nome = $_FILES['icone']['name'];
        $icone_extensao = strtolower(pathinfo($icone_nome, PATHINFO_EXTENSION));
        
        if ($icone_extensao == 'png') {
            $icone_novo_nome = 'icone_' . time() . '.png';
            if (move_uploaded_file($icone_tmp, $diretorio_upload . $icone_novo_nome)) {
                $icone = $icone_novo_nome;
            } else {
                $mensagem .= 'Erro ao fazer upload do ícone. ';
                $tipo_mensagem = 'danger';
            }
        } else {
            $mensagem .= 'O arquivo de ícone deve ser PNG. ';
            $tipo_mensagem = 'danger';
        }
    }
    
    // Atualizar configurações no banco de dados
    $sql = "UPDATE configuracoes SET 
        nome_empresa = ?,
        email_sistema = ?,
        telefone_sistema = ?,
        cpf_cnpj = ?,
        endereco = ?,
        
        efi_client_id = ?,
        efi_client_secret = ?,
        efi_chave_aleatoria = ?,
        efi_certificado = ?,
        
        mercadopago_public_key = ?,
        mercadopago_access_token = ?,
        
        menuia_endpoint = ?,
        menuia_app_key = ?,
        menuia_auth_key = ?,
        
        whatsapp_provider = ?,
        zapi_instance_id = ?,
        zapi_token = ?,
        zapi_client_token = ?,
        
        chave_pix = ?,
        saldo_inicial = ?";
    
    // Adicionar parâmetros de logo e ícone se foram alterados
    if ($logo !== null) {
        $sql .= ", logo = ?";
    }
    if ($icone !== null) {
        $sql .= ", icone = ?";
    }
    
    $sql .= " WHERE id = 1";
    
    $stmt = $conn->prepare($sql);
    
    // Criar o conjunto de parâmetros
    $param_types = "sssssssssssssssssssd";
    $params = [
        $nome_empresa,
        $email_sistema,
        $telefone_sistema,
        $cpf_cnpj,
        $endereco,
        $efi_client_id,
        $efi_client_secret,
        $efi_chave_aleatoria,
        $efi_certificado,
        $mercadopago_public_key,
        $mercadopago_access_token,
        $menuia_endpoint,
        $menuia_app_key,
        $menuia_auth_key,
        $whatsapp_provider,
        $zapi_instance_id,
        $zapi_token,
        $zapi_client_token,
        $chave_pix,
        $saldo_inicial
    ];
    
    // Adicionar parâmetros de logo e ícone se foram alterados
    if ($logo !== null) {
        $param_types .= "s";
        $params[] = $logo;
    }
    if ($icone !== null) {
        $param_types .= "s";
        $params[] = $icone;
    }
    
    // Executa a atualização
    $stmt->bind_param($param_types, ...$params);
    
    if ($stmt->execute()) {
        $mensagem = 'Configurações atualizadas com sucesso!';
        $tipo_mensagem = 'success';
    } else {
        $mensagem = 'Erro ao atualizar configurações: ' . $stmt->error;
        $tipo_mensagem = 'danger';
    }
    
    $stmt->close();
}

// Buscar configurações atuais
$config = null;
$sql = "SELECT * FROM configuracoes WHERE id = 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $config = $result->fetch_assoc();
}

require_once '../includes/head.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Configurações do Sistema</h1>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <!-- Cards de Acesso Rápido -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-wallet2 text-primary"></i> Contas de Investidores
                    </h5>
                    <p class="card-text">Gerencie contas para investidores depositarem e movimentarem créditos.</p>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="contas.php" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-right-circle"></i> Acessar Contas
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Adicione mais cards aqui para outras funcionalidades relacionadas -->
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-people text-success"></i> Usuários do Sistema
                    </h5>
                    <p class="card-text">Gerenciar usuários e permissões de acesso ao sistema.</p>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../usuarios/" class="btn btn-success w-100">
                        <i class="bi bi-arrow-right-circle"></i> Gerenciar Usuários
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-cash-coin text-info"></i> Solicitações de Saque
                    </h5>
                    <p class="card-text">Gerencie as solicitações de saque dos investidores.</p>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="saques.php" class="btn btn-info text-white w-100">
                        <i class="bi bi-arrow-right-circle"></i> Gerenciar Saques
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-envelope text-warning"></i> Templates de Mensagens
                    </h5>
                    <p class="card-text">Configure modelos de mensagens para envio de cobranças e notificações.</p>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../mensagens/templates_mensagens.php" class="btn btn-warning w-100">
                        <i class="bi bi-arrow-right-circle"></i> Editar Templates
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-danger">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-trash3 text-danger"></i> Limpar Sistema
                    </h5>
                    <p class="card-text">Ferramenta para excluir todos os dados do sistema: clientes, empréstimos, parcelas e movimentações.</p>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="limpar_sistema.php" class="btn btn-outline-danger w-100">
                        <i class="bi bi-exclamation-triangle"></i> Limpar Sistema
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Configurações Gerais -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Configurações Gerais</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="empresa-tab" data-bs-toggle="tab" data-bs-target="#empresa" type="button" role="tab" aria-controls="empresa" aria-selected="true">
                                    <i class="bi bi-building"></i> Empresa
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="efibank-tab" data-bs-toggle="tab" data-bs-target="#efibank" type="button" role="tab" aria-controls="efibank" aria-selected="false">
                                    <i class="bi bi-bank"></i> EFI Bank
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="mercadopago-tab" data-bs-toggle="tab" data-bs-target="#mercadopago" type="button" role="tab" aria-controls="mercadopago" aria-selected="false">
                                    <i class="bi bi-credit-card"></i> Mercado Pago
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="menuia-tab" data-bs-toggle="tab" data-bs-target="#menuia" type="button" role="tab" aria-controls="menuia" aria-selected="false">
                                    <i class="bi bi-whatsapp"></i> WhatsApp
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="financeiro-tab" data-bs-toggle="tab" data-bs-target="#financeiro" type="button" role="tab" aria-controls="financeiro" aria-selected="false">
                                    <i class="bi bi-cash-coin"></i> Financeiro
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="imagens-tab" data-bs-toggle="tab" data-bs-target="#imagens" type="button" role="tab" aria-controls="imagens" aria-selected="false">
                                    <i class="bi bi-image"></i> Imagens
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="configTabsContent">
                            <!-- Aba Empresa -->
                            <div class="tab-pane fade show active" id="empresa" role="tabpanel" aria-labelledby="empresa-tab">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="nome_empresa" class="form-label">Nome da Empresa</label>
                                        <input type="text" class="form-control" id="nome_empresa" name="nome_empresa" value="<?= htmlspecialchars($config['nome_empresa'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cpf_cnpj" class="form-label">CPF/CNPJ</label>
                                        <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?= htmlspecialchars($config['cpf_cnpj'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email_sistema" class="form-label">Email do Sistema</label>
                                        <input type="email" class="form-control" id="email_sistema" name="email_sistema" value="<?= htmlspecialchars($config['email_sistema'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="telefone_sistema" class="form-label">Telefone do Sistema</label>
                                        <input type="text" class="form-control telefone" id="telefone_sistema" name="telefone_sistema" value="<?= htmlspecialchars($config['telefone_sistema'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label for="endereco" class="form-label">Endereço Completo</label>
                                        <textarea class="form-control" id="endereco" name="endereco" rows="3"><?= htmlspecialchars($config['endereco'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Aba EFI Bank -->
                            <div class="tab-pane fade" id="efibank" role="tabpanel" aria-labelledby="efibank-tab">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="efi_client_id" class="form-label">Client ID</label>
                                        <input type="text" class="form-control" id="efi_client_id" name="efi_client_id" value="<?= htmlspecialchars($config['efi_client_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="efi_client_secret" class="form-label">Client Secret</label>
                                        <input type="password" class="form-control" id="efi_client_secret" name="efi_client_secret" value="<?= htmlspecialchars($config['efi_client_secret'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="efi_chave_aleatoria" class="form-label">Chave Aleatória</label>
                                        <input type="text" class="form-control" id="efi_chave_aleatoria" name="efi_chave_aleatoria" value="<?= htmlspecialchars($config['efi_chave_aleatoria'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="efi_certificado" class="form-label">Certificado</label>
                                        <input type="file" class="form-control" id="efi_certificado_file" name="efi_certificado_file" accept=".pem,.crt,.key">
                                        <small class="text-muted">Faça upload do arquivo de certificado (.pem, .crt ou .key).</small>
                                        
                                        <?php if (!empty($config['efi_certificado'])): ?>
                                            <div class="alert alert-success mt-2">
                                                <i class="bi bi-check-circle-fill"></i> Certificado atual carregado
                                                <input type="hidden" name="efi_certificado" value="<?= htmlspecialchars($config['efi_certificado']) ?>">
                                            </div>
                                        <?php else: ?>
                                            <input type="hidden" name="efi_certificado" value="">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Aba Mercado Pago -->
                            <div class="tab-pane fade" id="mercadopago" role="tabpanel" aria-labelledby="mercadopago-tab">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="mercadopago_public_key" class="form-label">Public Key</label>
                                        <input type="text" class="form-control" id="mercadopago_public_key" name="mercadopago_public_key" value="<?= htmlspecialchars($config['mercadopago_public_key'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="mercadopago_access_token" class="form-label">Access Token</label>
                                        <input type="password" class="form-control" id="mercadopago_access_token" name="mercadopago_access_token" value="<?= htmlspecialchars($config['mercadopago_access_token'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Aba WhatsApp -->
                            <div class="tab-pane fade" id="menuia" role="tabpanel" aria-labelledby="menuia-tab">
                                <div class="row g-3">
                                    <!-- Seletor de provedor -->
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">Provedor de WhatsApp</label>
                                        <div class="d-flex gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="whatsapp_provider" id="provider_menuia" value="menuia" <?= ($config['whatsapp_provider'] ?? 'menuia') === 'menuia' ? 'checked' : '' ?> onchange="toggleWhatsAppProvider()">
                                                <label class="form-check-label" for="provider_menuia">
                                                    <strong>MenuIA</strong>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="whatsapp_provider" id="provider_zapi" value="zapi" <?= ($config['whatsapp_provider'] ?? 'menuia') === 'zapi' ? 'checked' : '' ?> onchange="toggleWhatsAppProvider()">
                                                <label class="form-check-label" for="provider_zapi">
                                                    <strong>Z-API</strong>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Campos MenuIA -->
                                    <div id="menuia_fields">
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label for="menuia_endpoint" class="form-label">Endpoint</label>
                                                <input type="url" class="form-control" id="menuia_endpoint" name="menuia_endpoint" value="<?= htmlspecialchars($config['menuia_endpoint'] ?? 'https://chatbot.menuia.com') ?>">
                                            </div>
                                            <div class="col-md-12">
                                                <label for="menuia_app_key" class="form-label">App Key</label>
                                                <input type="text" class="form-control" id="menuia_app_key" name="menuia_app_key" value="<?= htmlspecialchars($config['menuia_app_key'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-12">
                                                <label for="menuia_auth_key" class="form-label">Auth Key</label>
                                                <input type="password" class="form-control" id="menuia_auth_key" name="menuia_auth_key" value="<?= htmlspecialchars($config['menuia_auth_key'] ?? '') ?>">
                                            </div>
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <strong>Dica:</strong> Obtenha suas credenciais em <a href="https://menuia.com" target="_blank" class="alert-link">menuia.com</a>.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Campos Z-API -->
                                    <div id="zapi_fields" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label for="zapi_instance_id" class="form-label">Instance ID</label>
                                                <input type="text" class="form-control" id="zapi_instance_id" name="zapi_instance_id" value="<?= htmlspecialchars($config['zapi_instance_id'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-12">
                                                <label for="zapi_token" class="form-label">Token</label>
                                                <input type="password" class="form-control" id="zapi_token" name="zapi_token" value="<?= htmlspecialchars($config['zapi_token'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-12">
                                                <label for="zapi_client_token" class="form-label">Client Token <small class="text-muted">(opcional)</small></label>
                                                <input type="password" class="form-control" id="zapi_client_token" name="zapi_client_token" value="<?= htmlspecialchars($config['zapi_client_token'] ?? '') ?>">
                                            </div>
                                            <div class="col-12">
                                                <div class="alert alert-info mb-0">
                                                    <strong>Dica:</strong> Obtenha suas credenciais em <a href="https://z-api.io" target="_blank" class="alert-link">z-api.io</a>. O Client Token é opcional e pode ser configurado na aba Segurança da sua conta Z-API.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Painel de Conexão Z-API -->
                                        <?php if (!empty($config['zapi_instance_id']) && !empty($config['zapi_token'])): ?>
                                        <div class="mt-4">
                                            <hr>
                                            <h6 class="fw-bold mb-3"><i class="bi bi-plug"></i> Conexão WhatsApp</h6>
                                            
                                            <!-- Status da Conexão -->
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <div id="zapi_status_badge">
                                                    <span class="badge bg-secondary fs-6">
                                                        <i class="bi bi-hourglass-split"></i> Verificando...
                                                    </span>
                                                </div>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" onclick="zapiCheckStatus()" title="Verificar status">
                                                        <i class="bi bi-arrow-clockwise"></i> Verificar
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" id="btn_zapi_disconnect" onclick="zapiDisconnect()" style="display:none;" title="Desconectar">
                                                        <i class="bi bi-x-circle"></i> Desconectar
                                                    </button>
                                                    <button type="button" class="btn btn-outline-success" id="btn_zapi_reconnect" onclick="zapiRestart()" style="display:none;" title="Reconectar">
                                                        <i class="bi bi-arrow-repeat"></i> Reconectar
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- QR Code -->
                                            <div id="zapi_qrcode_area" style="display:none;">
                                                <div class="card border-warning">
                                                    <div class="card-body text-center">
                                                        <p class="text-muted mb-2">Escaneie o QR Code com seu WhatsApp para conectar:</p>
                                                        <div id="zapi_qrcode_img" class="d-inline-block p-2 bg-white rounded shadow-sm">
                                                            <div class="spinner-border text-primary" role="status">
                                                                <span class="visually-hidden">Carregando...</span>
                                                            </div>
                                                        </div>
                                                        <p class="text-muted mt-2 mb-0"><small><i class="bi bi-info-circle"></i> O QR Code atualiza automaticamente a cada 15 segundos</small></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Info conectado -->
                                            <div id="zapi_connected_info" style="display:none;">
                                                <div class="alert alert-success mb-0">
                                                    <i class="bi bi-check-circle-fill"></i> Sua instância está conectada e pronta para enviar mensagens.
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Aba Financeiro -->
                            <div class="tab-pane fade" id="financeiro" role="tabpanel" aria-labelledby="financeiro-tab">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="chave_pix" class="form-label">Chave PIX</label>
                                        <input type="text" class="form-control" id="chave_pix" name="chave_pix" value="<?= htmlspecialchars($config['chave_pix'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="saldo_inicial" class="form-label">Saldo Inicial</label>
                                        <div class="input-group">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" class="form-control moeda" id="saldo_inicial" name="saldo_inicial" value="<?= number_format($config['saldo_inicial'] ?? 0, 2, ',', '.') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Aba Imagens -->
                            <div class="tab-pane fade" id="imagens" role="tabpanel" aria-labelledby="imagens-tab">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="logo" class="form-label">Logo</label>
                                        <input type="file" class="form-control" id="logo" name="logo" accept=".png">
                                        <small class="text-muted">Apenas arquivos PNG. Tamanho recomendado: 200x80px.</small>
                                        
                                        <?php if (!empty($config['logo'])): ?>
                                            <div class="mt-2">
                                                <p>Logo atual:</p>
                                                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" class="img-thumbnail" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="icone" class="form-label">Ícone</label>
                                        <input type="file" class="form-control" id="icone" name="icone" accept=".png">
                                        <small class="text-muted">Apenas arquivos PNG. Tamanho recomendado: 32x32px.</small>
                                        
                                        <?php if (!empty($config['icone'])): ?>
                                            <div class="mt-2">
                                                <p>Ícone atual:</p>
                                                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($config['icone']) ?>" alt="Ícone" class="img-thumbnail" style="max-height: 32px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="submit" name="salvar_config" class="btn btn-primary">
                                <i class="bi bi-save"></i> Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle dos campos de provedor WhatsApp (global para funcionar com onchange inline)
    function toggleWhatsAppProvider() {
        var provider = $('input[name="whatsapp_provider"]:checked').val();
        if (provider === 'zapi') {
            $('#menuia_fields').hide();
            $('#zapi_fields').show();
        } else {
            $('#menuia_fields').show();
            $('#zapi_fields').hide();
        }
    }

    // Máscaras para campos
    $(document).ready(function() {
        $('.telefone').mask('(00) 00000-0000');
        $('.moeda').maskMoney({
            prefix: '',
            thousands: '.',
            decimal: ',',
            allowZero: true
        });
        
        // Máscara dinâmica para CPF/CNPJ
        var cpfCnpjMask = function (val) {
            return val.replace(/\D/g, '').length <= 11 ? '000.000.000-00' : '00.000.000/0000-00';
        },
        cpfCnpjOptions = {
            onKeyPress: function(val, e, field, options) {
                field.mask(cpfCnpjMask.apply({}, arguments), options);
            }
        };
        $('#cpf_cnpj').mask(cpfCnpjMask, cpfCnpjOptions);
        
        // Botões para mostrar/esconder senhas
        function addPasswordToggle(fieldId) {
            var field = $('#' + fieldId);
            
            // Verificar se o campo já está em um input-group
            var parent = field.parent();
            if (!parent.hasClass('input-group')) {
                // Envolver o campo em um input-group se não estiver
                field.wrap('<div class="input-group"></div>');
                parent = field.parent();
            }
            
            // Verificar se o botão já existe
            if (parent.find('.toggle-password[data-target="' + fieldId + '"]').length === 0) {
                var button = $('<button type="button" class="btn btn-outline-secondary toggle-password" data-target="' + fieldId + '"><i class="bi bi-eye"></i></button>');
                parent.append(button);
                
                button.on('click', function() {
                    var target = $(this).data('target');
                    var input = $('#' + target);
                    var icon = $(this).find('i');
                    
                    if (input.attr('type') === 'password') {
                        input.attr('type', 'text');
                        icon.removeClass('bi-eye').addClass('bi-eye-slash');
                    } else {
                        input.attr('type', 'password');
                        icon.removeClass('bi-eye-slash').addClass('bi-eye');
                    }
                });
            }
        }
        
        addPasswordToggle('efi_client_secret');
        addPasswordToggle('mercadopago_access_token');
        addPasswordToggle('menuia_auth_key');
        addPasswordToggle('zapi_token');
        addPasswordToggle('zapi_client_token');
        
        // Aplicar estado inicial dos campos de provedor
        toggleWhatsAppProvider();
        
        // Manter a aba ativa após submit
        var activeTab = localStorage.getItem('activeConfigTab');
        if (activeTab) {
            $('#configTabs button[data-bs-target="' + activeTab + '"]').tab('show');
        }
        
        $('#configTabs button').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeConfigTab', $(e.target).data('bs-target'));
            // Auto-check status quando abrir a aba WhatsApp com Z-API selecionado
            if ($(e.target).data('bs-target') === '#menuia' && $('input[name="whatsapp_provider"]:checked').val() === 'zapi') {
                zapiCheckStatus();
            }
        });
        
        // Auto-check status ao carregar se a aba WhatsApp estiver ativa e Z-API selecionado
        if ($('#menuia').hasClass('show') && $('input[name="whatsapp_provider"]:checked').val() === 'zapi') {
            zapiCheckStatus();
        }
    });

    // ========== Z-API Connection Panel ==========
    var zapiQrInterval = null;
    var zapiQrAttempts = 0;
    var zapiMaxQrAttempts = 20; // ~5 minutos (20 x 15s)

    function zapiCheckStatus() {
        $('#zapi_status_badge').html('<span class="badge bg-secondary fs-6"><i class="bi bi-hourglass-split"></i> Verificando...</span>');
        
        $.ajax({
            url: 'zapi_proxy.php?action=status',
            method: 'GET',
            dataType: 'json',
            timeout: 15000,
            success: function(data) {
                if (data.connected === true) {
                    zapiSetConnected();
                } else {
                    var errorMsg = data.error || 'Não conectado';
                    zapiSetDisconnected(errorMsg);
                }
            },
            error: function(xhr) {
                var msg = 'Erro ao verificar status';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.error) msg = resp.error;
                } catch(e) {}
                zapiSetDisconnected(msg);
            }
        });
    }

    function zapiSetConnected() {
        zapiStopQrPolling();
        $('#zapi_status_badge').html('<span class="badge bg-success fs-6"><i class="bi bi-check-circle-fill"></i> Conectado</span>');
        $('#btn_zapi_disconnect').show();
        $('#btn_zapi_reconnect').hide();
        $('#zapi_qrcode_area').hide();
        $('#zapi_connected_info').show();
    }

    function zapiSetDisconnected(msg) {
        $('#zapi_status_badge').html('<span class="badge bg-danger fs-6"><i class="bi bi-x-circle-fill"></i> Desconectado</span>');
        $('#btn_zapi_disconnect').hide();
        $('#btn_zapi_reconnect').show();
        $('#zapi_connected_info').hide();
        $('#zapi_qrcode_area').show();
        zapiStartQrPolling();
    }

    function zapiLoadQrCode() {
        $.ajax({
            url: 'zapi_proxy.php?action=qrcode',
            method: 'GET',
            dataType: 'json',
            timeout: 15000,
            success: function(data) {
                if (data.value) {
                    // A Z-API retorna a imagem em base64
                    var imgSrc = data.value;
                    // Se não começa com data:, adicionar o prefixo
                    if (!imgSrc.startsWith('data:')) {
                        imgSrc = 'data:image/png;base64,' + imgSrc;
                    }
                    $('#zapi_qrcode_img').html('<img src="' + imgSrc + '" alt="QR Code Z-API" style="max-width: 280px; width: 100%;" class="rounded">');
                    zapiQrAttempts++;
                    
                    // Verificar status após carregar QR (pode já ter conectado)
                    if (zapiQrAttempts % 2 === 0) {
                        zapiSilentStatusCheck();
                    }
                    
                    if (zapiQrAttempts >= zapiMaxQrAttempts) {
                        zapiStopQrPolling();
                        $('#zapi_qrcode_img').html('<div class="text-muted p-3"><i class="bi bi-exclamation-triangle"></i> Tempo expirado. Clique em <strong>Reconectar</strong> para gerar um novo QR Code.</div>');
                    }
                } else if (data.connected === true) {
                    // Já está conectado
                    zapiSetConnected();
                } else {
                    $('#zapi_qrcode_img').html('<div class="text-warning p-3"><i class="bi bi-exclamation-triangle"></i> Aguardando QR Code...</div>');
                }
            },
            error: function() {
                $('#zapi_qrcode_img').html('<div class="text-danger p-3"><i class="bi bi-exclamation-circle"></i> Erro ao carregar QR Code</div>');
            }
        });
    }

    function zapiSilentStatusCheck() {
        $.ajax({
            url: 'zapi_proxy.php?action=status',
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(data) {
                if (data.connected === true) {
                    zapiSetConnected();
                }
            }
        });
    }

    function zapiStartQrPolling() {
        zapiStopQrPolling();
        zapiQrAttempts = 0;
        zapiLoadQrCode();
        zapiQrInterval = setInterval(zapiLoadQrCode, 15000);
    }

    function zapiStopQrPolling() {
        if (zapiQrInterval) {
            clearInterval(zapiQrInterval);
            zapiQrInterval = null;
        }
    }

    function zapiDisconnect() {
        if (!confirm('Tem certeza que deseja desconectar o WhatsApp?')) return;
        
        $('#zapi_status_badge').html('<span class="badge bg-warning fs-6"><i class="bi bi-hourglass-split"></i> Desconectando...</span>');
        
        $.ajax({
            url: 'zapi_proxy.php?action=disconnect',
            method: 'GET',
            dataType: 'json',
            timeout: 15000,
            success: function() {
                zapiSetDisconnected('Desconectado pelo usuário');
            },
            error: function() {
                zapiCheckStatus();
            }
        });
    }

    function zapiRestart() {
        $('#zapi_status_badge').html('<span class="badge bg-warning fs-6"><i class="bi bi-hourglass-split"></i> Reiniciando...</span>');
        $('#zapi_qrcode_img').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div>');
        
        $.ajax({
            url: 'zapi_proxy.php?action=restart',
            method: 'GET',
            dataType: 'json',
            timeout: 15000,
            complete: function() {
                // Aguardar 3 segundos para a instância reiniciar
                setTimeout(function() {
                    zapiCheckStatus();
                }, 3000);
            }
        });
    }
</script>

<?php require_once '../includes/footer.php'; ?> 