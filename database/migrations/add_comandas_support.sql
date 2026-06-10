-- Adiciona a coluna tipo_atendimento na tabela mesas
ALTER TABLE mesas 
ADD COLUMN IF NOT EXISTS tipo_atendimento VARCHAR(20) DEFAULT 'mesa' CHECK (tipo_atendimento IN ('mesa', 'comanda'));

-- Atualiza a check constraint de status na tabela mesas para incluir possiveis status de comandas
-- Status atuais: '1', '2', '3', 'livre', 'ocupada', 'reservada'
-- Para facilitar, vamos apenas remover e recriar a constraint ou mudar os domínios
ALTER TABLE mesas DROP CONSTRAINT IF EXISTS mesas_status_check;

ALTER TABLE mesas ADD CONSTRAINT mesas_status_check 
CHECK (status IN ('1', '2', '3', 'livre', 'ocupada', 'reservada', 'fechada', 'aberta', 'paga'));
