-- Tabela para armazenar os templates de mensagens
CREATE TABLE IF NOT EXISTS `templates_mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL COMMENT 'Nome do template',
  `status` varchar(50) NOT NULL COMMENT 'Status do template (pendente, atrasado, hoje, personalizado)',
  `mensagem` text NOT NULL COMMENT 'Conteúdo da mensagem com placeholders',
  `incluir_nome` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir nome do cliente',
  `incluir_valor` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir valor da parcela',
  `incluir_vencimento` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir data de vencimento',
  `incluir_atraso` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir informação de atraso',
  `incluir_valor_total` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir valor total do empréstimo',
  `incluir_valor_em_aberto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir valor em aberto',
  `incluir_total_parcelas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir total de parcelas',
  `incluir_parcelas_pagas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir parcelas pagas',
  `incluir_valor_pago` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir valor pago',
  `incluir_numero_parcela` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir número da parcela',
  `incluir_lista_parcelas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir lista de parcelas restantes',
  `incluir_link_pagamento` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Incluir link de pagamento',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Template ativo ou inativo',
  `data_criacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'ID do usuário que criou o template',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir templates padrão
INSERT INTO `templates_mensagens` 
(`nome`, `status`, `mensagem`, `incluir_nome`, `incluir_valor`, `incluir_vencimento`, `incluir_atraso`, 
`incluir_valor_total`, `incluir_valor_em_aberto`, `incluir_total_parcelas`, `incluir_parcelas_pagas`, 
`incluir_valor_pago`, `incluir_numero_parcela`, `incluir_lista_parcelas`, `incluir_link_pagamento`) 
VALUES
('Template Padrão - Pendente', 'pendente', 
'*🟡 PARCELA PENDENTE*\n\n*#{nomedogestor}*\n\nOlá, {nome_cliente}.\n\nResumo do seu empréstimo conosco:\n\nValor total: R$ {valor_total}\nValor devido: R$ {valor_em_aberto}\nQuantidade de parcelas: {total_parcelas}\nQuantas parcelas pagas: {parcelas_pagas}\nTotal já pago: R$ {valor_pago}\n\nInformamos que a parcela {numero_parcela} de {total_parcelas}, no valor de R$ {valor_parcela}, vence em {data_vencimento}.\n\n📌 *Parcelas restantes:*\n{lista_parcelas_restantes}', 
1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0),

('Template Padrão - Atrasado', 'atrasado', 
'*🔴 PARCELA ATRASADA*\n\n*#{nomedogestor}*\n\nOlá, {nome_cliente}.\n\nResumo do seu empréstimo conosco:\n\nValor total: R$ {valor_total}\nValor devido: R$ {valor_em_aberto}\nQuantidade de parcelas: {total_parcelas}\nQuantas parcelas pagas: {parcelas_pagas}\nTotal já pago: R$ {valor_pago}\n\nInformamos que a parcela {numero_parcela} de {total_parcelas}, no valor de R$ {valor_parcela}, venceu em {data_vencimento} {atraso}.\n\n📌 *Parcelas restantes:*\n{lista_parcelas_restantes}', 
1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0),

('Template Padrão - Vence Hoje', 'hoje', 
'*🟠 PARCELA QUE VENCE HOJE*\n\n*#{nomedogestor}*\n\nOlá, {nome_cliente}.\n\nResumo do seu empréstimo conosco:\n\nValor total: R$ {valor_total}\nValor devido: R$ {valor_em_aberto}\nQuantidade de parcelas: {total_parcelas}\nQuantas parcelas pagas: {parcelas_pagas}\nTotal já pago: R$ {valor_pago}\n\nInformamos que a parcela {numero_parcela} de {total_parcelas}, no valor de R$ {valor_parcela}, vence *hoje* ({data_vencimento}).\nEvite juros e transtornos 😉\n\n📌 *Parcelas restantes:*\n{lista_parcelas_restantes}', 
1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0); 