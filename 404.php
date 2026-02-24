<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/head.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body text-center p-5">
                    <h1 class="display-1 text-danger mb-4">404</h1>
                    <h2 class="mb-4">Página não encontrada</h2>
                    <p class="lead mb-5">A página que você está procurando não existe ou foi movida para outro endereço.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="<?= BASE_URL ?>" class="btn btn-primary">
                            <i class="bi bi-house-door me-2"></i>Página Inicial
                        </a>
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?> 