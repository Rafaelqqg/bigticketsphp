-- Avaliação de satisfação (1 a 5, onde 5 é ótimo) para tickets fechados
ALTER TABLE tickets ADD COLUMN avaliacao TINYINT NULL DEFAULT NULL COMMENT '1-5, 5=ótimo' AFTER fechado_por;
ALTER TABLE tickets ADD COLUMN avaliacao_em DATETIME NULL DEFAULT NULL AFTER avaliacao;
