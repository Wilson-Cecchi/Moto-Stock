-- ============================================================
-- MotoStock — Melhorias v2 (Médio Prazo)
-- Execute no phpMyAdmin após o setup.sql original
-- ============================================================
USE motostock;

-- ------------------------------------------------------------
-- USUÁRIOS (login com permissões)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nome         VARCHAR(100) NOT NULL,
    usuario      VARCHAR(50)  NOT NULL UNIQUE,
    senha        VARCHAR(255) NOT NULL,          -- password_hash (bcrypt)
    nivel_acesso ENUM('admin','gerente') NOT NULL DEFAULT 'gerente',
    loja_id      INT NULL,                        -- NULL = acesso global (admin)
    ativo        TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (loja_id) REFERENCES lojas(id)
);

-- ⚠ Senhas geradas via setup_usuarios.php (acesse 1x e delete)
-- Padrão: admin / motostock2025 | gerentes / loja1234

-- ------------------------------------------------------------
-- METAS DE VENDAS POR LOJA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS metas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    loja_id     INT           NOT NULL,
    mes         TINYINT       NOT NULL,   -- 1-12
    ano         SMALLINT      NOT NULL,
    meta_valor  DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE KEY  uk_loja_mes_ano (loja_id, mes, ano),
    FOREIGN KEY (loja_id) REFERENCES lojas(id)
);

-- Metas de exemplo para Jan-Mar 2025
INSERT IGNORE INTO metas (loja_id, mes, ano, meta_valor) VALUES
(1,1,2025,25000.00),(1,2,2025,27000.00),(1,3,2025,30000.00),
(2,1,2025,18000.00),(2,2,2025,18000.00),(2,3,2025,20000.00),
(3,1,2025,45000.00),(3,2,2025,40000.00),(3,3,2025,50000.00),
(4,1,2025,12000.00),(4,2,2025,12000.00),(4,3,2025,15000.00),
(5,1,2025,10000.00),(5,2,2025,10000.00),(5,3,2025,12000.00);

-- ------------------------------------------------------------
-- TRANSFERÊNCIAS DE ESTOQUE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transferencias (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    produto_nome       VARCHAR(200) NOT NULL,
    categoria          VARCHAR(100) NOT NULL,
    quantidade         INT NOT NULL,
    loja_origem        INT NOT NULL,
    loja_destino       INT NOT NULL,
    data_transferencia DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_origem)  REFERENCES lojas(id),
    FOREIGN KEY (loja_destino) REFERENCES lojas(id)
);
