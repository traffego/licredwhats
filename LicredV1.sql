-- --------------------------------------------------------
-- Servidor:                     187.33.241.40
-- Versão do servidor:           10.11.11-MariaDB-cll-lve - MariaDB Server
-- OS do Servidor:               Linux
-- HeidiSQL Versão:              12.10.0.7000
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Copiando estrutura para tabela platafo5_licred2.clientes
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `tipo_pessoa` tinyint(1) DEFAULT 1 COMMENT '1 = Física, 2 = Jurídica',
  `cpf_cnpj` varchar(20) DEFAULT NULL,
  `nascimento` date DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `endereco` varchar(150) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `chave_pix` varchar(150) DEFAULT NULL,
  `indicacao` varchar(150) DEFAULT NULL,
  `status` enum('Ativo','Inativo','Alerta','Atenção') DEFAULT 'Ativo',
  `nome_secundario` varchar(150) DEFAULT NULL,
  `telefone_secundario` varchar(20) DEFAULT NULL,
  `endereco_secundario` varchar(150) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `opt_in_whatsapp` tinyint(1) DEFAULT 1 COMMENT 'Consentimento para receber mensagens',
  `ultima_interacao_whatsapp` timestamp NULL DEFAULT NULL COMMENT 'Data da última interação por WhatsApp',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela platafo5_licred2.clientes: ~4 rows (aproximadamente)
REPLACE INTO `clientes` (`id`, `nome`, `cpf`, `telefone`, `email`, `tipo_pessoa`, `cpf_cnpj`, `nascimento`, `cep`, `endereco`, `bairro`, `cidade`, `estado`, `chave_pix`, `indicacao`, `status`, `nome_secundario`, `telefone_secundario`, `endereco_secundario`, `observacoes`, `opt_in_whatsapp`, `ultima_interacao_whatsapp`) VALUES
	(13, 'Milton Friedman', '', '(21) 98218-8560', 'milton@campos.com', 0, '123.321.123-32', '2001-05-10', '66666-666', 'R. Visc. de Piraje', '', 'Rio de Janeiro', 'RJ', 'financeiro2@pix.com', 'Amigão', 'Ativo', '', '', '', '', 1, NULL),
	(14, 'Beto Barbosa', '', '(21) 98161-2199', 'betobarbosa@licredo.com', 0, '999.988.888-88', '2001-05-10', '26311-490', 'R. Visc. de Pirajá - Ipanema', '', 'Rio de Janeiro', 'RJ', '777777777777777777777777777777', 'Amigao', 'Ativo', 'Geraldo', '(21) 55555-5555', 'Rua Chorumela', '', 1, NULL),
	(15, 'DIEGO REGO', '', '(55) 89999-7519', '', 0, '', NULL, '', '', '', '', 'PI', '', '', 'Ativo', '', '', '', '', 1, NULL),
	(16, 'JOSE LUCAS MENDES SILVA', '', '(89) 99942-3648', '', 0, '', NULL, '', '', '', '', '', '', '', 'Ativo', '', '', '', '', 1, NULL);

-- Copiando estrutura para tabela platafo5_licred2.cobrancas
CREATE TABLE IF NOT EXISTS `cobrancas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `destinatario` varchar(20) NOT NULL COMMENT 'Número de telefone do destinatário',
  `destinatario_nome` varchar(100) DEFAULT NULL COMMENT 'Nome do destinatário',
  `conteudo` text NOT NULL COMMENT 'Conteúdo da mensagem',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor da cobrança',
  `link_pagamento` varchar(500) NOT NULL COMMENT 'Link para pagamento',
  `descricao` varchar(255) DEFAULT NULL COMMENT 'Descrição da cobrança',
  `status` enum('pendente','enviado','erro') NOT NULL DEFAULT 'pendente',
  `id_mensagem` varchar(255) DEFAULT NULL COMMENT 'ID da mensagem na API',
  `erro` text DEFAULT NULL COMMENT 'Mensagem de erro, se houver',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_envio` timestamp NULL DEFAULT NULL,
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Usuário que enviou a cobrança',
  PRIMARY KEY (`id`),
  KEY `destinatario` (`destinatario`),
  KEY `status` (`status`),
  KEY `usuario_id` (`usuario_id`),
  KEY `data_criacao` (`data_criacao`),
  KEY `data_envio` (`data_envio`),
  CONSTRAINT `cobrancas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.cobrancas: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela platafo5_licred2.configuracoes
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_empresa` varchar(255) NOT NULL DEFAULT 'Minha Empresa',
  `email_sistema` varchar(255) NOT NULL DEFAULT 'contato@minhaempresa.com',
  `telefone_sistema` varchar(20) NOT NULL DEFAULT '(00) 00000-0000',
  `cpf_cnpj` varchar(20) NOT NULL DEFAULT '',
  `endereco` text NOT NULL DEFAULT '',
  `efi_client_id` varchar(255) DEFAULT NULL,
  `efi_client_secret` varchar(255) DEFAULT NULL,
  `efi_chave_aleatoria` varchar(255) DEFAULT NULL,
  `efi_certificado` text DEFAULT NULL,
  `mercadopago_public_key` varchar(255) DEFAULT NULL,
  `mercadopago_access_token` varchar(255) DEFAULT NULL,
  `menuia_endpoint` varchar(255) DEFAULT 'https://chatbot.menuia.com',
  `menuia_app_key` varchar(255) DEFAULT NULL,
  `menuia_auth_key` varchar(255) DEFAULT NULL,
  `chave_pix` varchar(255) DEFAULT NULL,
  `saldo_inicial` decimal(15,2) DEFAULT 0.00,
  `logo` varchar(255) DEFAULT NULL,
  `icone` varchar(255) DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.configuracoes: ~0 rows (aproximadamente)
REPLACE INTO `configuracoes` (`id`, `nome_empresa`, `email_sistema`, `telefone_sistema`, `cpf_cnpj`, `endereco`, `efi_client_id`, `efi_client_secret`, `efi_chave_aleatoria`, `efi_certificado`, `mercadopago_public_key`, `mercadopago_access_token`, `menuia_endpoint`, `menuia_app_key`, `menuia_auth_key`, `chave_pix`, `saldo_inicial`, `logo`, `icone`, `data_criacao`, `data_atualizacao`) VALUES
	(1, 'Licred', 'contato@licred.com', '(98) 88888-8888', '123.123.123-12', 'Rua Exemplo, 123 - Centro - Cidade', '', '', '', '', '', '', 'https://chatbot.menuia.com', 'ee7f0152-018d-40fd-a0c8-b36d4e6dd383', 'bvQ53v6sdSsPzdzSqmBeSR7qsqwPMsmn58Er5Slera332jSWA0', '', 0.00, 'logo_1745559171.png', NULL, '2025-04-22 17:26:33', '2025-04-25 02:55:43');

-- Copiando estrutura para tabela platafo5_licred2.emprestimos
CREATE TABLE IF NOT EXISTS `emprestimos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `tipo_de_cobranca` enum('parcelada_comum','reparcelada_com_juros') NOT NULL,
  `valor_emprestado` decimal(10,2) NOT NULL,
  `parcelas` int(11) NOT NULL,
  `valor_parcela` decimal(10,2) DEFAULT NULL,
  `juros_percentual` decimal(5,2) DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `configuracao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Configurações do empréstimo em formato JSON' CHECK (json_valid(`configuracao`)),
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'ativo',
  PRIMARY KEY (`id`),
  KEY `idx_emprestimos_cliente_id` (`cliente_id`),
  KEY `idx_emprestimos_data_inicio` (`data_inicio`),
  CONSTRAINT `emprestimos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela platafo5_licred2.emprestimos: ~6 rows (aproximadamente)
REPLACE INTO `emprestimos` (`id`, `cliente_id`, `tipo_de_cobranca`, `valor_emprestado`, `parcelas`, `valor_parcela`, `juros_percentual`, `data_inicio`, `configuracao`, `data_criacao`, `data_atualizacao`, `status`) VALUES
	(18, 14, 'parcelada_comum', 1000.00, 30, 44.00, 32.00, '2025-04-21', '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":44}', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 'ativo'),
	(19, 14, 'parcelada_comum', 2000.00, 30, 87.00, 30.50, '2025-04-22', '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":87}', '2025-04-21 17:11:52', '2025-04-21 17:11:52', 'ativo'),
	(20, 13, 'parcelada_comum', 1000.00, 10, 110.00, 10.00, '2025-04-22', '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":110}', '2025-04-21 18:33:21', '2025-04-21 18:33:21', 'ativo'),
	(21, 13, 'parcelada_comum', 1000.00, 30, 43.00, 29.99, '2025-04-24', '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":43.33}', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 'ativo'),
	(23, 15, 'parcelada_comum', 1000.00, 30, 44.00, 32.00, '2025-04-21', '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["0","feriados"],"considerar_feriados":true,"valor_parcela_padrao":44}', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 'ativo'),
	(24, 16, 'parcelada_comum', 1500.00, 30, 65.00, 30.00, '2025-04-16', '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["0","feriados"],"considerar_feriados":true,"valor_parcela_padrao":65}', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 'ativo'),
	(25, 15, 'parcelada_comum', 1000.00, 30, 44.00, 32.00, '2025-04-14', '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["2","feriados"],"considerar_feriados":true,"valor_parcela_padrao":44}', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 'ativo');

-- Copiando estrutura para tabela platafo5_licred2.feriados
CREATE TABLE IF NOT EXISTS `feriados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `data` date NOT NULL,
  `tipo` enum('fixo','movel') DEFAULT NULL,
  `evitar` enum('sim_evitar','nao_evitar') DEFAULT 'sim_evitar',
  `local` enum('nacional','estadual','municipal') DEFAULT 'nacional',
  `ano` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data` (`data`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.feriados: ~15 rows (aproximadamente)
REPLACE INTO `feriados` (`id`, `nome`, `data`, `tipo`, `evitar`, `local`, `ano`) VALUES
	(1, 'Confraternização Universal', '2025-01-01', 'fixo', 'sim_evitar', 'nacional', 2025),
	(2, 'Carnaval (Segunda-feira)', '2025-03-03', 'movel', 'sim_evitar', 'nacional', 2025),
	(3, 'Carnaval (Terça-feira)', '2025-03-04', 'movel', 'sim_evitar', 'nacional', 2025),
	(4, 'Quarta-feira de Cinzas (até 12h)', '2025-03-05', 'movel', 'sim_evitar', 'nacional', 2025),
	(5, 'Sexta-feira Santa (Paixão de Cristo)', '2025-04-18', 'movel', 'sim_evitar', 'nacional', 2025),
	(6, 'Tiradentes', '2025-04-21', 'fixo', 'sim_evitar', 'nacional', 2025),
	(7, 'Dia do Trabalho', '2025-05-01', 'fixo', 'sim_evitar', 'nacional', 2025),
	(8, 'Corpus Christi', '2025-06-19', 'movel', 'sim_evitar', 'nacional', 2025),
	(9, 'Independência do Brasil', '2025-09-07', 'fixo', 'sim_evitar', 'nacional', 2025),
	(10, 'Nossa Senhora Aparecida', '2025-10-12', 'fixo', 'sim_evitar', 'nacional', 2025),
	(11, 'Finados', '2025-11-02', 'fixo', 'sim_evitar', 'nacional', 2025),
	(12, 'Proclamação da República', '2025-11-15', 'fixo', 'sim_evitar', 'nacional', 2025),
	(13, 'Natal', '2025-12-25', 'fixo', 'sim_evitar', 'nacional', 2025),
	(14, 'Véspera de Natal', '2025-12-24', 'fixo', 'sim_evitar', 'nacional', 2025),
	(15, 'Véspera de Ano Novo', '2025-12-31', 'fixo', 'sim_evitar', 'nacional', 2025);

-- Copiando estrutura para tabela platafo5_licred2.historico
CREATE TABLE IF NOT EXISTS `historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emprestimo_id` int(11) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `emprestimo_id` (`emprestimo_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.historico: ~0 rows (aproximadamente)
REPLACE INTO `historico` (`id`, `emprestimo_id`, `tipo`, `descricao`, `valor`, `data`, `usuario_id`, `created_at`) VALUES
	(1, 16, 'quitacao', 'Quitação do empréstimo', 968.00, '2025-04-20', 1, '2025-04-21 00:59:10');

-- Copiando estrutura para tabela platafo5_licred2.historico_mensagens
CREATE TABLE IF NOT EXISTS `historico_mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `destinatario` varchar(20) NOT NULL COMMENT 'Número de telefone do destinatário',
  `destinatario_nome` varchar(100) DEFAULT NULL COMMENT 'Nome do destinatário',
  `conteudo` text NOT NULL COMMENT 'Conteúdo da mensagem',
  `status` enum('enviando','enviado','falha','entregue','lida') NOT NULL DEFAULT 'enviando',
  `id_mensagem` varchar(255) DEFAULT NULL COMMENT 'ID da mensagem na API',
  `erro` text DEFAULT NULL COMMENT 'Mensagem de erro, se houver',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_envio` timestamp NULL DEFAULT NULL,
  `data_entrega` timestamp NULL DEFAULT NULL,
  `data_leitura` timestamp NULL DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Usuário que enviou a mensagem',
  PRIMARY KEY (`id`),
  KEY `destinatario` (`destinatario`),
  KEY `status` (`status`),
  KEY `usuario_id` (`usuario_id`),
  KEY `data_criacao` (`data_criacao`),
  KEY `data_envio` (`data_envio`),
  CONSTRAINT `historico_mensagens_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.historico_mensagens: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela platafo5_licred2.mensagens_log
CREATE TABLE IF NOT EXISTS `mensagens_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emprestimo_id` int(11) DEFAULT NULL,
  `parcela_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `telefone` varchar(20) NOT NULL,
  `mensagem` text NOT NULL,
  `data_envio` datetime NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `status` enum('sucesso','erro') NOT NULL DEFAULT 'sucesso',
  `erro` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `emprestimo_id` (`emprestimo_id`),
  KEY `parcela_id` (`parcela_id`),
  KEY `template_id` (`template_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `data_envio` (`data_envio`),
  KEY `status` (`status`),
  KEY `cliente_id` (`cliente_id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.mensagens_log: ~57 rows (aproximadamente)
REPLACE INTO `mensagens_log` (`id`, `emprestimo_id`, `parcela_id`, `template_id`, `cliente_id`, `telefone`, `mensagem`, `data_envio`, `usuario_id`, `status`, `erro`) VALUES
	(1, 20, 91, 1, NULL, '5521967380813', 'Olá Milton Friedman,\r\n\r\nGostaríamos de lembrar que sua parcela de R$ 110,00 vence no dia 22/04/2025.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 1.100,00\r\nTotal de parcelas: 10\r\nParcelas pagas: 0\r\nValor já pago: R$ 0,00\r\n\r\nPara facilitar seu pagamento, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=91\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-21 20:56:34', 1, 'sucesso', NULL),
	(2, 20, 91, 9, NULL, '5521967380813', 'Olá Milton Friedman,\r\n\r\nSua parcela de R$ 110,00 que vencia em 22/04/2025 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 1.100,00\r\nTotal de parcelas: 10\r\nParcelas pagas: 0\r\nValor já pago: R$ 0,00\r\n\r\nPara regularizar sua situação, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=91\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-21 21:28:09', 1, 'sucesso', NULL),
	(3, 20, 91, 6, NULL, '5521967380813', 'Olá, Milton Friedman! Seja bem-vindo(a) ao sistema de empréstimos. Estamos à disposição para ajudá-lo(a). Atenciosamente, Gestor.', '2025-04-21 21:30:52', 1, 'sucesso', NULL),
	(4, 20, 91, 1, NULL, '5521967380813', 'Olá Milton Friedman,\r\n\r\nGostaríamos de lembrar que sua parcela de R$ 110,00 vence no dia 22/04/2025.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 1.100,00\r\nTotal de parcelas: 10\r\nParcelas pagas: 0\r\nValor já pago: R$ 0,00\r\n\r\nPara facilitar seu pagamento, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=91\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 11:48:44', 1, 'sucesso', NULL),
	(5, 20, 91, 11, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nA parcela no valor de R$ 110,00 vence hoje (22/04/2025).\r\n\r\nPara pagar, envie o valor para o pix: \r\n\r\n123123123123 \r\n\r\nOu pague no link de pagamento http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=91\r\n\r\nAtenciosamente,\r\nLicred', '2025-04-22 13:56:11', 1, 'sucesso', NULL),
	(6, 18, 34, 11, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nA parcela no valor de R$ 44,00 vence hoje (24/04/2025).\r\n\r\nPara pagar, envie o valor para o pix: \r\n\r\n123123123123 \r\n\r\nOu pague no link de pagamento http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=34\r\n\r\nAtenciosamente,\r\nLicred', '2025-04-22 14:28:20', 1, 'sucesso', NULL),
	(7, 20, 91, 1, NULL, '5521967380813', 'Olá Milton Friedman,\r\n\r\nGostaríamos de lembrar que sua parcela de R$ 110,00 vence no dia 22/04/2025.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 1.100,00\r\nTotal de parcelas: 10\r\nParcelas pagas: 0\r\nValor já pago: R$ 0,00\r\n\r\nPara facilitar seu pagamento, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=91\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 15:56:39', 1, 'sucesso', NULL),
	(8, 19, 61, 1, NULL, '5521971431212', 'Olá Beto Barbosa,\r\n\r\nGostaríamos de lembrar que sua parcela de R$ 87,00 vence no dia 22/04/2025.\r\n\r\nValor total do empréstimo: R$ 2.610,00\r\nValor em aberto: R$ 2.610,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 0\r\nValor já pago: R$ 0,00\r\n\r\nPara facilitar seu pagamento, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=61\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 15:56:41', 1, 'sucesso', NULL),
	(9, 20, 91, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nA parcela no valor de R$ 110,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 1.100,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=91\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 16:08:59', 1, 'sucesso', NULL),
	(10, 19, 61, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nA parcela no valor de R$ 87,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 2.610,00\r\nValor em aberto: R$ 2.610,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=61\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 16:09:02', 1, 'sucesso', NULL),
	(11, 20, 91, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nA parcela no valor de R$ 110,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 1.100,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=91\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 16:30:26', 1, 'sucesso', NULL),
	(12, 19, 61, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nA parcela no valor de R$ 87,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 2.610,00\r\nValor em aberto: R$ 2.610,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=61\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 16:30:28', 1, 'sucesso', NULL),
	(13, 20, 91, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nA parcela no valor de R$ 110,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 1.100,00\r\n\r\nPara regularizar, acesse: http://192.168.1.69/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=91\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 18:30:14', 1, 'sucesso', NULL),
	(14, 19, 61, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nA parcela no valor de R$ 87,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 2.610,00\r\nValor em aberto: R$ 2.610,00\r\n\r\nPara regularizar, acesse: http://192.168.1.69/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=61\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 18:30:16', 1, 'sucesso', NULL),
	(15, 19, 61, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nA parcela no valor de R$ 87,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 2.610,00\r\nValor em aberto: R$ 2.610,00\r\n\r\nPara regularizar, acesse: http://192.168.1.69/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=61\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 21:18:37', 1, 'sucesso', NULL),
	(16, 19, 61, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nA parcela no valor de R$ 87,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 2.610,00\r\nValor em aberto: R$ 2.610,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=61\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 21:36:41', 1, 'sucesso', NULL),
	(17, 20, 91, 3, NULL, '5521967380813', 'Olá Milton Friedman,\r\n\r\nRecebemos seu pagamento e confirmamos a quitação do empréstimo.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nTotal de parcelas: 10\r\nValor pago: R$ 110,00\r\n\r\nAgradecemos a preferência!\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 21:38:30', 1, 'sucesso', NULL),
	(18, 20, 91, 13, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nRecebemos seu pagamento de R$ 110,00 referente à parcela 1.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor restante: R$ 990,00\r\n\r\nAgradecemos a pontualidade!\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-22 21:59:10', 1, 'sucesso', NULL),
	(19, 20, 91, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*    \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 110,00\r\nParcela: 1 de 10', '2025-04-22 23:11:42', 1, 'sucesso', NULL),
	(20, 20, 92, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nA parcela no valor de R$ 110,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 990,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=92\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-23 00:13:02', 1, 'erro', 'Erro desconhecido'),
	(21, 20, 92, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nA parcela no valor de R$ 110,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 990,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=92\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-23 00:13:27', 1, 'erro', 'Erro desconhecido'),
	(22, 19, 61, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nA parcela no valor de R$ 87,00 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 2.610,00\r\nValor em aberto: R$ 2.610,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=61\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-23 00:13:28', 1, 'erro', 'Erro desconhecido'),
	(23, 20, 92, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nA parcela no valor de R$ 110,00 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.100,00\r\nValor em aberto: R$ 990,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=92\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-23 00:21:36', 1, 'sucesso', NULL),
	(24, 19, 61, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nA parcela no valor de R$ 87,00 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 2.610,00\r\nValor em aberto: R$ 2.610,00\r\n\r\nPara regularizar, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=61\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-23 00:21:37', 1, 'sucesso', NULL),
	(25, 18, 31, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nPagamento parcial de R$ 132,00 foi pago.', '2025-04-23 00:44:45', 1, 'sucesso', NULL),
	(26, 20, 91, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nPagamento parcial de R$ 660,00 foi pago.', '2025-04-23 00:44:48', 1, 'sucesso', NULL),
	(27, 20, 91, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nPagamento parcial de R$ 770,00 foi pago.', '2025-04-23 00:45:49', 1, 'sucesso', NULL),
	(28, 18, 31, 12, NULL, '5521971431212', 'Olá, Beto Barbosa!\r\n\r\nPagamento parcial de R$ 132,00 foi pago.', '2025-04-23 00:45:51', 1, 'sucesso', NULL),
	(29, 20, 98, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nPagamento parcial de R$ 880,00 foi pago.', '2025-04-23 00:48:09', 1, 'sucesso', NULL),
	(30, 20, 99, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nPagamento parcial de R$ 990,00 foi pago.', '2025-04-23 00:48:31', 1, 'sucesso', NULL),
	(31, 20, 100, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 110,00\r\nParcela: 10 de 10', '2025-04-23 00:50:08', 1, 'sucesso', NULL),
	(32, 19, 61, 13, NULL, '5521971431212', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Beto Barbosa\r\nContrato: 12345\r\nValor: R$ 87,00\r\nParcela: 1 de 30', '2025-04-23 00:51:14', 1, 'sucesso', NULL),
	(33, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 1 de 30', '2025-04-23 01:12:17', 1, 'erro', 'Erro desconhecido'),
	(34, 21, 102, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nPagamento parcial de R$ 86,66 foi pago.', '2025-04-23 01:12:33', 1, 'sucesso', NULL),
	(35, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 2 de 30', '2025-04-23 01:14:00', 1, 'erro', 'Erro desconhecido'),
	(36, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 2 de 30', '2025-04-23 01:15:58', 1, 'erro', 'Erro desconhecido'),
	(37, 21, 103, 15, NULL, '5521967380813', 'Olá Milton Friedman,\r\n\r\nSua parcela de R$ 43,33 que vencia em 26/04/2025 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.299,90\r\nValor em aberto: R$ 1.213,24\r\nTotal de parcelas: 30\r\nParcelas pagas: 2\r\nValor já pago: R$ 86,66\r\n\r\nPara regularizar sua situação, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=103\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-23 01:16:10', 1, 'erro', 'Erro desconhecido'),
	(38, 21, 103, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 3 de 30', '2025-04-23 01:16:28', 1, 'sucesso', NULL),
	(39, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 3 de 30', '2025-04-23 01:20:31', 1, 'erro', 'Erro desconhecido'),
	(40, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 3 de 30', '2025-04-23 01:20:49', 1, 'erro', 'Erro desconhecido'),
	(41, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 3 de 30', '2025-04-23 01:21:24', 1, 'erro', 'Erro desconhecido'),
	(42, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 3 de 30', '2025-04-23 01:25:56', 1, 'erro', 'Erro desconhecido'),
	(43, 21, 104, 15, NULL, '5521967380813', 'Olá Milton Friedman,\r\n\r\nSua parcela de R$ 43,33 que vencia em 27/04/2025 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.299,90\r\nValor em aberto: R$ 1.169,91\r\nTotal de parcelas: 30\r\nParcelas pagas: 3\r\nValor já pago: R$ 129,99\r\n\r\nPara regularizar sua situação, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=104\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-23 01:29:26', 1, 'sucesso', NULL),
	(44, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 3 de 30', '2025-04-23 01:30:05', 1, 'sucesso', NULL),
	(45, 21, 104, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 4 de 30', '2025-04-23 01:31:19', 1, 'sucesso', NULL),
	(46, 21, 105, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 5 de 30', '2025-04-23 01:31:39', 1, 'sucesso', NULL),
	(47, 21, 106, 12, NULL, '5521967380813', 'Olá, Milton Friedman!\r\n\r\nPagamento parcial de R$ 259,98 foi pago.', '2025-04-23 01:32:03', 1, 'sucesso', NULL),
	(48, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 7 de 30', '2025-04-23 01:45:08', 1, 'sucesso', NULL),
	(49, 21, 107, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 8 de 30', '2025-04-23 01:45:31', 1, 'sucesso', NULL),
	(50, 21, 112, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 12 de 30', '2025-04-23 01:56:32', 1, 'sucesso', NULL),
	(51, 21, 101, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 12 de 30', '2025-04-23 01:57:15', 1, 'sucesso', NULL),
	(52, 19, 64, 13, NULL, '5521993652605', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Beto Barbosa\r\nContrato: 12345\r\nValor: R$ 87,00\r\nParcela: 4 de 30', '2025-04-23 02:31:47', 1, 'sucesso', NULL),
	(53, 21, 113, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 13 de 30', '2025-04-24 10:40:14', 1, 'sucesso', NULL),
	(54, 21, 114, 13, NULL, '5521967380813', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 14 de 30', '2025-04-24 16:44:36', 1, 'sucesso', NULL),
	(55, 18, 34, 14, NULL, '5521981612199', 'Olá Beto Barbosa,\r\n\r\nSua parcela de R$ 44,00 que vencia em 24/04/2025 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 1.320,00\r\nValor em aberto: R$ 1.188,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 3\r\nValor já pago: R$ 132,00\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=34\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 02:39:55', 1, 'erro', 'Erro desconhecido'),
	(56, 21, 115, 13, NULL, '5521982188560', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 15 de 30', '2025-04-25 02:40:32', 1, 'erro', 'Erro desconhecido'),
	(57, 21, 116, 15, NULL, '5521982188560', 'Olá Milton Friedman,\r\n\r\nSua parcela de R$ 43,33 que vencia em 10/05/2025 está atrasada há 0 dias.\r\n\r\nValor total do empréstimo: R$ 1.299,90\r\nValor em aberto: R$ 649,95\r\nTotal de parcelas: 30\r\nParcelas pagas: 15\r\nValor já pago: R$ 649,95\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=116\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 02:41:12', 1, 'erro', 'Erro desconhecido'),
	(58, 18, 34, 14, NULL, '5521981612199', 'Olá Beto Barbosa,\r\n\r\nSua parcela de R$ 44,00 que vencia em 24/04/2025 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 1.320,00\r\nValor em aberto: R$ 1.188,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 3\r\nValor já pago: R$ 132,00\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=34\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 02:49:25', 1, 'erro', 'Erro desconhecido'),
	(59, 18, 34, 14, NULL, '5521981612199', 'Olá Beto Barbosa,\r\n\r\nSua parcela de R$ 44,00 que vencia em 24/04/2025 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 1.320,00\r\nValor em aberto: R$ 1.188,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 3\r\nValor já pago: R$ 132,00\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=34\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 02:51:19', 1, 'erro', 'Erro desconhecido'),
	(60, 21, 116, 13, NULL, '5521982188560', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 16 de 30', '2025-04-25 02:53:23', 1, 'erro', 'Erro desconhecido'),
	(61, 18, 34, 14, NULL, '5521981612199', 'Olá Beto Barbosa,\r\n\r\nSua parcela de R$ 44,00 que vencia em 24/04/2025 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 1.320,00\r\nValor em aberto: R$ 1.188,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 3\r\nValor já pago: R$ 132,00\r\n\r\nPara regularizar sua situação, acesse: http://localhost/licred2/sistema_emprestimos_v1.8/sistema_emprestimos_v1/pagamento/link.php?p=34\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 02:53:52', 1, 'erro', 'Erro desconhecido'),
	(62, 18, 34, 14, NULL, '5521981612199', 'Olá Beto Barbosa,\r\n\r\nSua parcela de R$ 44,00 que vencia em 24/04/2025 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 1.320,00\r\nValor em aberto: R$ 1.188,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 3\r\nValor já pago: R$ 132,00\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=34\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 02:56:19', 1, 'sucesso', NULL),
	(63, 21, 101, 13, NULL, '5521982188560', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: Milton Friedman\r\nContrato: 12345\r\nValor: R$ 43,33\r\nParcela: 16 de 30', '2025-04-25 07:49:30', 1, 'sucesso', NULL),
	(64, 18, 34, 14, NULL, '5521981612199', 'Olá Beto Barbosa,\r\n\r\nSua parcela de R$ 44,00 que vencia em 24/04/2025 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 1.320,00\r\nValor em aberto: R$ 1.188,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 3\r\nValor já pago: R$ 132,00\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=34\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 07:54:52', 1, 'sucesso', NULL),
	(65, 18, 34, 14, NULL, '5521981612199', 'Olá Beto Barbosa,\r\n\r\nSua parcela de R$ 44,00 que vencia em 24/04/2025 está atrasada há 1 dias.\r\n\r\nValor total do empréstimo: R$ 1.320,00\r\nValor em aberto: R$ 1.188,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 3\r\nValor já pago: R$ 132,00\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=34\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 08:17:52', 1, 'sucesso', NULL),
	(66, 23, 161, 14, NULL, '5555899997519', 'Olá DIEGO REGO,\r\n\r\nSua parcela de R$ 44,00 que vencia em 21/04/2025 está atrasada há 4 dias.\r\n\r\nValor total do empréstimo: R$ 1.320,00\r\nValor em aberto: R$ 1.320,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 0\r\nValor já pago: R$ 0,00\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=161\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 08:17:53', 1, 'erro', 'Erro desconhecido'),
	(67, 24, 191, 14, NULL, '5589999423648', 'Olá JOSE LUCAS MENDES SILVA,\r\n\r\nSua parcela de R$ 65,00 que vencia em 16/04/2025 está atrasada há 9 dias.\r\n\r\nValor total do empréstimo: R$ 1.950,00\r\nValor em aberto: R$ 1.950,00\r\nTotal de parcelas: 30\r\nParcelas pagas: 0\r\nValor já pago: R$ 0,00\r\n\r\nPara regularizar sua situação, acesse: https://cred.traffego.agency/pagamento/link.php?p=191\r\n\r\nAtenciosamente,\r\nGestor', '2025-04-25 08:17:55', 1, 'sucesso', NULL);

-- Copiando estrutura para tabela platafo5_licred2.parcelas
CREATE TABLE IF NOT EXISTS `parcelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emprestimo_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `vencimento` date NOT NULL,
  `status` enum('pendente','parcial','pago','atrasado') DEFAULT 'pendente',
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `diferenca_transacao` decimal(10,2) DEFAULT 0.00,
  `acao_diferenca` varchar(50) DEFAULT NULL,
  `valor_original` decimal(10,2) DEFAULT NULL,
  `ultima_cobranca` timestamp NULL DEFAULT NULL COMMENT 'Data da última cobrança enviada',
  `total_cobrancas` int(11) DEFAULT 0 COMMENT 'Total de cobranças enviadas',
  PRIMARY KEY (`id`),
  KEY `idx_emprestimo_numero` (`emprestimo_id`,`numero`),
  KEY `idx_status` (`status`),
  KEY `idx_vencimento` (`vencimento`),
  CONSTRAINT `parcelas_ibfk_1` FOREIGN KEY (`emprestimo_id`) REFERENCES `emprestimos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.parcelas: ~190 rows (aproximadamente)
REPLACE INTO `parcelas` (`id`, `emprestimo_id`, `numero`, `valor`, `vencimento`, `status`, `valor_pago`, `data_pagamento`, `forma_pagamento`, `observacao`, `created_at`, `updated_at`, `diferenca_transacao`, `acao_diferenca`, `valor_original`, `ultima_cobranca`, `total_cobrancas`) VALUES
	(31, 18, 1, 44.00, '2025-04-21', 'pago', 44.00, '2025-04-21', 'dinheiro', 'valor_original: 44.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 13:32:59', '2025-04-21 13:38:10', 0.00, NULL, NULL, NULL, 0),
	(32, 18, 2, 44.00, '2025-04-22', 'pago', 44.00, '2025-04-21', 'dinheiro', 'valor_original: 44.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 13:32:59', '2025-04-21 13:38:21', 0.00, NULL, NULL, NULL, 0),
	(33, 18, 3, 44.00, '2025-04-23', 'pago', 44.00, '2025-04-21', 'dinheiro', 'valor_original: 44.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 13:32:59', '2025-04-21 13:38:31', 0.00, NULL, NULL, NULL, 0),
	(34, 18, 4, 44.00, '2025-04-24', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-25 04:35:23', 0.00, NULL, NULL, NULL, 0),
	(35, 18, 5, 44.00, '2025-04-25', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-25 04:38:35', 0.00, NULL, NULL, NULL, 0),
	(36, 18, 6, 44.00, '2025-04-26', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(37, 18, 7, 44.00, '2025-04-27', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(38, 18, 8, 44.00, '2025-04-28', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(39, 18, 9, 44.00, '2025-04-29', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(40, 18, 10, 44.00, '2025-04-30', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(41, 18, 11, 44.00, '2025-05-01', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(42, 18, 12, 44.00, '2025-05-02', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(43, 18, 13, 44.00, '2025-05-03', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(44, 18, 14, 44.00, '2025-05-04', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(45, 18, 15, 44.00, '2025-05-05', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(46, 18, 16, 44.00, '2025-05-06', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(47, 18, 17, 44.00, '2025-05-07', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(48, 18, 18, 44.00, '2025-05-08', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(49, 18, 19, 44.00, '2025-05-09', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(50, 18, 20, 44.00, '2025-05-10', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(51, 18, 21, 44.00, '2025-05-11', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(52, 18, 22, 44.00, '2025-05-12', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(53, 18, 23, 44.00, '2025-05-13', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(54, 18, 24, 44.00, '2025-05-14', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(55, 18, 25, 44.00, '2025-05-15', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(56, 18, 26, 44.00, '2025-05-16', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(57, 18, 27, 44.00, '2025-05-17', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(58, 18, 28, 44.00, '2025-05-18', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(59, 18, 29, 44.00, '2025-05-19', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(60, 18, 30, 44.00, '2025-05-20', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 0.00, NULL, NULL, NULL, 0),
	(61, 19, 1, 87.00, '2025-04-22', 'pago', 87.00, '2025-04-23', 'dinheiro', 'valor_original: 87.00, diferenca_transacao: 0 | diferenca_transacao: 13, acao_diferenca: desconto_proximas', '2025-04-21 17:11:53', '2025-04-23 03:51:12', 0.00, NULL, NULL, NULL, 0),
	(62, 19, 2, 87.00, '2025-04-23', 'pago', 87.00, '2025-04-23', 'dinheiro', 'valor_original: 87.00, diferenca_transacao: 0 | diferenca_transacao: 13, acao_diferenca: desconto_proximas | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 17:11:53', '2025-04-23 04:02:21', 0.00, NULL, NULL, NULL, 0),
	(63, 19, 3, 87.00, '2025-04-24', 'pago', 87.00, '2025-04-23', 'pix', 'valor_original: 87.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 17:11:53', '2025-04-23 04:08:10', 0.00, NULL, NULL, NULL, 0),
	(64, 19, 4, 87.00, '2025-04-25', 'pago', 87.00, '2025-04-23', 'dinheiro', 'valor_original: 87.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 17:11:53', '2025-04-23 05:31:45', 0.00, NULL, NULL, NULL, 0),
	(65, 19, 5, 87.00, '2025-04-26', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(66, 19, 6, 87.00, '2025-04-27', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(67, 19, 7, 87.00, '2025-04-28', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(68, 19, 8, 87.00, '2025-04-29', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(69, 19, 9, 87.00, '2025-04-30', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(70, 19, 10, 87.00, '2025-05-02', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(71, 19, 11, 87.00, '2025-05-03', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(72, 19, 12, 87.00, '2025-05-04', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(73, 19, 13, 87.00, '2025-05-05', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(74, 19, 14, 87.00, '2025-05-06', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(75, 19, 15, 87.00, '2025-05-07', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(76, 19, 16, 87.00, '2025-05-08', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(77, 19, 17, 87.00, '2025-05-09', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(78, 19, 18, 87.00, '2025-05-10', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(79, 19, 19, 87.00, '2025-05-11', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(80, 19, 20, 87.00, '2025-05-12', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(81, 19, 21, 87.00, '2025-05-13', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(82, 19, 22, 87.00, '2025-05-14', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(83, 19, 23, 87.00, '2025-05-15', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(84, 19, 24, 87.00, '2025-05-16', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(85, 19, 25, 87.00, '2025-05-17', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(86, 19, 26, 87.00, '2025-05-18', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(87, 19, 27, 87.00, '2025-05-19', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(88, 19, 28, 87.00, '2025-05-20', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(89, 19, 29, 87.00, '2025-05-21', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(90, 19, 30, 87.00, '2025-05-22', 'pendente', NULL, NULL, NULL, 'valor_original: 87.00, diferenca_transacao: 0', '2025-04-21 17:11:53', '2025-04-21 17:11:53', 0.00, NULL, NULL, NULL, 0),
	(91, 20, 1, 110.00, '2025-04-22', 'pago', 110.00, '2025-04-22', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-22 22:45:51', 0.00, NULL, NULL, NULL, 0),
	(92, 20, 2, 110.00, '2025-04-23', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:24:36', 0.00, NULL, NULL, NULL, 0),
	(93, 20, 3, 110.00, '2025-04-24', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:25:41', 0.00, NULL, NULL, NULL, 0),
	(94, 20, 4, 110.00, '2025-04-25', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:37:04', 0.00, NULL, NULL, NULL, 0),
	(95, 20, 5, 110.00, '2025-04-26', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:38:37', 0.00, NULL, NULL, NULL, 0),
	(96, 20, 6, 110.00, '2025-04-27', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:44:43', 0.00, NULL, NULL, NULL, 0),
	(97, 20, 7, 110.00, '2025-04-28', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:45:47', 0.00, NULL, NULL, NULL, 0),
	(98, 20, 8, 110.00, '2025-04-29', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:48:07', 0.00, NULL, NULL, NULL, 0),
	(99, 20, 9, 110.00, '2025-04-30', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:48:29', 0.00, NULL, NULL, NULL, 0),
	(100, 20, 10, 110.00, '2025-05-02', 'pago', 110.00, '2025-04-23', 'dinheiro', 'valor_original: 110.00, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-21 18:33:21', '2025-04-23 03:50:06', 0.00, NULL, NULL, NULL, 0),
	(101, 21, 1, 43.33, '2025-04-24', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:09:49', 0.00, NULL, NULL, NULL, 0),
	(102, 21, 2, 43.33, '2025-04-25', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:12:31', 0.00, NULL, NULL, NULL, 0),
	(103, 21, 3, 43.33, '2025-04-26', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:16:26', 0.00, NULL, NULL, NULL, 0),
	(104, 21, 4, 43.33, '2025-04-27', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:31:17', 0.00, NULL, NULL, NULL, 0),
	(105, 21, 5, 43.33, '2025-04-28', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 6, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:31:37', 0.00, NULL, NULL, NULL, 0),
	(106, 21, 6, 43.33, '2025-04-29', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 6, acao_diferenca: desconto_proximas | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:32:01', 0.00, NULL, NULL, NULL, 0),
	(107, 21, 7, 43.33, '2025-04-30', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas | diferenca_transacao: 43, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:45:29', 0.00, NULL, NULL, NULL, 0),
	(108, 21, 8, 43.33, '2025-05-02', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 43, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:45:29', 0.00, NULL, NULL, NULL, 0),
	(109, 21, 9, 43.33, '2025-05-03', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:45:57', 0.00, NULL, NULL, NULL, 0),
	(110, 21, 10, 43.33, '2025-05-04', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:50:02', 0.00, NULL, NULL, NULL, 0),
	(111, 21, 11, 43.33, '2025-05-05', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:50:45', 0.00, NULL, NULL, NULL, 0),
	(112, 21, 12, 43.33, '2025-05-06', 'pago', 43.33, '2025-04-23', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-23 04:56:29', 0.00, NULL, NULL, NULL, 0),
	(113, 21, 13, 43.33, '2025-05-07', 'pago', 43.33, '2025-04-24', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-24 13:40:13', 0.00, NULL, NULL, NULL, 0),
	(114, 21, 14, 43.33, '2025-05-08', 'pago', 43.33, '2025-04-24', 'pix', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-24 19:44:35', 0.00, NULL, NULL, NULL, 0),
	(115, 21, 15, 43.33, '2025-05-09', 'pago', 43.33, '2025-04-25', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-25 05:40:31', 0.00, NULL, NULL, NULL, 0),
	(116, 21, 16, 43.33, '2025-05-10', 'pago', 43.33, '2025-04-25', 'dinheiro', 'valor_original: 43.33, diferenca_transacao: 0 | diferenca_transacao: 0, acao_diferenca: desconto_proximas', '2025-04-23 04:09:18', '2025-04-25 05:53:22', 0.00, NULL, NULL, NULL, 0),
	(117, 21, 17, 43.33, '2025-05-11', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(118, 21, 18, 43.33, '2025-05-12', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(119, 21, 19, 43.33, '2025-05-13', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(120, 21, 20, 43.33, '2025-05-14', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(121, 21, 21, 43.33, '2025-05-15', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(122, 21, 22, 43.33, '2025-05-16', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(123, 21, 23, 43.33, '2025-05-17', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(124, 21, 24, 43.33, '2025-05-18', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(125, 21, 25, 43.33, '2025-05-19', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(126, 21, 26, 43.33, '2025-05-20', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(127, 21, 27, 43.33, '2025-05-21', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(128, 21, 28, 43.33, '2025-05-22', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(129, 21, 29, 43.33, '2025-05-23', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(130, 21, 30, 43.33, '2025-05-24', 'pendente', NULL, NULL, NULL, 'valor_original: 43.33, diferenca_transacao: 0', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 0.00, NULL, NULL, NULL, 0),
	(161, 23, 1, 44.00, '2025-04-21', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(162, 23, 2, 44.00, '2025-04-22', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(163, 23, 3, 44.00, '2025-04-23', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(164, 23, 4, 44.00, '2025-04-24', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(165, 23, 5, 44.00, '2025-04-25', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(166, 23, 6, 44.00, '2025-04-26', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(167, 23, 7, 44.00, '2025-04-28', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(168, 23, 8, 44.00, '2025-04-29', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(169, 23, 9, 44.00, '2025-04-30', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(170, 23, 10, 44.00, '2025-05-02', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(171, 23, 11, 44.00, '2025-05-03', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(172, 23, 12, 44.00, '2025-05-05', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(173, 23, 13, 44.00, '2025-05-06', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(174, 23, 14, 44.00, '2025-05-07', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(175, 23, 15, 44.00, '2025-05-08', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(176, 23, 16, 44.00, '2025-05-09', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(177, 23, 17, 44.00, '2025-05-10', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(178, 23, 18, 44.00, '2025-05-12', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(179, 23, 19, 44.00, '2025-05-13', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(180, 23, 20, 44.00, '2025-05-14', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(181, 23, 21, 44.00, '2025-05-15', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(182, 23, 22, 44.00, '2025-05-16', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(183, 23, 23, 44.00, '2025-05-17', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(184, 23, 24, 44.00, '2025-05-19', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(185, 23, 25, 44.00, '2025-05-20', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(186, 23, 26, 44.00, '2025-05-21', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(187, 23, 27, 44.00, '2025-05-22', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(188, 23, 28, 44.00, '2025-05-23', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(189, 23, 29, 44.00, '2025-05-24', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(190, 23, 30, 44.00, '2025-05-26', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 10:58:16', '2025-04-25 10:58:16', 0.00, NULL, NULL, NULL, 0),
	(191, 24, 1, 65.00, '2025-04-16', 'atrasado', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(192, 24, 2, 65.00, '2025-04-17', 'atrasado', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(193, 24, 3, 65.00, '2025-04-19', 'atrasado', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(194, 24, 4, 65.00, '2025-04-22', 'atrasado', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(195, 24, 5, 65.00, '2025-04-23', 'atrasado', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(196, 24, 6, 65.00, '2025-04-24', 'atrasado', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(197, 24, 7, 65.00, '2025-04-25', 'atrasado', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 11:00:40', 0.00, NULL, NULL, NULL, 0),
	(198, 24, 8, 65.00, '2025-04-26', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(199, 24, 9, 65.00, '2025-04-28', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(200, 24, 10, 65.00, '2025-04-29', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(201, 24, 11, 65.00, '2025-04-30', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(202, 24, 12, 65.00, '2025-05-02', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(203, 24, 13, 65.00, '2025-05-03', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(204, 24, 14, 65.00, '2025-05-05', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(205, 24, 15, 65.00, '2025-05-06', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(206, 24, 16, 65.00, '2025-05-07', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(207, 24, 17, 65.00, '2025-05-08', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(208, 24, 18, 65.00, '2025-05-09', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(209, 24, 19, 65.00, '2025-05-10', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(210, 24, 20, 65.00, '2025-05-12', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(211, 24, 21, 65.00, '2025-05-13', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(212, 24, 22, 65.00, '2025-05-14', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(213, 24, 23, 65.00, '2025-05-15', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(214, 24, 24, 65.00, '2025-05-16', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(215, 24, 25, 65.00, '2025-05-17', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(216, 24, 26, 65.00, '2025-05-19', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(217, 24, 27, 65.00, '2025-05-20', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(218, 24, 28, 65.00, '2025-05-21', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(219, 24, 29, 65.00, '2025-05-22', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(220, 24, 30, 65.00, '2025-05-23', 'pendente', NULL, NULL, NULL, 'valor_original: 65.00, diferenca_transacao: 0', '2025-04-25 10:59:03', '2025-04-25 10:59:03', 0.00, NULL, NULL, NULL, 0),
	(221, 25, 1, 44.00, '2025-04-14', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(222, 25, 2, 44.00, '2025-04-16', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(223, 25, 3, 44.00, '2025-04-17', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(224, 25, 4, 44.00, '2025-04-19', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(225, 25, 5, 44.00, '2025-04-20', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(226, 25, 6, 44.00, '2025-04-23', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(227, 25, 7, 44.00, '2025-04-24', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(228, 25, 8, 44.00, '2025-04-25', 'atrasado', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:42', 0.00, NULL, NULL, NULL, 0),
	(229, 25, 9, 44.00, '2025-04-26', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(230, 25, 10, 44.00, '2025-04-27', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(231, 25, 11, 44.00, '2025-04-28', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(232, 25, 12, 44.00, '2025-04-30', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(233, 25, 13, 44.00, '2025-05-02', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(234, 25, 14, 44.00, '2025-05-03', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(235, 25, 15, 44.00, '2025-05-04', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(236, 25, 16, 44.00, '2025-05-05', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(237, 25, 17, 44.00, '2025-05-07', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(238, 25, 18, 44.00, '2025-05-08', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(239, 25, 19, 44.00, '2025-05-09', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(240, 25, 20, 44.00, '2025-05-10', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(241, 25, 21, 44.00, '2025-05-11', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(242, 25, 22, 44.00, '2025-05-12', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(243, 25, 23, 44.00, '2025-05-14', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(244, 25, 24, 44.00, '2025-05-15', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(245, 25, 25, 44.00, '2025-05-16', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(246, 25, 26, 44.00, '2025-05-17', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(247, 25, 27, 44.00, '2025-05-18', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(248, 25, 28, 44.00, '2025-05-19', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(249, 25, 29, 44.00, '2025-05-21', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0),
	(250, 25, 30, 44.00, '2025-05-22', 'pendente', NULL, NULL, NULL, 'valor_original: 44.00, diferenca_transacao: 0', '2025-04-25 11:19:39', '2025-04-25 11:19:39', 0.00, NULL, NULL, NULL, 0);

-- Copiando estrutura para tabela platafo5_licred2.templates_mensagens
CREATE TABLE IF NOT EXISTS `templates_mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL,
  `mensagem` text NOT NULL,
  `incluir_nome` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_valor` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_vencimento` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_atraso` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_valor_total` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_valor_em_aberto` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_total_parcelas` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_parcelas_pagas` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_valor_pago` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_numero_parcela` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_lista_parcelas` tinyint(1) NOT NULL DEFAULT 0,
  `incluir_link_pagamento` tinyint(1) NOT NULL DEFAULT 0,
  `usuario_id` int(11) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `status` (`status`),
  KEY `ativo` (`ativo`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.templates_mensagens: ~8 rows (aproximadamente)
REPLACE INTO `templates_mensagens` (`id`, `nome`, `status`, `mensagem`, `incluir_nome`, `incluir_valor`, `incluir_vencimento`, `incluir_atraso`, `incluir_valor_total`, `incluir_valor_em_aberto`, `incluir_total_parcelas`, `incluir_parcelas_pagas`, `incluir_valor_pago`, `incluir_numero_parcela`, `incluir_lista_parcelas`, `incluir_link_pagamento`, `usuario_id`, `ativo`, `data_criacao`, `data_atualizacao`) VALUES
	(3, 'Confirmação de Quitação', 'quitado', 'Olá {nome_cliente},\r\n\r\nRecebemos seu pagamento e confirmamos a quitação do empréstimo.\r\n\r\nValor total do empréstimo: {valor_total}\r\nTotal de parcelas: {total_parcelas}\r\nValor pago: {valor_pago}\r\n\r\nAgradecemos a preferência!\r\n\r\nAtenciosamente,\r\n{nomedogestor}', 1, 0, 0, 0, 1, 0, 1, 0, 1, 0, 0, 0, 1, 1, '2025-04-16 18:20:59', '2025-04-23 02:41:30'),
	(10, 'Resumo de Novo Emprestimos.', 'Boas Vindas', 'Olá, {nome_cliente}!\r\n\r\nSeu empréstimo foi cadastrado com sucesso.\r\n\r\nValor total: {valor_total}\r\nTotal de parcelas: {total_parcelas}\r\nValor da parcela: {valor_parcela}\r\n\r\nAtenciosamente,\r\nLicred', 1, 1, 0, 0, 1, 0, 1, 0, 0, 0, 0, 0, 1, 1, '2025-04-22 16:43:39', '2025-04-23 02:44:03'),
	(11, 'Parcela Vence Hoje', 'cobrancadia', 'Olá, {nome_cliente}!\r\n\r\nA parcela no valor de {valor_parcela} vence hoje ({data_vencimento}).\r\n\r\nPara pagar, envie o valor para o pix: \r\n\r\n123123123123 \r\n\r\nOu pague no link de pagamento {link_pagamento}\r\n\r\nAtenciosamente,\r\nLicred', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, '2025-04-22 16:45:54', '2025-04-22 16:45:54'),
	(12, 'Pagamento Parcial', 'parcial', 'Olá, {nome_cliente}!\r\n\r\nPagamento parcial de {valor_pago} foi pago.', 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 1, '2025-04-22 16:47:25', '2025-04-23 03:43:45'),
	(13, 'Parcela Paga', 'pago', '*----------------------------*\r\n*PAGAMENTO EFETUADO*  \r\n*-----------------------------*\r\nDados do Pagamento\r\nCliente: {nome_cliente}\r\nContrato: 12345\r\nValor: {valor_parcela}\r\nParcela: {parcelas_pagas} de {total_parcelas}', 1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 1, 1, '2025-04-22 16:48:09', '2025-04-23 02:40:55'),
	(14, 'Aviso de Atraso', 'atrasado', 'Olá {nome_cliente},\r\n\r\nSua parcela de {valor_parcela} que vencia em {data_vencimento} está atrasada há {atraso}.\r\n\r\nValor total do empréstimo: {valor_total}\r\nValor em aberto: {valor_em_aberto}\r\nTotal de parcelas: {total_parcelas}\r\nParcelas pagas: {parcelas_pagas}\r\nValor já pago: {valor_pago}\r\n\r\nPara regularizar sua situação, acesse: {link_pagamento}\r\n\r\nAtenciosamente,\r\n{nomedogestor}', 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, '2025-04-23 03:52:47', '2025-04-23 03:52:47'),
	(15, 'Pendentes Hoje', 'pendente', 'Olá {nome_cliente},\r\n\r\nSua parcela de {valor_parcela} que vencia em {data_vencimento} está atrasada há {atraso}.\r\n\r\nValor total do empréstimo: {valor_total}\r\nValor em aberto: {valor_em_aberto}\r\nTotal de parcelas: {total_parcelas}\r\nParcelas pagas: {parcelas_pagas}\r\nValor já pago: {valor_pago}\r\n\r\nPara regularizar sua situação, acesse: {link_pagamento}\r\n\r\nAtenciosamente,\r\n{nomedogestor}', 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, '2025-04-23 03:53:55', '2025-04-23 03:53:55'),
	(16, 'Completou Parcial', 'parcela_completada', 'Olá {nome_cliente}, confirmamos o recebimento do COMPLEMENTO da sua parcela nº {numero_parcela} no valor de R$ {valor_parcela}.\r\n\r\nSua parcela agora está TOTALMENTE PAGA.\r\n\r\nAgradecemos sua pontualidade!\r\n\r\nAtenciosamente,\r\n{nomedogestor}', 1, 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 1, 1, '2025-04-23 04:06:03', '2025-04-23 04:07:36');

-- Copiando estrutura para tabela platafo5_licred2.templates_mensagens_guardar2
CREATE TABLE IF NOT EXISTS `templates_mensagens_guardar2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `status` enum('texto','imagem','documento') NOT NULL DEFAULT 'texto',
  `mensagem` text NOT NULL,
  `arquivo` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.templates_mensagens_guardar2: ~3 rows (aproximadamente)
REPLACE INTO `templates_mensagens_guardar2` (`id`, `nome`, `status`, `mensagem`, `arquivo`, `ativo`, `criado_em`, `atualizado_em`) VALUES
	(1, 'Boas-vindas', 'texto', 'Olá, {nome_cliente}! Seja bem-vindo(a) ao sistema de empréstimos. Estamos à disposição para ajudá-lo(a). Atenciosamente, {nomedogestor}.', NULL, 1, '2025-04-21 17:14:53', NULL),
	(2, 'Lembrete de Pagamento', 'texto', 'Olá, {nome_cliente}! Gostaríamos de lembrá-lo(a) sobre o pagamento que vence em breve. Se já realizou o pagamento, por favor desconsidere esta mensagem. Atenciosamente, {nomedogestor}.', NULL, 1, '2025-04-21 17:14:53', NULL),
	(3, 'Confirmação de Empréstimo', 'texto', 'Olá, {nome_cliente}! Seu empréstimo foi aprovado com sucesso. Para mais informações, entre em contato conosco. Atenciosamente, {nomedogestor}.', NULL, 1, '2025-04-21 17:14:53', NULL);

-- Copiando estrutura para tabela platafo5_licred2.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela platafo5_licred2.usuarios: ~0 rows (aproximadamente)
REPLACE INTO `usuarios` (`id`, `email`, `senha`) VALUES
	(1, 'admin@teste.com', '$2a$10$I1saM1f4iI9gmOeADt8yvelB//rjtwK1h1xJcHP5PKJFjTr3IgD6y');

-- Copiando estrutura para tabela platafo5_licred2.whatsapp_configs
CREATE TABLE IF NOT EXISTS `whatsapp_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) DEFAULT 'WhatsApp Principal',
  `numero` varchar(20) DEFAULT NULL COMMENT 'Número de telefone com DDD',
  `api_token` varchar(255) DEFAULT NULL COMMENT 'Token de acesso à API',
  `authkey` varchar(255) DEFAULT NULL COMMENT 'Token de acesso à API',
  `appkey` varchar(255) DEFAULT NULL COMMENT 'Token de acesso à API',
  `endpoint` varchar(255) DEFAULT NULL,
  `qrcode_data` text DEFAULT NULL COMMENT 'QR Code em base64',
  `data_qrcode` timestamp NULL DEFAULT NULL COMMENT 'Data de geração do QR Code',
  `status_conexao` enum('disconnected','connected','aguardando') NOT NULL DEFAULT 'disconnected',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `padrao` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se é o WhatsApp padrão',
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_licred2.whatsapp_configs: ~2 rows (aproximadamente)
REPLACE INTO `whatsapp_configs` (`id`, `nome`, `numero`, `api_token`, `authkey`, `appkey`, `endpoint`, `qrcode_data`, `data_qrcode`, `status_conexao`, `ativo`, `padrao`, `data_atualizacao`) VALUES
	(1, 'https://chatbot.menuia.com/', 'd563230a-7727-4a4d-8', 'vgkyTWyV3eMKdN6t2ErF2ky5Zco2MKKghdF7HppPfA1YKp8dvB', NULL, NULL, NULL, NULL, NULL, 'disconnected', 1, 0, '2025-04-22 16:01:54'),
	(3, 'https://chatbot.menuia.com/', 'd563230a-7727-4a4d-8', 'vgkyTWyV3eMKdN6t2ErF2ky5Zco2MKKghdF7HppPfA1YKp8dvB', NULL, NULL, NULL, NULL, NULL, 'disconnected', 1, 0, NULL);

-- Copiando estrutura para tabela platafo5_licred2.whatsapp_configs_copy
CREATE TABLE IF NOT EXISTS `whatsapp_configs_copy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) DEFAULT 'WhatsApp Principal',
  `numero` varchar(20) DEFAULT NULL COMMENT 'Número de telefone com DDD',
  `api_token` varchar(255) DEFAULT NULL COMMENT 'Token de acesso à API',
  `qrcode_data` text DEFAULT NULL COMMENT 'QR Code em base64',
  `data_qrcode` timestamp NULL DEFAULT NULL COMMENT 'Data de geração do QR Code',
  `status_conexao` enum('disconnected','connected','aguardando') NOT NULL DEFAULT 'disconnected',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `padrao` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se é o WhatsApp padrão',
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- Copiando dados para a tabela platafo5_licred2.whatsapp_configs_copy: ~0 rows (aproximadamente)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
