-- Verificar e adicionar coluna tipo na tabela usuarios se não existir
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS tipo ENUM('admin', 'investidor', 'cliente') DEFAULT NULL COMMENT 'Tipo de usuário no sistema';

-- Atualizar existente admin
UPDATE usuarios SET tipo = 'admin', nivel_autoridade = 'administrador' WHERE id = 1;

-- Verificar e adicionar coluna investidor_id na tabela emprestimos se não existir
ALTER TABLE emprestimos ADD COLUMN IF NOT EXISTS investidor_id INT DEFAULT NULL AFTER cliente_id;

-- Adicionar foreign key para investidor_id se não existir (verificando se já existe primeiro)
SET @constraint_exists = (
    SELECT COUNT(1) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'emprestimos' 
    AND COLUMN_NAME = 'investidor_id' 
    AND REFERENCED_TABLE_NAME = 'usuarios'
);

SET @sql = IF(@constraint_exists > 0, 
    'SELECT "Foreign key já existe"', 
    'ALTER TABLE emprestimos ADD CONSTRAINT fk_emprestimos_investidor FOREIGN KEY (investidor_id) REFERENCES usuarios(id)'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna comissao na tabela contas se não existir
ALTER TABLE contas ADD COLUMN IF NOT EXISTS comissao DECIMAL(5,2) DEFAULT 40.00 COMMENT 'Percentual de comissão do investidor';

-- Garantir que o tipo corresponda ao nível_autoridade para consistência
UPDATE usuarios SET tipo = 'admin' WHERE nivel_autoridade = 'administrador' AND tipo IS NULL;
UPDATE usuarios SET tipo = 'admin' WHERE nivel_autoridade = 'superadmin' AND tipo IS NULL;
UPDATE usuarios SET nivel_autoridade = 'investidor' WHERE tipo = 'investidor' AND nivel_autoridade IS NULL;

-- Script para criar a tabela de contas se não existir
CREATE TABLE IF NOT EXISTS contas (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    saldo_inicial DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    comissao DECIMAL(5,2) DEFAULT 40.00 COMMENT 'Percentual de comissão do investidor',
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Script para criar a tabela de movimentações de contas se não existir
CREATE TABLE IF NOT EXISTS movimentacoes_contas (
    id INT NOT NULL AUTO_INCREMENT,
    conta_id INT NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao TEXT NULL,
    data_movimentacao DATETIME NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (conta_id) REFERENCES contas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 