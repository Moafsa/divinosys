<?php
// Componente de Sidebar Padrão
$currentRoute = $_GET['view'] ?? 'dashboard';
$navigationMenu = $GLOBALS['navigationMenu'] ?? [];
$tenantName = $tenant['nome'] ?? 'Divino Lanches';
?>

<!-- Sidebar Overlay for Mobile -->
<div id="sidebar-overlay" class="sidebar-overlay"></div>

<!-- Sidebar -->
<div class="sidebar collapsed" id="sidebar">
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-content">
        <div class="sidebar-brand">
            <div class="brand-icon text-white">
                <i class="fas fa-utensils"></i>
            </div>
            <h4 class="text-white mb-0">
                <?php echo $tenantName; ?>
            </h4>
        </div>
        <?php include __DIR__ . '/navigation_menu.php'; ?>
    </div>
</div>
