-- Adiciona coluna fechado_por na tabela tickets para registrar quem fechou o ticket
ALTER TABLE tickets ADD COLUMN fechado_por VARCHAR(64) NULL DEFAULT NULL AFTER updated_at;

-- Opcional: preencher tickets já fechados com o responsável (quem provavelmente fechou)
-- UPDATE tickets SET fechado_por = responsavel WHERE status IN ('fechado','resolvido','Fechado','Resolvido') AND fechado_por IS NULL AND responsavel IS NOT NULL;
