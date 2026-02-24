<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/autenticacao.php';

$pagina_atual = basename($_SERVER['PHP_SELF']);
$is_admin = temPermissao('admin');
$is_investidor = isset($_SESSION['nivel_autoridade']) && $_SESSION['nivel_autoridade'] === 'investidor';

$menu_items = array();

// Menu para todos os usuários
$menu_items[] = array(
    'link' => 'dashboard.php',
    'icon' => 'fas fa-tachometer-alt',
    'texto' => 'Dashboard'
);

// Menu para administradores
if (temPermissao('administrador')) {
    // Dropdown Empréstimos
    $menu_items[] = array(
        'tipo' => 'dropdown',
        'texto' => 'Empréstimos',
        'icon' => 'fas fa-money-bill-wave',
        'itens' => array(
            array(
                'link' => 'emprestimos/',
                'texto' => 'Listar'
            ),
            array(
                'link' => 'emprestimos/novo.php',
                'texto' => 'Novo'
            )
        )
    );
    
    // Dropdown Clientes
    $menu_items[] = array(
        'tipo' => 'dropdown',
        'texto' => 'Clientes',
        'icon' => 'fas fa-users',
        'itens' => array(
            array(
                'link' => 'clientes/',
                'texto' => 'Listar'
            ),
            array(
                'link' => 'clientes/novo.php',
                'texto' => 'Novo'
            )
        )
    );
    
    $menu_items[] = array(
        'link' => 'usuarios/',
        'icon' => 'fas fa-user-shield',
        'texto' => 'Usuários'
    );
    
    $menu_items[] = array(
        'link' => 'relatorios/',
        'icon' => 'fas fa-chart-bar',
        'texto' => 'Relatórios'
    );
}

// Menu para investidores
if (temPermissao('investidor')) {
    $menu_items[] = array(
        'link' => 'configuracoes/contas.php',
        'icon' => 'fas fa-wallet',
        'texto' => 'Contas'
    );
}
?>

<!-- Barra superior com informações de parcelas (visível apenas para administradores) -->
<?php if ($is_admin): ?>
<div class="topbar bg-dark text-white py-1">
    <div class="container d-flex justify-content-between align-items-center">
        <?php
        // Indicador de última verificação de parcelas
        $arquivo_cache = __DIR__ . '/../cache/ultima_verificacao_parcelas.txt';
        if (file_exists($arquivo_cache)) {
            $ultima_verificacao = file_get_contents($arquivo_cache);
            $data_hora_verificacao = date('d/m/Y H:i', (int)$ultima_verificacao);
            
            // Calcula tempo desde a última verificação
            $agora = time();
            $minutos_desde_ultima = floor(($agora - (int)$ultima_verificacao) / 60);
            
            // Define a cor baseada no tempo desde a última verificação
            if ($minutos_desde_ultima < 30) {
                $status_cor = "success"; // Verde para verificação recente
            } elseif ($minutos_desde_ultima < 120) {
                $status_cor = "warning"; // Amarelo para verificação entre 30min e 2h
            } else {
                $status_cor = "danger"; // Vermelho para verificação antiga (mais de 2h)
            }
        ?>
            <div class="d-flex align-items-center">
                <span class="badge text-bg-<?= $status_cor ?> me-2">
                    <i class="bi bi-clock-history"></i>
                </span>
                <small>
                    Última verificação de parcelas: <?= $data_hora_verificacao ?>
                </small>
            </div>
            <a href="<?= BASE_URL ?>includes/verificar_parcelas.php" class="btn btn-sm btn-<?= $status_cor ?>" title="Atualizar parcelas">
                <i class="bi bi-arrow-clockwise"></i> Atualizar Parcelas
            </a>
        <?php 
        } else {
        ?>
            <div class="d-flex align-items-center">
                <span class="badge text-bg-secondary me-2">
                    <i class="bi bi-exclamation-circle"></i>
                </span>
                <small>
                    Parcelas não verificadas ainda
                </small>
            </div>
            <a href="<?= BASE_URL ?>includes/verificar_parcelas.php" class="btn btn-sm btn-light" title="Verificar parcelas agora">
                <i class="bi bi-arrow-clockwise"></i> Verificar Parcelas
            </a>
        <?php
        }
        ?>
    </div>
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-blue-dark">
    <div class="container">
        <!-- Logo e Nome -->
        <a class="navbar-brand" href="<?= $is_investidor ? BASE_URL . 'investidor.php' : BASE_URL ?>">
            <img id="logo-img" src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" height="30" class="me-2">
        </a>

        <!-- Botão Mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu Principal -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto">
                <?php foreach ($menu_items as $item): ?>
                    <?php if (isset($item['tipo']) && $item['tipo'] === 'dropdown'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="<?= $item['icon']; ?> me-1"></i>
                                <?= $item['texto']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php foreach ($item['itens'] as $subitem): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?= BASE_URL . $subitem['link']; ?>">
                                            <?= $subitem['texto']; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $pagina_atual === $item['link'] ? 'active' : '' ?>" 
                               href="<?= BASE_URL . $item['link']; ?>">
                                <i class="<?= $item['icon']; ?> me-1"></i>
                                <?= $item['texto']; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <!-- Menu do Usuário -->
            <div class="dropdown">
                <a class="nav-link dropdown-toggle text-white active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i>
                    <span class="d-none d-lg-inline">
                        <?= $is_admin ? 'Admin' : 'Investidor' ?>
                    </span>
                    <span class="d-lg-none">Ver Perfil</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($is_admin): ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>usuarios/"><i class="bi bi-people me-2"></i>Usuários</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>relatorios/"><i class="bi bi-bar-chart me-2"></i>Relatórios</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>configuracoes/"><i class="bi bi-gear me-2"></i>Configurações</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php else: ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>configuracoes/perfil.php"><i class="bi bi-person me-2"></i>Meu Perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Botão de Fechar Mobile -->
<button class="btn-close btn-close-white d-lg-none position-fixed end-0 m-3" 
        style="z-index: 1050; top: 40px;" 
        data-bs-toggle="collapse" 
        data-bs-target="#navbarMain"></button>

<style>
/* Estilos para a barra superior */
.topbar {
    font-size: 0.85rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    position: relative;
    z-index: 1060; /* Valor maior que a navbar e outros elementos */
}

/* Animação sutil para destacar a topbar quando a página carrega */
@keyframes highlight-topbar {
    0% { background-color: #343a40; }
    50% { background-color: #495057; }
    100% { background-color: #343a40; }
}

.topbar {
    animation: highlight-topbar 2s ease-in-out;
}

.topbar .badge {
    padding: 0.35em 0.5em;
}

.topbar .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

@media (max-width: 576px) {
    .topbar small {
        font-size: 0.7rem;
    }
    
    .topbar .btn-sm {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
    }
}

.navbar {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.navbar .container {
    max-width: 1200px;
}

.navbar-brand {
    position: relative;
    padding: 0.5rem;
    border-radius: 12px;
    background: rgba(229, 213, 213, 0.39);
    backdrop-filter: blur(1px);
    -webkit-backdrop-filter: blur(1px);
}

.navbar-brand img {
    position: relative;
    z-index: 1;
}

.nav-link {
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.nav-link:hover {
    background-color: rgba(255,255,255,0.1);
    border-radius: 0.25rem;
}

.nav-link.active {
    background-color: rgba(255,255,255,0.2);
    border-radius: 0.25rem;
}

.dropdown-menu {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 0.5rem;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
}

.dropdown-item:hover {
    background-color: rgba(13,110,253,0.1);
}

.dropdown-item i {
    width: 1.25rem;
    text-align: center;
}

@media (max-width: 991.98px) {
    .navbar-collapse {
        padding: 1rem 0;
        position: fixed;
        top: 37px; /* Deixa espaço para a topbar */
        left: 0;
        bottom: 0;
        width: 80%;
        max-width: 320px;
        background-color: var(--bs-primary);
        z-index: 1040; /* Menor que a topbar */
        overflow-y: auto;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .navbar-collapse.show {
        transform: translateX(0);
    }
    
    /* Ajustes para garantir que a topbar fique visível em celulares pequenos */
    .topbar .container {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .topbar small {
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .dropdown-menu {
        border: none;
        box-shadow: none;
        background-color: rgba(255,255,255,0.1);
        padding-left: 1rem;
        position: static !important;
        transform: none !important;
    }
    
    .dropdown-item {
        color: rgba(255,255,255,0.8);
    }
    
    .dropdown-item:hover {
        background-color: rgba(255,255,255,0.1);
        color: white;
    }
    
    .nav-item.dropdown .dropdown-menu {
        display: none;
    }
    
    .nav-item.dropdown.show .dropdown-menu {
        display: block;
    }
}

/* Animação de pulso para o botão de atualizar */
@keyframes btn-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.btn-pulse {
    animation: btn-pulse 1s ease-in-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Para dispositivos móveis, substitui o comportamento padrão do dropdown
    if (window.innerWidth < 992) {
        // Previne o comportamento padrão em mobile e implementa toggle manual
        document.querySelectorAll('.dropdown-toggle').forEach(function(dropdown) {
            dropdown.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const parent = this.parentElement;
                
                // Fecha outros dropdowns
                document.querySelectorAll('.nav-item.dropdown.show').forEach(function(item) {
                    if (item !== parent) {
                        item.classList.remove('show');
                        item.querySelector('.dropdown-menu').style.display = 'none';
                    }
                });
                
                // Toggle do dropdown atual
                parent.classList.toggle('show');
                const menu = parent.querySelector('.dropdown-menu');
                menu.style.display = parent.classList.contains('show') ? 'block' : 'none';
            });
        });
        
        // Quando clicar em um item do dropdown, fecha o menu
        document.querySelectorAll('.dropdown-item').forEach(function(item) {
            item.addEventListener('click', function() {
                document.querySelector('.navbar-collapse').classList.remove('show');
                updateCloseButton();
            });
        });
    } else {
        // Em desktop usa o comportamento padrão do Bootstrap
        var dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(function(dropdown) {
            new bootstrap.Dropdown(dropdown);
        });
    }

    // Gerencia o botão de fechar mobile
    const navbarToggler = document.querySelector('.navbar-toggler');
    const closeButton = document.querySelector('.btn-close');
    const navbarCollapse = document.querySelector('.navbar-collapse');

    function updateCloseButton() {
        if (window.innerWidth < 992) {
            closeButton.style.display = navbarCollapse.classList.contains('show') ? 'block' : 'none';
        } else {
            closeButton.style.display = 'none';
        }
    }

    // Fecha o menu mobile quando um item é clicado
    document.querySelectorAll('.navbar-nav .nav-link:not(.dropdown-toggle)').forEach(function(navLink) {
        navLink.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                navbarCollapse.classList.remove('show');
                updateCloseButton();
            }
        });
    });

    navbarToggler.addEventListener('click', function() {
        setTimeout(updateCloseButton, 10);
    });

    closeButton.addEventListener('click', function() {
        setTimeout(updateCloseButton, 10);
    });

    // Atualiza o botão de fechar quando a janela é redimensionada
    window.addEventListener('resize', function() {
        updateCloseButton();
        
        // Recarrega a página ao mudar entre desktop e mobile para garantir o comportamento correto
        const wasMobile = window.innerWidth < 992;
        const isMobile = window.innerWidth < 992;
        
        if (wasMobile !== isMobile) {
            // Apenas recarrega se mudar entre mobile e desktop
            location.reload();
        }
    });
    
    updateCloseButton();
    
    // Garantir que a topbar seja sempre visível
    const topbar = document.querySelector('.topbar');
    const navbar = document.querySelector('.navbar');
    
    if (topbar && navbar) {
        // Animar o botão de atualizar parcelas periodicamente para chamar atenção
        const btnAtualizar = topbar.querySelector('a.btn');
        
        // A cada 5 minutos, pisca o botão de atualizar para lembrar o usuário
        if (btnAtualizar) {
            setInterval(function() {
                btnAtualizar.classList.add('btn-pulse');
                setTimeout(function() {
                    btnAtualizar.classList.remove('btn-pulse');
                }, 2000);
            }, 300000); // 5 minutos
        }
    }
});
</script>
