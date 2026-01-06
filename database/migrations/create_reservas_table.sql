-- Create reservas table for online menu reservations
CREATE TABLE IF NOT EXISTS reservas (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    
    -- Reservation details
    num_convidados INTEGER NOT NULL DEFAULT 1,
    data_reserva DATE NOT NULL,
    hora_reserva TIME NOT NULL,
    
    -- Customer information
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    celular VARCHAR(20) NOT NULL,
    instrucoes TEXT,
    cliente_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL,
    
    -- Status and tracking
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'confirmada', 'cancelada', 'concluida', 'nao_compareceu')),
    mesa_id INTEGER REFERENCES mesas(id) ON DELETE SET NULL,
    observacoes_internas TEXT,
    lembrete_enviado BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    CONSTRAINT fk_reservas_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservas_filial FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE SET NULL,
    CONSTRAINT fk_reservas_mesa FOREIGN KEY (mesa_id) REFERENCES mesas(id) ON DELETE SET NULL
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_reservas_tenant_filial ON reservas(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_reservas_data_hora ON reservas(data_reserva, hora_reserva);
CREATE INDEX IF NOT EXISTS idx_reservas_status ON reservas(status);
CREATE INDEX IF NOT EXISTS idx_reservas_mesa ON reservas(mesa_id);
CREATE INDEX IF NOT EXISTS idx_reservas_cliente_id ON reservas(cliente_id);
CREATE INDEX IF NOT EXISTS idx_reservas_lembrete ON reservas(data_reserva, status, lembrete_enviado) WHERE status = 'confirmada';

-- Add comment to table
COMMENT ON TABLE reservas IS 'Reservas de mesas feitas através do cardápio online';
COMMENT ON COLUMN reservas.cliente_id IS 'ID do cliente (usuarios_globais) que fez a reserva';
COMMENT ON COLUMN reservas.lembrete_enviado IS 'Indica se o lembrete de confirmação foi enviado no dia da reserva';

