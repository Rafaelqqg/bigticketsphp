-- Migração: filial_id -> filial_codigo (armazena codigo da filial: 1, 2, 3...)
-- Execute este script no banco ANTES de usar a nova versão do sistema.
--
-- Passo 1: Remove FKs que referenciam filiais(id)
ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_filial;
ALTER TABLE solicitacoes_cadastro DROP FOREIGN KEY solicitacoes_cadastro_ibfk_1;

-- Passo 2: Atualiza filial_id com codigo da filial
UPDATE usuarios u INNER JOIN filiais f ON f.id = u.filial_id SET u.filial_id = CAST(f.codigo AS UNSIGNED);
UPDATE tickets t INNER JOIN filiais f ON f.id = t.filial_id SET t.filial_id = CAST(f.codigo AS UNSIGNED);
UPDATE solicitacoes_cadastro sc INNER JOIN filiais f ON f.id = sc.filial_id SET sc.filial_id = CAST(f.codigo AS UNSIGNED);

-- Passo 3: Remover coluna id de filiais (usar codigo como PK)
ALTER TABLE filiais MODIFY id INT NOT NULL;
ALTER TABLE filiais DROP PRIMARY KEY, ADD PRIMARY KEY (codigo);
ALTER TABLE filiais DROP COLUMN id;

-- Passo 4: Renomear filial_id para filial_codigo
ALTER TABLE usuarios CHANGE COLUMN filial_id filial_codigo INT NULL;
ALTER TABLE tickets CHANGE COLUMN filial_id filial_codigo INT NULL;
ALTER TABLE solicitacoes_cadastro CHANGE COLUMN filial_id filial_codigo INT NULL;
