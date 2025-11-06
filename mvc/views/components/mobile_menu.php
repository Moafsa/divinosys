<?php
// Componente Mobile Menu - Reutilizável para todas as páginas
$currentRoute = $_GET['view'] ?? 'dashboard';
$tenantName = $tenant['nome'] ?? 'Divino Lanches';
?>

<!-- Mobile Menu Button -->
<div id="mobile-menu-btn" style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: #6f42c1; color: white; border: none; border-radius: 50%; width: 50px; height: 50px; display: none; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.3);">
    ☰
</div>

<!-- Mobile Menu Spacer - Para dar espaço ao conteúdo quando o botão estiver visível -->
<div id="mobile-menu-spacer" style="height: 0px; transition: height 0.3s ease;"></div>

<!-- Mobile Menu Overlay -->
<div id="mobile-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998; display: none;"></div>

<!-- Mobile Menu -->
<div id="mobile-menu" style="position: fixed; top: 0; left: -250px; width: 250px; height: 100vh; background: linear-gradient(135deg, #6f42c1, #6c757d); z-index: 9999; transition: left 0.3s ease; padding: 20px; overflow-y: auto; box-shadow: 2px 0 20px rgba(0,0,0,0.3);">
    <!-- Header -->
    <div style="color: white; font-size: 18px; font-weight: bold; margin-bottom: 30px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 15px;">
        <i class="fas fa-utensils" style="margin-right: 10px;"></i>
        <?php echo $tenantName; ?>
    </div>
    
    <!-- Navigation Menu -->
    <div style="color: white;">
        <!-- Dashboard -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=dashboard'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'dashboard') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-tachometer-alt" style="margin-right: 12px; width: 20px;"></i> Dashboard
        </div>
        
        <!-- Pedidos -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=pedidos'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'pedidos') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-list" style="margin-right: 12px; width: 20px;"></i> Pedidos
        </div>
        
        <!-- Delivery -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=delivery'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'delivery') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-motorcycle" style="margin-right: 12px; width: 20px;"></i> Delivery
        </div>
        
        <!-- Estoque -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=estoque'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'estoque') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-boxes" style="margin-right: 12px; width: 20px;"></i> Estoque
        </div>
        
        <!-- Financeiro -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=financeiro'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'financeiro') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-chart-bar" style="margin-right: 12px; width: 20px;"></i> Financeiro
        </div>
        
        <!-- Relatórios -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=relatorios'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'relatorios') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-chart-line" style="margin-right: 12px; width: 20px;"></i> Relatórios
        </div>
        
        <!-- Clientes -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=clientes'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'clientes') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-users" style="margin-right: 12px; width: 20px;"></i> Clientes
        </div>
        
        <!-- Produtos -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=gerenciar_produtos'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'gerenciar_produtos') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-hamburger" style="margin-right: 12px; width: 20px;"></i> Produtos
        </div>
        
        
        <!-- Configurações -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s;" 
             onclick="window.location.href='?view=configuracoes'" 
             onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
             onmouseout="this.style.background='transparent'"
             <?php echo ($currentRoute === 'configuracoes') ? 'style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.2);"' : ''; ?>>
            <i class="fas fa-cog" style="margin-right: 12px; width: 20px;"></i> Configurações
        </div>
        
        <!-- Divider -->
        <div style="border-top: 1px solid rgba(255,255,255,0.2); margin: 20px 0;"></div>
        
        <!-- Logout -->
        <div style="margin: 15px 0; padding: 12px; border-radius: 8px; cursor: pointer; transition: background 0.3s; background: rgba(220, 53, 69, 0.2);" 
             onclick="if(confirm('Deseja realmente sair?')) { window.location.href='logout.php'; }" 
             onmouseover="this.style.background='rgba(220, 53, 69, 0.3)'" 
             onmouseout="this.style.background='rgba(220, 53, 69, 0.2)'">
            <i class="fas fa-sign-out-alt" style="margin-right: 12px; width: 20px;"></i> Sair
        </div>
    </div>
</div>

<script>
    // Mobile Menu JavaScript - Reutilizável
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        const overlay = document.getElementById('mobile-overlay');
        
        if (menu && overlay) {
            if (menu.style.left === '0px') {
                // Close menu
                menu.style.left = '-250px';
                overlay.style.display = 'none';
            } else {
                // Open menu
                menu.style.left = '0px';
                overlay.style.display = 'block';
            }
        }
    }
    
    function closeMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        const overlay = document.getElementById('mobile-overlay');
        if (menu && overlay) {
            menu.style.left = '-250px';
            overlay.style.display = 'none';
        }
    }
    
    function checkScreenSize() {
        const btn = document.getElementById('mobile-menu-btn');
        const spacer = document.getElementById('mobile-menu-spacer');
        
        if (window.innerWidth <= 768) {
            btn.style.display = 'flex';
            btn.style.right = '20px';
            btn.style.left = 'auto';
            // Adicionar espaçamento no topo para o botão
            if (spacer) {
                spacer.style.height = '80px';
            }
        } else {
            btn.style.display = 'none';
            // Remover espaçamento em desktop
            if (spacer) {
                spacer.style.height = '0px';
            }
            closeMobileMenu();
        }
    }
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('mobile-menu-btn');
        const overlay = document.getElementById('mobile-overlay');
        
        if (btn) {
            btn.addEventListener('click', toggleMobileMenu);
        }
        
        if (overlay) {
            overlay.addEventListener('click', closeMobileMenu);
        }
        
        checkScreenSize();
        window.addEventListener('resize', checkScreenSize);
    });
</script>

<!-- AI Chat Widget - Available on all pages -->
<?php 
// Only include AI chat widget on authenticated pages (not on login/register)
$publicPages = ['login', 'register', 'login_admin', 'onboarding', 'subscription_expired'];
$currentView = $_GET['view'] ?? 'dashboard';

if (!in_array($currentView, $publicPages)) {
    include __DIR__ . '/AIChatWidget.php';
}
?>