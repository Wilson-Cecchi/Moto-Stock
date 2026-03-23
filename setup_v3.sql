-- ============================================================
-- MotoStock — Setup v3
-- Execute no phpMyAdmin após setup_v2.sql
-- ============================================================
USE motostock;

-- Adiciona nível 'funcionario' ao ENUM de usuarios
ALTER TABLE usuarios
    MODIFY COLUMN nivel_acesso ENUM('admin','gerente','funcionario') NOT NULL DEFAULT 'funcionario';

-- ------------------------------------------------------------
-- SOLICITAÇÕES DE REPOSIÇÃO E TRANSFERÊNCIA
-- ------------------------------------------------------------
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
