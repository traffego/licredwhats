<?php
require_once __DIR__ . '/../includes/conexao.php';

/**
 * Classe para gerenciar os templates de mensagens no banco de dados
 */
class TemplatesMensagensDB {
    private $conn;
    
    /**
     * Construtor - estabelece conexão com o banco de dados
     */
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * Obtém todos os templates ativos
     * @return array Templates ativos
     */
    public function getTemplatesAtivos() {
        $sql = "SELECT * FROM templates_mensagens WHERE ativo = 1 ORDER BY status, nome";
        $result = $this->conn->query($sql);
        
        $templates = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $templates[] = $row;
            }
        }
        
        return $templates;
    }
    
    /**
     * Obtém um template específico pelo ID
     * @param int $id ID do template
     * @return array|null Dados do template ou null se não encontrado
     */
    public function getTemplatePorId($id) {
        $id = (int)$id;
        $sql = "SELECT * FROM templates_mensagens WHERE id = ? AND ativo = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            return $row;
        }
        
        return false;
    }
    
    /**
     * Obtém templates por status
     * @param string $status Status do template
     * @return array Templates com o status especificado
     */
    public function getTemplatesPorStatus($status) {
        $stmt = $this->conn->prepare("SELECT * FROM templates_mensagens WHERE status = ? AND ativo = 1");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $templates = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $templates[] = $row;
            }
        }
        
        return $templates;
    }
    
    /**
     * Salva um novo template
     * @param array $dados Dados do template
     * @return int ID do template criado
     */
    public function salvarTemplate($dados) {
        $sql = "INSERT INTO templates_mensagens (
            nome, status, mensagem, 
            incluir_nome, incluir_valor, incluir_vencimento, incluir_atraso,
            incluir_valor_total, incluir_valor_em_aberto, incluir_total_parcelas,
            incluir_parcelas_pagas, incluir_valor_pago, incluir_numero_parcela,
            incluir_lista_parcelas, incluir_link_pagamento, usuario_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "sssiiiiiiiiiiiii",
            $dados['nome'],
            $dados['status'],
            $dados['mensagem'],
            $dados['incluir_nome'],
            $dados['incluir_valor'],
            $dados['incluir_vencimento'],
            $dados['incluir_atraso'],
            $dados['incluir_valor_total'],
            $dados['incluir_valor_em_aberto'],
            $dados['incluir_total_parcelas'],
            $dados['incluir_parcelas_pagas'],
            $dados['incluir_valor_pago'],
            $dados['incluir_numero_parcela'],
            $dados['incluir_lista_parcelas'],
            $dados['incluir_link_pagamento'],
            $dados['usuario_id']
        );
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        
        return false;
    }
    
    /**
     * Atualiza um template existente
     * @param int $id ID do template
     * @param array $dados Dados atualizados do template
     * @return bool Sucesso da operação
     */
    public function atualizarTemplate($id, $dados) {
        $sql = "UPDATE templates_mensagens SET 
            nome = ?,
            status = ?,
            mensagem = ?,
            incluir_nome = ?,
            incluir_valor = ?,
            incluir_vencimento = ?,
            incluir_atraso = ?,
            incluir_valor_total = ?,
            incluir_valor_em_aberto = ?,
            incluir_total_parcelas = ?,
            incluir_parcelas_pagas = ?,
            incluir_valor_pago = ?,
            incluir_numero_parcela = ?,
            incluir_lista_parcelas = ?,
            incluir_link_pagamento = ?
            WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "sssiiiiiiiiiiiiii",
            $dados['nome'],
            $dados['status'],
            $dados['mensagem'],
            $dados['incluir_nome'],
            $dados['incluir_valor'],
            $dados['incluir_vencimento'],
            $dados['incluir_atraso'],
            $dados['incluir_valor_total'],
            $dados['incluir_valor_em_aberto'],
            $dados['incluir_total_parcelas'],
            $dados['incluir_parcelas_pagas'],
            $dados['incluir_valor_pago'],
            $dados['incluir_numero_parcela'],
            $dados['incluir_lista_parcelas'],
            $dados['incluir_link_pagamento'],
            $id
        );
        
        return $stmt->execute();
    }
    
    /**
     * Exclui um template (soft delete)
     * @param int $id ID do template
     * @return bool Sucesso da operação
     */
    public function excluirTemplate($id) {
        $sql = "UPDATE templates_mensagens SET ativo = 0 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    /**
     * Processa um template substituindo os placeholders pelos valores reais
     * @param int $id ID do template
     * @param array $dados Dados para substituição
     * @return string Mensagem processada
     */
    public function processarTemplate($id, $dados) {
        $template = $this->getTemplatePorId($id);
        if (!$template) {
            return false;
        }
        
        $mensagem = $template['mensagem'];
        
        // Substitui os placeholders pelos valores reais
        foreach ($dados as $chave => $valor) {
            $mensagem = str_replace('{' . $chave . '}', $valor, $mensagem);
        }
        
        return $mensagem;
    }
} 