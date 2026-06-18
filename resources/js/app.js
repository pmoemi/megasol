import './bootstrap';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

document.addEventListener('livewire:init', () => {
    window.Alpine.plugin(focus);
    window.Alpine.plugin(collapse);
});

function appLayout() {
    return {
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('sidebar-collapsed') === 'true',
        theme: localStorage.getItem('theme') || 'dark',
        init() {
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            document.documentElement.setAttribute('data-theme', this.theme);
        },
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebar-collapsed', this.sidebarCollapsed);
            document.documentElement.setAttribute('data-sidebar-collapsed', this.sidebarCollapsed ? 'true' : 'false');
        },
        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', this.theme);
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            document.documentElement.setAttribute('data-theme', this.theme);
        },
    };
}

window.appLayout = appLayout;
