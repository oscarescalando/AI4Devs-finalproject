/*
 * TDChronos — arranque del cliente (spec/09-ui-estilo.md §2).
 * Solo soporte liviano: iconos Lucide, tema claro/oscuro persistido y
 * toggle del sidebar. Nada de estado de sesión ni de dominio en JS (ADR-011).
 */
import { createIcons } from 'lucide';

window.tcRenderIcons = (root = document) => {
    if (typeof createIcons !== 'function') {
        return;
    }
    createIcons({ nameAttr: 'data-lucide' }, root);
};

window.tcToggleTheme = () => {
    const isDark = document.documentElement.classList.toggle('dark');
    try {
        window.localStorage.setItem('tc.theme', isDark ? 'dark' : 'light');
    } catch {
        /* almacenamiento no disponible: la preferencia no persiste */
    }
};

window.tcToggleSidebar = () => {
    document.getElementById('tc-sidebar')?.classList.toggle('is-open');
    document.getElementById('tc-sidebar-backdrop')?.classList.toggle('sidebar-backdrop');
};

document.addEventListener('DOMContentLoaded', () => window.tcRenderIcons());
document.addEventListener('livewire:load', () => window.tcRenderIcons());
document.addEventListener('livewire:morphed', (event) => {
    window.tcRenderIcons(event?.target ?? document);
    // El morph puede reemplazar el icono por un <i data-lucide> sin renderizar
    window.tcRenderIcons();
});
