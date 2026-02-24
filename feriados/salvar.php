<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/queries_feriados.php';

// Verificar se é uma requisição POST
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
            // Preparar os dados para inserção
            $dados_feriado = [
                'nome' => $nome,
                'data' => $data,
                'tipo' => $tipo ?: 'fixo', // valor padrão se não for fornecido
                'evitar' => $evitar ?: 'sim_evitar', // valor padrão se não for fornecido
                'local' => $local ?: 'nacional', // valor padrão se não for fornecido
                'ano' => $ano
            ];
            
            // Verificar se já existe um feriado nesta data
            $feriado_existente = verificarSeDataEFeriado($conn, $data);
            
            if ($feriado_existente) {
                $mensagem = [
                    'tipo' => 'erro',
                    'texto' => 'Já existe um feriado cadastrado nesta data!'
                ];
            } else {
                // Inserir o feriado no banco
                $resultado = inserirFeriado($conn, $dados_feriado);
                
                if ($resultado) {
                    $mensagem = [
                        'tipo' => 'sucesso',
                        'texto' => 'Feriado cadastrado com sucesso!'
                    ];
                    
                    // Redirecionar para a página de listagem
                    header("Location: index.php?ano={$ano}&mensagem=sucesso");
                    exit;
                } else {
                    $mensagem = [
                        'tipo' => 'erro',
                        'texto' => 'Erro ao cadastrar o feriado: ' . $conn->error
                    ];
                }
            }
        }
    }
} else {
    // Se não for uma requisição POST, redirecionar para a página de listagem
    header("Location: index.php");
    exit;
} 