-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           10.4.32-MariaDB - mariadb.org binary distribution
-- OS do Servidor:               Win64
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

-- Copiando estrutura para tabela sistema_emprestimosv1_8.emprestimos
CREATE TABLE IF NOT EXISTS `emprestimos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `tipo_de_cobranca` enum('parcelada_comum','reparcelada_com_juros') NOT NULL,
  `valor_emprestado` decimal(10,2) NOT NULL,
  `parcelas` int(11) NOT NULL,
  `valor_parcela` decimal(10,2) DEFAULT NULL,
  `juros_percentual` decimal(5,2) DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `json_parcelas---------------------------` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `configuracao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Configurações do empréstimo em formato JSON' CHECK (json_valid(`configuracao`)),
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'ativo',
  PRIMARY KEY (`id`),
  KEY `idx_emprestimos_cliente_id` (`cliente_id`),
  KEY `idx_emprestimos_data_inicio` (`data_inicio`),
  CONSTRAINT `emprestimos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela sistema_emprestimosv1_8.emprestimos: ~2 rows (aproximadamente)
REPLACE INTO `emprestimos` (`id`, `cliente_id`, `tipo_de_cobranca`, `valor_emprestado`, `parcelas`, `valor_parcela`, `juros_percentual`, `data_inicio`, `json_parcelas---------------------------`, `configuracao`, `data_criacao`, `data_atualizacao`, `status`) VALUES
	(18, 14, 'parcelada_comum', 1000.00, 30, 44.00, 32.00, '2025-04-21', NULL, '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":44}', '2025-04-21 13:32:59', '2025-04-21 13:32:59', 'ativo'),
	(19, 14, 'parcelada_comum', 2000.00, 30, 87.00, 30.50, '2025-04-22', NULL, '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":87}', '2025-04-21 17:11:52', '2025-04-21 17:11:52', 'ativo'),
	(20, 13, 'parcelada_comum', 1000.00, 10, 110.00, 10.00, '2025-04-22', NULL, '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":110}', '2025-04-21 18:33:21', '2025-04-21 18:33:21', 'ativo'),
	(21, 13, 'parcelada_comum', 1000.00, 30, 43.00, 29.99, '2025-04-24', NULL, '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":43.33}', '2025-04-23 04:09:18', '2025-04-23 04:09:18', 'ativo'),
	(22, 13, 'parcelada_comum', 1500.00, 30, 65.00, 30.00, '2025-04-25', NULL, '{"usar_tlc":false,"tlc_valor":0,"modo_calculo":"parcela","periodo_pagamento":"diario","dias_semana":["feriados"],"considerar_feriados":true,"valor_parcela_padrao":65}', '2025-04-24 21:42:41', '2025-04-24 21:42:41', 'ativo');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
