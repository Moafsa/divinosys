-- Create table for filial-specific settings (appearance, configurations, etc.)
CREATE TABLE IF NOT EXISTS filial_settings (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, filial_id, setting_key)
);

CREATE INDEX IF NOT EXISTS idx_filial_settings_tenant_filial ON filial_settings(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_filial_settings_key ON filial_settings(setting_key);

-- Migrate existing cor_primaria from tenants to filial_settings for filial 1
INSERT INTO filial_settings (tenant_id, filial_id, setting_key, setting_value)
SELECT t.id, f.id, 'cor_primaria', t.cor_primaria
FROM tenants t
JOIN filiais f ON f.tenant_id = t.id AND f.id = (SELECT MIN(id) FROM filiais WHERE tenant_id = t.id)
WHERE t.cor_primaria IS NOT NULL
ON CONFLICT (tenant_id, filial_id, setting_key) DO NOTHING;

