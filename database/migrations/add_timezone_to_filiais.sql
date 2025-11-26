-- =====================================================
-- MIGRATION: ADD TIMEZONE TO FILIAIS
-- Description: Add timezone field to filiais table for proper time handling
-- Date: 2025-01-15
-- =====================================================

-- Add timezone field to filiais table
ALTER TABLE filiais 
ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'America/Sao_Paulo';

-- Add comment
COMMENT ON COLUMN filiais.timezone IS 'Timezone do estabelecimento (ex: America/Sao_Paulo, America/Manaus, etc)';

-- Update existing records to use default timezone if null
UPDATE filiais SET timezone = 'America/Sao_Paulo' WHERE timezone IS NULL;

