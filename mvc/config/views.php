<?php
/**
 * Configuração de Views
 * Define quais views estão disponíveis no sistema
 */

return [
    // Views públicas (sem autenticação)
    'login' => [
        'path' => 'mvc/views/login.php',
        'auth_required' => false
    ],
    'login_admin' => [
        'path' => 'mvc/views/login_admin.php',
        'auth_required' => false
    ],
    'onboarding' => [
        'path' => 'mvc/views/onboarding.php',
        'auth_required' => false
    ],
    'planos' => [
        'path' => 'mvc/views/planos.php',
        'auth_required' => false
    ],
    
    // Views do SuperAdmin
    'superadmin_dashboard' => [
        'path' => 'mvc/views/superadmin_dashboard.php',
        'auth_required' => true,
        'nivel_required' => 999
    ],
    
    // Views do Tenant/Estabelecimento
    'tenant_dashboard' => [
        'path' => 'mvc/views/tenant_dashboard.php',
        'auth_required' => true
    ],
    
    // Views principais do sistema
    'Dashboard1' => [
        'path' => 'mvc/views/Dashboard1.php',
        'auth_required' => true
    ],
    'mesas' => [
        'path' => 'mvc/views/mesas.php',
        'auth_required' => true
    ],
    'gerar_pedido' => [
        'path' => 'mvc/views/gerar_pedido.php',
        'auth_required' => true
    ],
    'pedidos' => [
        'path' => 'mvc/views/pedidos.php',
        'auth_required' => true
    ],
    'delivery' => [
        'path' => 'mvc/views/delivery.php',
        'auth_required' => true
    ],
    'gerenciar_produtos' => [
        'path' => 'mvc/views/gerenciar_produtos.php',
        'auth_required' => true
    ],
    'clientes' => [
        'path' => 'mvc/views/clientes.php',
        'auth_required' => true
    ],
    'relatorios' => [
        'path' => 'mvc/views/relatorios.php',
        'auth_required' => true
    ],
    'financeiro' => [
        'path' => 'mvc/views/financeiro.php',
        'auth_required' => true
    ],
    'estoque' => [
        'path' => 'mvc/views/estoque.php',
        'auth_required' => true
    ],
    'asaas_config' => [
        'path' => 'mvc/views/asaas_config.php',
        'auth_required' => true
    ],
    'configuracoes' => [
        'path' => 'mvc/views/configuracoes.php',
        'auth_required' => true
    ],
    
    // Logout
    'logout' => [
        'path' => 'mvc/views/logout.php',
        'auth_required' => false
    ],
    
    // Página de assinatura expirada
    'subscription_expired' => [
        'path' => 'mvc/views/subscription_expired.php',
        'auth_required' => true
    ]
];

