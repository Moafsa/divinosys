<?php
// Componente de menu de navegação baseado em permissões
$navigationMenu = $GLOBALS['navigationMenu'] ?? [];
$currentRoute = $_GET['view'] ?? 'dashboard';
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
    
    <hr class="text-white-50">
    
    <a class="nav-link" href="<?php echo $router->url('logout'); ?>" data-tooltip="Sair">
        <i class="fas fa-sign-out-alt"></i>
        <span>Sair</span>
    </a>
</nav>
