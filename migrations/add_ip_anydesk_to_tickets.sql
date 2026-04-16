-- Adiciona campo para o solicitante informar IP da máquina ou ID do AnyDesk.
ALTER TABLE tickets
ADD COLUMN ip_anydesk VARCHAR(100) NULL;

