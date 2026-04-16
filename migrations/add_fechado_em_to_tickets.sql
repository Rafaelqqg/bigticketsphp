-- Data/hora real de fechamento do ticket (evita distorção ao usar updated_at).
ALTER TABLE tickets ADD COLUMN fechado_em DATETIME NULL;

-- Backfill inicial: para tickets já fechados/resolvidos sem fechado_em, usar updated_at
-- sem alterar o próprio updated_at (importante para não distorcer histórico).
UPDATE tickets
SET fechado_em = updated_at,
    updated_at = updated_at
WHERE fechado_em IS NULL
  AND LOWER(TRIM(COALESCE(status, ''))) IN ('fechado', 'resolvido');

