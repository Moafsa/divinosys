// Sidebar Recolhível - JavaScript Compartilhado

// Sidebar functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebar.classList.contains('collapsed')) {
        sidebar.classList.remove('collapsed');
        if (mainContent) mainContent.classList.remove('expanded');
        localStorage.setItem('sidebarCollapsed', 'false');
    } else {
        sidebar.classList.add('collapsed');
        if (mainContent) mainContent.classList.add('expanded');
        localStorage.setItem('sidebarCollapsed', 'true');
    }
}

// Mobile sidebar functionality
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('show');
        
        // Toggle overlay for mobile
        if (overlay) {
            overlay.classList.toggle('show');
        }
    }
}


// Initialize sidebar state from localStorage
function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (!sidebar) return;
    
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        if (mainContent) mainContent.classList.add('expanded');
    }
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.querySelector('.sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (window.innerWidth <= 768 && sidebar && toggleButton) {
        if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
            sidebar.classList.remove('show');
            if (overlay) {
                overlay.classList.remove('show');
            }
        }
    }
    
    // Close sidebar when clicking on overlay
    if (overlay && event.target === overlay) {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove('show');
        if (overlay) {
            overlay.classList.remove('show');
        }
    }
});

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
    
    // Ensure mobile button is centered in header
    function positionMobileButton() {
        const button = document.getElementById('sidebarToggle');
        if (button && window.innerWidth <= 768) {
            button.style.cssText = `
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: fixed !important;
                top: 20px !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                z-index: 1051 !important;
                background: #6f42c1 !important;
                color: white !important;
                border: none !important;
                border-radius: 50% !important;
                width: 40px !important;
                height: 40px !important;
                align-items: center !important;
                justify-content: center !important;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2) !important;
                cursor: pointer !important;
                font-size: 16px !important;
                margin: 0 !important;
                padding: 0 !important;
            `;
        } else if (button && window.innerWidth > 768) {
            button.style.display = 'none';
        }
    }
    
    // Position button on load and resize
    positionMobileButton();
    window.addEventListener('resize', positionMobileButton);
    
    // Add event listeners for toggle buttons
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (window.innerWidth <= 768) {
                toggleMobileSidebar();
            } else {
                toggleSidebar();
            }
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('show');
            if (overlay) {
                overlay.classList.remove('show');
            }
        }
    });
});
