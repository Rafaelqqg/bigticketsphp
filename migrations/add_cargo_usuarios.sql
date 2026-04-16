-- Cargo: Colaborador (padrão), Gestor, Supervisor. Quem se cadastra em "Criar conta" vem como Colaborador; admin pode alterar na edição.
ALTER TABLE usuarios ADD COLUMN cargo VARCHAR(50) DEFAULT 'Colaborador';
