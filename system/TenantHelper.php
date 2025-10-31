<?php

namespace System;

/**
 * Helper class for tenant and filial context management
 * Ensures every view has valid tenant_id and filial_id
 */
class TenantHelper
{
    /**
     * Ensure session has valid tenant and filial
     * If filial is missing, loads the default filial for the tenant
     * 
     * @return array ['tenant' => array, 'filial' => array|null]
     */
    public static function ensureTenantContext()
    {
        $session = Session::getInstance();
        $db = Database::getInstance();
        
        $tenant = $session->getTenant();
        $filial = $session->getFilial();
        
        // If no tenant in session, cannot proceed
        if (!$tenant) {
            error_log("TenantHelper::ensureTenantContext - No tenant in session");
            return ['tenant' => null, 'filial' => null];
        }
        
        // If no filial in session, load the default filial for this tenant
        if (!$filial) {
            $filial = $db->fetch(
                "SELECT * FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1",
                [$tenant['id']]
            );
            
            if ($filial) {
                $session->setFilial($filial);
                error_log("TenantHelper::ensureTenantContext - Loaded default filial {$filial['id']} for tenant {$tenant['id']}");
            } else {
                error_log("TenantHelper::ensureTenantContext - No filial found for tenant {$tenant['id']}");
            }
        }
        
        // Load cor_primaria from filial_settings
        if ($filial && $tenant) {
            $corSetting = $db->fetch(
                "SELECT setting_value FROM filial_settings WHERE tenant_id = ? AND filial_id = ? AND setting_key = 'cor_primaria'",
                [$tenant['id'], $filial['id']]
            );
            
            if ($corSetting) {
                $tenant['cor_primaria'] = $corSetting['setting_value'];
            }
        }
        
        return [
            'tenant' => $tenant,
            'filial' => $filial
        ];
    }
    
    /**
     * Get tenant_id and filial_id for database queries
     * 
     * @return array ['tenant_id' => int, 'filial_id' => int|null]
     */
    public static function getContextIds()
    {
        $context = self::ensureTenantContext();
        
        return [
            'tenant_id' => $context['tenant']['id'] ?? null,
            'filial_id' => $context['filial']['id'] ?? null
        ];
    }
}

