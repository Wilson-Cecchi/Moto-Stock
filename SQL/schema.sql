-- ============================================================
-- MotoStock — Schema (DDL)
-- Execute este arquivo primeiro no phpMyAdmin
-- ============================================================

CREATE DATABASE IF NOT EXISTS motostock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE motostock;

-- Drop na ordem inversa das FKs
DROP TABLE IF EXISTS solicitacoes;
DROP TABLE IF EXISTS transferencias;
DROP TABLE IF EXISTS metas;
DROP TABLE IF EXISTS vendas;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS lojas;

-- LOJAS
CREATE TABLE lojas (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    nome     VARCHAR(100) NOT NULL,
    tipo     ENUM('Matriz','Filial') NOT NULL,
    cidade   VARCHAR(100) NOT NULL,
    estado   CHAR(2) NOT NULL,
    endereco VARCHAR(200)
);

-- CLIENTES
CREATE TABLE clientes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    email      VARCHAR(150),
    loja_id    INT NOT NULL,
    FOREIGN KEY (loja_id) REFERENCES lojas(id)
);

-- PRODUTOS
CREATE TABLE produtos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    loja_id        INT NOT NULL,
    nome           VARCHAR(200) NOT NULL,
    categoria      VARCHAR(100) NOT NULL,
    preco          DECIMAL(10,2) NOT NULL,
    estoque        INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 5,
    FOREIGN KEY (loja_id) REFERENCES lojas(id)
);

-- USUARIOS  (nivel_acesso ja inclui funcionario)
CREATE TABLE IF NOT EXISTS usuarios (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nome         VARCHAR(100) NOT NULL,
    usuario      VARCHAR(50)  NOT NULL UNIQUE,
    senha        VARCHAR(255) NOT NULL,          -- password_hash (bcrypt)
    nivel_acesso ENUM('admin','gerente','funcionario') NOT NULL DEFAULT 'gerente',
    loja_id      INT NULL,                        -- NULL = acesso global (admin)
    ativo        TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (loja_id) REFERENCES lojas(id)
);

-- VENDAS
CREATE TABLE vendas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(10) NOT NULL,
    data_venda  DATE NOT NULL,
    loja_id     INT NOT NULL,
    cliente_id  INT NOT NULL,
    produto     VARCHAR(200) NOT NULL,
    categoria   VARCHAR(100) NOT NULL,
    qtd         INT NOT NULL,
    preco_unit  DECIMAL(10,2) NOT NULL,
    total       DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (loja_id)   REFERENCES lojas(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

-- METAS
CREATE TABLE IF NOT EXISTS metas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    loja_id     INT           NOT NULL,
    mes         TINYINT       NOT NULL,   -- 1-12
    ano         SMALLINT      NOT NULL,
    meta_valor  DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE KEY  uk_loja_mes_ano (loja_id, mes, ano),
    FOREIGN KEY (loja_id) REFERENCES lojas(id)
);

-- TRANSFERENCIAS
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

-- SOLICITACOES
CREATE TABLE IF NOT EXISTS solicitacoes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tipo            ENUM('reposicao','transferencia') NOT NULL,
    status          ENUM('pendente','aprovado_origem','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',

    -- Quem pediu
    funcionario_id  INT NOT NULL,
    loja_solicitante INT NOT NULL,

    -- Produto
    produto_nome    VARCHAR(200) NOT NULL,
    categoria       VARCHAR(100) NOT NULL,
    quantidade      INT NOT NULL,

    -- Para transferência: loja que vai ceder o produto
    loja_cedente    INT NULL,

    -- Observações
    motivo          TEXT NULL,
    obs_resposta    TEXT NULL,

    -- Aprovações
    aprovado_por_origem   INT NULL,   -- gerente/admin da loja solicitante
    aprovado_por_cedente  INT NULL,   -- gerente/admin da loja cedente (só transferência)

    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (funcionario_id)       REFERENCES usuarios(id),
    FOREIGN KEY (loja_solicitante)     REFERENCES lojas(id),
    FOREIGN KEY (loja_cedente)         REFERENCES lojas(id),
    FOREIGN KEY (aprovado_por_origem)  REFERENCES usuarios(id),
    FOREIGN KEY (aprovado_por_cedente) REFERENCES usuarios(id)
);
-- Acesso admin inicial
INSERT IGNORE INTO usuarios (nome, usuario, senha, nivel_acesso, loja_id) VALUES
('Administrador Geral', 'admin', '$2b$10$4K5KT/ld/85xUnqKFi2..ugq3hllqO7wBSbcl0xSlB./zdKc5T7wC', 'admin', NULL);