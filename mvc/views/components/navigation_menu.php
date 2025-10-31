<?php
// Componente de menu de navegação baseado em permissões
$navigationMenu = $GLOBALS['navigationMenu'] ?? [];
$currentRoute = $_GET['view'] ?? 'dashboard';

// Debug temporário
if (empty($navigationMenu)) {
    error_log("Navigation menu is empty. User type: " . ($_SESSION['user_type'] ?? 'not set'));
    error_log("Global navigationMenu: " . print_r($GLOBALS['navigationMenu'] ?? 'not set', true));
    error_log("Permissions for cozinha: " . print_r(\System\Auth::getUserPermissions('cozinha'), true));
} else {
    error_log("Navigation menu has " . count($navigationMenu) . " items for user type: " . ($_SESSION['user_type'] ?? 'not set'));
    error_log("Navigation menu items: " . implode(', ', array_keys($navigationMenu)));
}
?>

<nav class="nav flex-column">
    <?php foreach ($navigationMenu as $key => $menuItem): ?>
        <?php
        // Determinar se é o item ativo
        $isActive = ($currentRoute === $key || 
                    ($key === 'novo_pedido' && $currentRoute === 'gerar_pedido') ||
                    ($key === 'produtos' && $currentRoute === 'gerenciar_produtos'));
        $activeClass = $isActive ? 'active' : '';
        ?>
        
        <a class="nav-link <?php echo $activeClass; ?>" 
           href="<?php echo $menuItem['url']; ?>" 
           data-tooltip="<?php echo $menuItem['label']; ?>">
            <i class="<?php echo $menuItem['icon']; ?>"></i>
            <span><?php echo $menuItem['label']; ?></span>
        </a>
    <?php endforeach; ?>
</nav>
