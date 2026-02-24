<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

// Verificar se o usuário tem permissão de superadmin
$nivel_usuario = $_SESSION['nivel_autoridade'] ?? '';
if ($nivel_usuario !== 'superadmin') {
    echo '<div class="container py-4"><div class="alert alert-danger">Você não tem permissão para acessar esta página. Apenas superadmins podem limpar o sistema.</div></div>';
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Processar a limpeza do sistema
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_limpeza'])) {
    // Verificar senha do administrador
    $senha_informada = $_POST['senha_admin'] ?? '';
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    
    // Buscar hash da senha do usuário no banco
    $sql_usuario = "SELECT senha FROM usuarios WHERE id = ? AND nivel_autoridade = 'superadmin' LIMIT 1";
    $stmt = $conn->prepare($sql_usuario);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado && $resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();
        $hash_senha = $usuario['senha'];
        
        // Verificar se a senha está correta
        if (password_verify($senha_informada, $hash_senha)) {
            // Iniciar transação
            $conn->begin_transaction();
            
            try {
                // Desabilitar verificação de chaves estrangeiras
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");

                // Registrar início da operação em log
                $log_operacao = "Iniciando operação de limpeza do sistema pelo usuário ID {$usuario_id}";
                error_log($log_operacao);
                
                // 1. Eliminar registros de parcelas
                $conn->query("DELETE FROM parcelas");
                
                // 2. Eliminar registros de controle_comissoes
                $conn->query("DELETE FROM controle_comissoes");
                
                // Retorno de capital agora é controlado apenas pela tabela movimentacoes_contas
                
                // 4. Eliminar registros de movimentacoes_contas
                $conn->query("DELETE FROM movimentacoes_contas");
                
                // 5. Eliminar registros de empréstimos
                $conn->query("DELETE FROM emprestimos");
                
                // 6. Eliminar registros de clientes
                $conn->query("DELETE FROM clientes");

                // 7. Excluir a tabela retorno_capital que não será mais usada
                $conn->query("DROP TABLE IF EXISTS retorno_capital");
                
                // 7. Resetar as sequências das tabelas (IDs)
                $conn->query("ALTER TABLE parcelas AUTO_INCREMENT = 1");
                $conn->query("ALTER TABLE controle_comissoes AUTO_INCREMENT = 1");
                // Retorno de capital agora é controlado apenas pela tabela movimentacoes_contas
                $conn->query("ALTER TABLE movimentacoes_contas AUTO_INCREMENT = 1");
                $conn->query("ALTER TABLE emprestimos AUTO_INCREMENT = 1");
                $conn->query("ALTER TABLE clientes AUTO_INCREMENT = 1");
                
                // 8. Não é necessário resetar os saldos das contas, pois eles são calculados dinamicamente
                // a partir das movimentações, que já foram excluídas no passo 4
                
                // Reabilitar verificação de chaves estrangeiras
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");

                // Confirmar transação
                $conn->commit();
                
                $mensagem = "Sistema limpo com sucesso! Todos os dados de clientes, empréstimos, parcelas e movimentações foram excluídos.";
                $tipo_mensagem = "success";
                
            } catch (Exception $e) {
                // Reverter em caso de erro
                $conn->rollback();
                $mensagem = "Erro ao limpar o sistema: " . $e->getMessage();
                $tipo_mensagem = "danger";
                error_log("Erro na limpeza do sistema: " . $e->getMessage());
            }
        } else {
            $mensagem = "Senha incorreta! Operação cancelada.";
            $tipo_mensagem = "danger";
        }
    } else {
        $mensagem = "Usuário não encontrado ou não tem permissão de superadmin.";
        $tipo_mensagem = "danger";
    }
}
?>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Limpar Sistema</h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Voltar
            </a>
        </div>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show" role="alert">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>
        
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>AVISO: Operação Irreversível</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>ATENÇÃO!</h4>
                    <p>Esta operação irá <strong>EXCLUIR PERMANENTEMENTE</strong> todos os seguintes dados do sistema:</p>
                    <ul>
                        <li>Todos os cadastros de clientes</li>
                        <li>Todos os empréstimos registrados</li>
                        <li>Todas as parcelas e pagamentos</li>
                        <li>Todos os registros de comissões</li>
                        <li>Todas as movimentações de contas</li>
                        <li>Todos os registros de retorno de capital</li>
                    </ul>
                    <p>Os saldos das contas serão redefinidos para seus valores iniciais.</p>
                    <p class="mb-0"><strong>Esta ação não pode ser desfeita!</strong> É altamente recomendável fazer um backup do banco de dados antes de prosseguir.</p>
                </div>
                
                <form method="post" action="" id="form-limpar-sistema">
                    <div class="mb-3">
                        <label for="senha_admin" class="form-label">Confirme sua senha de superadmin:</label>
                        <input type="password" class="form-control" id="senha_admin" name="senha_admin" required>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="confirmar_entendimento" required>
                        <label class="form-check-label" for="confirmar_entendimento">
                            Eu entendo que essa ação irá excluir <strong>PERMANENTEMENTE</strong> todos os dados do sistema e não poderá ser desfeita.
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" id="btn-confirmar-limpeza" name="confirmar_limpeza" class="btn btn-danger" disabled>
                            <i class="bi bi-trash3-fill me-2"></i>Limpar Sistema
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Habilitar botão somente após marcar checkbox
            const checkbox = document.getElementById('confirmar_entendimento');
            const btnConfirmar = document.getElementById('btn-confirmar-limpeza');
            
            checkbox.addEventListener('change', function() {
                btnConfirmar.disabled = !this.checked;
            });
            
            // Confirmação adicional antes de enviar o formulário
            document.getElementById('form-limpar-sistema').addEventListener('submit', function(e) {
                if (!confirm('AVISO FINAL: Você está prestes a excluir TODOS OS DADOS do sistema. Esta ação é IRREVERSÍVEL. Deseja realmente continuar?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 