<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

// Verifica se o usuário tem permissão adequada
$nivel_usuario = $_SESSION['nivel_autoridade'] ?? '';
if ($nivel_usuario !== 'administrador' && $nivel_usuario !== 'superadmin') {
    echo '<div class="container py-4"><div class="alert alert-danger">Você não tem permissão para acessar esta página.</div></div>';
    exit;
}
?>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container py-4">
        <h1 class="mb-4">Relatórios</h1>
        
        <div class="row g-4">
            <!-- Relatório 1 -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Parcelas em Atraso</h5>
                        <p class="card-text">Exibe todas as parcelas em atraso com informações dos clientes, valores e dias de atraso.</p>
                    </div>
                    <div class="card-footer bg-white border-0">
                        <a href="parcelas_atraso.php" class="btn btn-primary w-100">
                            <i class="bi bi-exclamation-triangle me-2"></i>Visualizar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Relatório 2 -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Pagamentos por Dia</h5>
                        <p class="card-text">Apresenta o total de pagamentos recebidos por dia nos últimos 30 dias.</p>
                    </div>
                    <div class="card-footer bg-white border-0">
                        <a href="pagamentos_diarios.php" class="btn btn-success w-100">
                            <i class="bi bi-calendar-check me-2"></i>Visualizar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Relatório 3 -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Resumo por Cliente</h5>
                        <p class="card-text">Mostra um resumo financeiro de cada cliente com totais emprestados, pagos e pendentes.</p>
                    </div>
                    <div class="card-footer bg-white border-0">
                        <a href="resumo_cliente.php" class="btn btn-info text-white w-100">
                            <i class="bi bi-person-lines-fill me-2"></i>Visualizar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Relatório 4 - Investidores -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Relatório de Investidores</h5>
                        <p class="card-text">Apresenta os dados financeiros dos investidores, seus clientes e empréstimos relacionados.</p>
                    </div>
                    <div class="card-footer bg-white border-0">
                        <a href="investidores_clientes.php" class="btn btn-warning text-dark w-100">
                            <i class="bi bi-people-fill me-2"></i>Visualizar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 