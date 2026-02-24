<?php
/**
 * Classe ZApi - Integração com Z-API para envio de mensagens WhatsApp
 * Documentação: https://developer.z-api.io/
 */
class ZApi {
    
    private $instanceId;
    private $token;
    private $clientToken;
    private $baseUrl = 'https://api.z-api.io/instances';
    
    /**
     * @param string $instanceId - ID da instância Z-API
     * @param string $token - Token da instância
     * @param string $clientToken - Token de segurança da conta (opcional)
     */
    public function __construct(string $instanceId, string $token, string $clientToken = '') {
        $this->instanceId = $instanceId;
        $this->token = $token;
        $this->clientToken = $clientToken;
    }
    
    /**
     * Envia mensagem de texto simples
     * @param string $phone - Número do telefone com DDI (ex: 5511999999999)
     * @param string $message - Texto da mensagem
     * @return object - Resposta da API
     */
    public function sendText(string $phone, string $message): object {
        return $this->request('/send-text', [
            'phone' => $phone,
            'message' => $message
        ]);
    }
    
    /**
     * Envia imagem com legenda opcional
     * @param string $phone - Número do telefone com DDI
     * @param string $imageUrl - URL da imagem
     * @param string $caption - Legenda (opcional)
     * @return object - Resposta da API
     */
    public function sendImage(string $phone, string $imageUrl, string $caption = ''): object {
        $body = [
            'phone' => $phone,
            'image' => $imageUrl
        ];
        if (!empty($caption)) {
            $body['caption'] = $caption;
        }
        return $this->request('/send-image', $body);
    }
    
    /**
     * Envia documento
     * @param string $phone - Número do telefone com DDI
     * @param string $documentUrl - URL do documento
     * @param string $extension - Extensão do arquivo (pdf, docx, etc)
     * @param string $caption - Legenda (opcional)
     * @return object - Resposta da API
     */
    public function sendDocument(string $phone, string $documentUrl, string $extension, string $caption = ''): object {
        $body = [
            'phone' => $phone,
            'document' => $documentUrl
        ];
        if (!empty($caption)) {
            $body['caption'] = $caption;
        }
        return $this->request('/send-document/' . $extension, $body);
    }
    
    /**
     * Envia mídia genérica (detecta tipo pela extensão)
     * @param string $phone - Número do telefone com DDI
     * @param string $fileUrl - URL do arquivo
     * @param string $caption - Legenda (opcional)
     * @return object - Resposta da API
     */
    public function sendMedia(string $phone, string $fileUrl, string $caption = ''): object {
        // Detectar extensão do arquivo
        $extension = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        // Extensões de imagem
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        
        if (in_array($extension, $imageExtensions)) {
            return $this->sendImage($phone, $fileUrl, $caption);
        } else {
            return $this->sendDocument($phone, $fileUrl, $extension, $caption);
        }
    }
    
    /**
     * Verifica se a resposta da API indica sucesso
     * @param object $response - Resposta da API
     * @return bool
     */
    public static function isSuccess(object $response): bool {
        return isset($response->zapiMessageId) || isset($response->messageId);
    }
    
    /**
     * Faz a requisição HTTP POST para a Z-API
     * @param string $endpoint - Caminho do endpoint (ex: /send-text)
     * @param array $body - Corpo da requisição
     * @return object - Resposta decodificada
     * @throws Exception em caso de erro
     */
    private function request(string $endpoint, array $body): object {
        $url = $this->baseUrl . '/' . $this->instanceId . '/token/' . $this->token . $endpoint;
        
        $headers = [
            'Content-Type: application/json'
        ];
        
        // Adicionar Client-Token se configurado
        if (!empty($this->clientToken)) {
            $headers[] = 'Client-Token: ' . $this->clientToken;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('Erro de conexão com Z-API: ' . $curlError);
        }
        
        $decoded = json_decode($response);
        
        if ($decoded === null) {
            throw new Exception('Resposta inválida da Z-API (HTTP ' . $httpCode . '): ' . $response);
        }
        
        // Adicionar httpCode ao objeto de resposta para facilitar verificações
        $decoded->httpCode = $httpCode;
        
        // Se retornou erro da API
        if ($httpCode >= 400) {
            $errorMsg = isset($decoded->message) ? $decoded->message : 'Erro HTTP ' . $httpCode;
            throw new Exception('Erro Z-API: ' . $errorMsg);
        }
        
        return $decoded;
    }
}
