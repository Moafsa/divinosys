-- Add trial_days column to planos table
-- This allows each plan to have a configurable trial period

ALTER TABLE planos 
ADD COLUMN IF NOT EXISTS trial_days INTEGER DEFAULT 14;

COMMENT ON COLUMN planos.trial_days IS 'Number of trial days for this plan (0 = no trial, -1 = unlimited trial)';

-- Update existing plans with default values
UPDATE planos SET trial_days = 14 WHERE trial_days IS NULL;

