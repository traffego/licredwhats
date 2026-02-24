<?php
/**
 * Função para verificar e atualizar parcelas vencidas
 * Executa no máximo a cada 10 minutos para não sobrecarregar o sistema
 */

// Verificando se já existe uma conexão ativa com o banco de dados
if (!isset($conn) || !$conn instanceof mysqli) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/conexao.php';
}

/**
 * Verifica se é necessário executar a verificação de parcelas
 * baseado no tempo da última verificação
 * @return bool Verdadeiro se deve verificar, falso caso contrário
 */
function deveVerificarParcelas() {
    // Se foi solicitada verificação forçada via GET, sempre retorna true
    if (isset($_GET['forcar'])) {
        return true;
    }
    
    // Arquivo que armazena timestamp da última verificação
    $arquivo_cache = __DIR__ . '/../cache/ultima_verificacao_parcelas.txt';
    
    // Intervalo mínimo entre verificações (1 hora = 3600 segundos)
    $intervalo_minimo = 3600;
    
    // Se o diretório cache não existir, cria
    if (!file_exists(__DIR__ . '/../cache')) {
        mkdir(__DIR__ . '/../cache', 0755, true);
    }
    
    // Se o arquivo não existir, devemos verificar
    if (!file_exists($arquivo_cache)) {
        return true;
    }
    
    // Lê o timestamp da última verificação
    $ultima_verificacao = (int)file_get_contents($arquivo_cache);
    $agora = time();
    
    // Se passou o tempo mínimo, devemos verificar
    return ($agora - $ultima_verificacao) >= $intervalo_minimo;
}

/**
 * Atualiza o timestamp da última verificação
 */
function registrarVerificacao() {
    $arquivo_cache = __DIR__ . '/../cache/ultima_verificacao_parcelas.txt';
    file_put_contents($arquivo_cache, time());
}

/**
 * Verifica e atualiza o status das parcelas vencidas
 * @return array Estatísticas das atualizações realizadas
 */
function verificarAtualizarParcelasVencidas() {
    global $conn;
    
    // Se não deve verificar, retorna sem fazer nada
    if (!deveVerificarParcelas()) {
        return [
            'verificado' => false,
            'mensagem' => 'Verificação ignorada: muito recente'
        ];
    }
    
    // Inicializa estatísticas
    $stats = [
        'verificado' => true,
        'emprestimos_verificados' => 0,
        'parcelas_atualizadas' => 0,
        'erros' => 0,
        'data_hora' => date('Y-m-d H:i:s'),
        'log_erros' => [] // Array para armazenar detalhes dos erros
    ];

    try {
        // Data atual para considerar parcelas atrasadas
        $hoje = new DateTime();
        
        // Método mais simples e eficiente: atualizar todas as parcelas de uma vez
        $sql_update_todas = "UPDATE parcelas SET status = 'atrasado' 
                             WHERE status IN ('pendente', 'parcial') 
                             AND DATE(vencimento) < DATE(NOW())
                             AND id IN (
                                SELECT p.id 
                                FROM (SELECT * FROM parcelas) AS p
                                INNER JOIN emprestimos e ON p.emprestimo_id = e.id 
                                WHERE (e.status = 'ativo' OR e.status IS NULL)
                             )";
                             
        $stmt_update_todas = $conn->prepare($sql_update_todas);
        if (!$stmt_update_todas) {
            $erro_mensagem = "Erro ao preparar atualização em massa: " . $conn->error;
            $stats['log_erros'][] = $erro_mensagem;
            throw new Exception($erro_mensagem);
        }
        
        if (!$stmt_update_todas->execute()) {
            $erro_mensagem = "Erro ao executar atualização em massa: " . $stmt_update_todas->error;
            $stats['log_erros'][] = $erro_mensagem;
            throw new Exception($erro_mensagem);
        }
        
        $stats['parcelas_atualizadas'] = $stmt_update_todas->affected_rows;
        $stmt_update_todas->close();
        
        // Verifica e conta os empréstimos afetados
        $sql_count = "SELECT COUNT(DISTINCT emprestimo_id) as total FROM parcelas 
                     WHERE status = 'atrasado'";
        $result_count = $conn->query($sql_count);
        if ($result_count && $row = $result_count->fetch_assoc()) {
            $stats['emprestimos_verificados'] = (int)$row['total'];
        }
        
        // Registra que a verificação foi realizada
        registrarVerificacao();
        
        // Se alguma parcela foi atualizada, registra no log do sistema
        if ($stats['parcelas_atualizadas'] > 0) {
            error_log("[PARCELAS] {$stats['parcelas_atualizadas']} parcelas atualizadas para status 'atrasado'");
        }
        
    } catch (Exception $e) {
        error_log("Erro ao atualizar parcelas: " . $e->getMessage());
        $stats['erros']++;
        $stats['log_erros'][] = "Erro geral: " . $e->getMessage();
    }
    
    return $stats;
}

// Se este arquivo for executado diretamente (via URL ou CLI)
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    // Força a verificação quando o arquivo é acessado diretamente
    $_GET['forcar'] = true;
    
    $resultado = verificarAtualizarParcelasVencidas();
    
    // Verifica se estamos em ambiente web ou CLI
    if (php_sapi_name() !== 'cli') {
        // Interface web
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <title>Verificação de Parcelas - Sistema de Empréstimos</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
        </head>
        <body class="bg-light">
            <div class="container py-5">
                <div class="card shadow-sm mx-auto" style="max-width: 600px;">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-clock-history"></i> Verificação de Parcelas</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!$resultado['verificado']): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <strong>Verificação ignorada:</strong> A última verificação foi realizada há menos de 10 minutos.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <strong>Verificação concluída com sucesso!</strong>
                            </div>
                            <ul class="list-group mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Empréstimos verificados
                                    <span class="badge bg-primary rounded-pill"><?= $resultado['emprestimos_verificados'] ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Parcelas atualizadas
                                    <span class="badge bg-success rounded-pill"><?= $resultado['parcelas_atualizadas'] ?></span>
                                </li>
                                <?php if ($resultado['erros'] > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Erros encontrados
                                    <span class="badge bg-danger rounded-pill"><?= $resultado['erros'] ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                            
                            <?php if (!empty($resultado['log_erros'])): ?>
                            <div class="mt-3">
                                <h5 class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Detalhes dos Erros</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($resultado['log_erros'] as $erro): ?>
                                            <li class="list-group-item list-group-item-danger">
                                                <?= htmlspecialchars($erro) ?>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <p class="mb-0"><small class="text-muted">Data/hora: <?= date('d/m/Y H:i:s') ?></small></p>
                            <?php 
                            $arquivo_cache = __DIR__ . '/../cache/ultima_verificacao_parcelas.txt';
                            if (file_exists($arquivo_cache)) {
                                $ultima = date('d/m/Y H:i:s', file_get_contents($arquivo_cache));
                                echo '<p class="mb-0"><small class="text-muted">Última verificação: ' . $ultima . '</small></p>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="<?= BASE_URL ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-house-fill"></i> Voltar para o Dashboard
                        </a>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="window.location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Verificar Novamente
                        </button>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    } else {
        // Interface CLI
        echo "Verificação de parcelas concluída: " . date('Y-m-d H:i:s') . "\n";
        if (!$resultado['verificado']) {
            echo "Verificação ignorada: muito recente\n";
        } else {
            echo "- Empréstimos verificados: " . $resultado['emprestimos_verificados'] . "\n";
            echo "- Parcelas atualizadas: " . $resultado['parcelas_atualizadas'] . "\n";
            echo "- Erros: " . $resultado['erros'] . "\n";
        }
    }
} 