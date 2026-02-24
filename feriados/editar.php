<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/queries_feriados.php';

// Título da página
$titulo = "Editar Feriado";

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php?mensagem=erro&texto=ID do feriado não informado");
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: index.php?mensagem=erro&texto=ID do feriado inválido");
    exit;
}

// Buscar o feriado no banco
$feriado = buscarFeriadoPorId($conn, $id);
if (!$feriado) {
    header("Location: index.php?mensagem=erro&texto=Feriado não encontrado");
    exit;
}

// Include do cabeçalho
include_once __DIR__ . '/../includes/head.php';
include_once __DIR__ . '/../includes/navbar.php';

// Verificar se existe mensagem de erro
$mensagem = null;
if (isset($_GET['mensagem'])) {
    $tipo = $_GET['mensagem'] === 'sucesso' ? 'sucesso' : 'erro';
    $mensagem = [
        'tipo' => $tipo,
        'texto' => $_GET['texto'] ?? ($tipo === 'sucesso' ? 'Operação realizada com sucesso' : 'Ocorreu um erro ao processar sua solicitação.')
    ];
}

// Processo de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar e sanitizar os dados recebidos
    $nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS));
    $data = trim(filter_input(INPUT_POST, 'data', FILTER_SANITIZE_SPECIAL_CHARS));
    $tipo = trim(filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_SPECIAL_CHARS));
    $evitar = trim(filter_input(INPUT_POST, 'evitar', FILTER_SANITIZE_SPECIAL_CHARS));
    $local = trim(filter_input(INPUT_POST, 'local', FILTER_SANITIZE_SPECIAL_CHARS));
    
    // Extrair o ano da data
    $ano = date('Y', strtotime($data));
    
    // Verificar se todos os campos obrigatórios foram preenchidos
    if (empty($nome) || empty($data)) {
        $mensagem = [
            'tipo' => 'erro',
            'texto' => 'Os campos Nome e Data são obrigatórios!'
        ];
    } else {
        // Verificar se a data está em um formato válido
        $data_formatada = DateTime::createFromFormat('Y-m-d', $data);
        
        if (!$data_formatada) {
            $mensagem = [
                'tipo' => 'erro',
                'texto' => 'A data informada não é válida!'
            ];
        } else {
            // Preparar os dados para atualização
            $dados_feriado = [
                'nome' => $nome,
                'data' => $data,
                'tipo' => $tipo ?: 'fixo',
                'evitar' => $evitar ?: 'sim_evitar',
                'local' => $local ?: 'nacional',
                'ano' => $ano
            ];
            
            // Verificar se já existe outro feriado nesta data (exceto o atual)
            $feriado_existente = verificarSeDataEFeriado($conn, $data);
            
            if ($feriado_existente && $feriado_existente['id'] != $id) {
                $mensagem = [
                    'tipo' => 'erro',
                    'texto' => 'Já existe outro feriado cadastrado nesta data!'
                ];
            } else {
                // Atualizar o feriado no banco
                $resultado = atualizarFeriado($conn, $id, $dados_feriado);
                
                if ($resultado) {
                    // Redirecionar para a página de listagem
                    header("Location: index.php?ano={$ano}&mensagem=sucesso&texto=Feriado atualizado com sucesso!");
                    exit;
                } else {
                    $mensagem = [
                        'tipo' => 'erro',
                        'texto' => 'Erro ao atualizar o feriado: ' . $conn->error
                    ];
                }
            }
        }
    }
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4><?php echo $titulo; ?></h4>
        </div>
        <div class="card-body">
            <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $mensagem['tipo'] === 'erro' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensagem['texto']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id; ?>" method="POST">
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome do Feriado <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($feriado['nome']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="data" class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="data" name="data" value="<?php echo htmlspecialchars($feriado['data']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="fixo" <?php if ($feriado['tipo'] === 'fixo') echo 'selected'; ?>>Fixo</option>
                        <option value="movel" <?php if ($feriado['tipo'] === 'movel') echo 'selected'; ?>>Móvel</option>
                    </select>
                    <div class="form-text">Feriados fixos ocorrem na mesma data todo ano e serão exibidos automaticamente em todos os anos. Feriados móveis podem variar de data a cada ano.</div>
                </div>
                
                <div class="mb-3">
                    <label for="evitar" class="form-label">Evitar Geração de Parcelas</label>
                    <select class="form-select" id="evitar" name="evitar">
                        <option value="sim_evitar" <?php if ($feriado['evitar'] === 'sim_evitar' || empty($feriado['evitar'])) echo 'selected'; ?>>Sim, evitar</option>
                        <option value="nao_evitar" <?php if ($feriado['evitar'] === 'nao_evitar') echo 'selected'; ?>>Não é necessário evitar</option>
                    </select>
                    <div class="form-text">Indica se o sistema deve evitar gerar parcelas nesta data. Você também pode alterar esta opção diretamente na listagem de feriados.</div>
                </div>
                
                <div class="mb-3">
                    <label for="local" class="form-label">Abrangência</label>
                    <select class="form-select" id="local" name="local">
                        <option value="nacional" <?php if ($feriado['local'] === 'nacional' || empty($feriado['local'])) echo 'selected'; ?>>Nacional</option>
                        <option value="estadual" <?php if ($feriado['local'] === 'estadual') echo 'selected'; ?>>Estadual</option>
                        <option value="municipal" <?php if ($feriado['local'] === 'municipal') echo 'selected'; ?>>Municipal</option>
                    </select>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="index.php?ano=<?php echo $feriado['ano']; ?>" class="btn btn-secondary">Voltar</a>
                    <div>
                        <a href="excluir.php?id=<?php echo $id; ?>" class="btn btn-danger me-2" onclick="return confirm('Tem certeza que deseja excluir este feriado?')">Excluir</a>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?> 