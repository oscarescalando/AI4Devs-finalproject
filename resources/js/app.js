/*
 * TDChronos — arranque del cliente (spec/09-ui-estilo.md §2).
 * Soporte liviano: iconos Lucide, tema claro/oscuro persistido, toggle del
 * sidebar y estados unificados del kit CRUD (TQ-003): toasts accesibles,
 * confirmación destructiva y foco al primer campo inválido. Nada de estado
 * de sesión ni de dominio en JS (ADR-011).
 */
import { createIcons, icons } from 'lucide';

/* Cliente Echo global sobre Reverb (TQ-017): solo lo instancia — las
 * pantallas se conectan en TQ-018. Sin VITE_REVERB_APP_KEY no arranca y
 * todo sigue por sondeo (degradación O1). */
import './echo.js';


window.tcRenderIcons = (root = document) => {
    if (typeof createIcons !== 'function') {
        return;
    }
    createIcons({ icons, nameAttr: 'data-lucide', root });
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

/* ---------- Puente WS→Livewire de las pantallas vivas (TQ-018, RF-77) ----------
 * PATRÓN ELEGIDO (idéntico en las cinco pantallas, ver comentarios de las
 * vistas): cada Volt declara en su raíz x-data="tcBridge(@js([...]))" con
 * su canal y el mapa {evento WS → evento Livewire}. El puente SOLO
 * retransmite: window.tcEcho (TQ-017) escucha el canal y cada payload se
 * convierte en window.Livewire.dispatch('<tc-*>', { payload }) que el Volt
 * recibe con #[On('tc-*')] y resuelve comparando seq contra la última
 * versión vista (delta simple, ISSUE-141 mínima).
 * Suscripción en el init de Alpine; limpieza en destroy() → leaveChannel
 * cuando el morph elimina el nodo o se navega fuera: sin listeners
 * huérfanos. Sin tcEcho (sin clave VITE_, degradación O1) el puente no
 * hace nada y la pantalla vive del sondeo de respaldo de 15 s. */
window.tcBridge = ({ channel, events = {}, private: isPrivate = true }) => ({
    wireName: null,
    init() {
        if (! window.tcEcho) {
            return;
        }

        this.wireName = isPrivate ? `private-${channel}` : channel;

        const connection = isPrivate
            ? window.tcEcho.private(channel)
            : window.tcEcho.channel(channel);

        Object.entries(events).forEach(([wsEvent, livewireEvent]) => {
            connection.listen(wsEvent, (payload) => {
                window.Livewire?.dispatch(livewireEvent, { payload: payload ?? {} });
            });
        });
    },
    destroy() {
        if (this.wireName) {
            window.tcEcho?.leaveChannel(this.wireName);
            this.wireName = null;
        }
    },
});

/* ---------- Toasts (ISSUE-111): rol status, cierre automático, español ---------- */

const TOAST_ICONS = {
    success: 'check-circle-2',
    danger: 'x-circle',
    warning: 'alert-triangle',
    info: 'info',
};

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

window.tcToast = ({ type = 'info', title = '', message = '', duration = 5000 } = {}) => {
    let region = document.getElementById('tc-toasts');
    if (! region) {
        region = document.createElement('div');
        region.id = 'tc-toasts';
        region.className = 'toast-region';
        region.setAttribute('role', 'status');
        region.setAttribute('aria-live', 'polite');
        document.body.appendChild(region);
    }

    const kind = TOAST_ICONS[type] ? type : 'info';
    const toast = document.createElement('div');
    toast.className = `toast toast-${kind}`;
    toast.innerHTML = `
        <span class="toast-icon"><i data-lucide="${TOAST_ICONS[kind]}"></i></span>
        <span class="min-w-0">
            ${title ? `<span class="toast-title">${escapeHtml(title)}</span>` : ''}
            ${message ? `<span class="toast-message">${escapeHtml(message)}</span>` : ''}
        </span>
        <button type="button" class="toast-close" aria-label="Cerrar">
            <i data-lucide="x"></i>
        </button>`;
    region.appendChild(toast);
    window.tcRenderIcons(toast);

    const dismiss = () => {
        toast.remove();
        if (region.childElementCount === 0) {
            region.remove();
        }
    };
    toast.querySelector('.toast-close')?.addEventListener('click', dismiss);
    if (duration > 0) {
        window.setTimeout(dismiss, duration);
    }

    return toast;
};

/* Livewire puede despachar el toast desde el servidor:
 * $this->dispatch('toast', type: 'success', message: '…'). */
document.addEventListener('toast', (event) => {
    const detail = event.detail ?? {};
    window.tcToast({
        type: detail.type,
        title: detail.title,
        message: detail.message,
        duration: detail.duration,
    });
});

/* ---------- PWA base instalable (ISSUE-114, TQ-006): registro del SW ---------- */

window.tcRegisterSW = () => {
    if (! ('serviceWorker' in navigator)) {
        return;
    }
    // Solo contexto seguro: https o localhost. En file:// (u otro http
    // inseguro) no hay SW — el registro se omite y, si fallara, calla.
    const host = window.location.hostname;
    if (window.location.protocol !== 'https:' && host !== 'localhost' && host !== '127.0.0.1') {
        return;
    }
    navigator.serviceWorker.register('/sw.js').catch(() => {
        /* sin soporte o sin permiso: la app sigue siendo online-normal */
    });
};

/* ---------- Confirmación previa de acciones destructivas (ISSUE-110) ---------- */

window.tcConfirm = ({
    title = 'Confirmar acción',
    message = '',
    confirmText = 'Aceptar',
    cancelText = 'Cancelar',
    danger = false,
} = {}) => new Promise((resolve) => {
    document.getElementById('tc-confirm')?.remove();

    const root = document.createElement('div');
    root.id = 'tc-confirm';
    root.innerHTML = `
        <div class="modal-overlay" role="dialog" aria-modal="true" aria-label="${escapeHtml(title)}">
            <div class="modal-dialog" tabindex="-1" @click.stop>
                <div class="modal-header">
                    <div class="min-w-0">
                        <h2 class="modal-title">${escapeHtml(title)}</h2>
                    </div>
                </div>
                <div class="modal-body">
                    <p class="text-sm">${escapeHtml(message)}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary tc-confirm-cancel">${escapeHtml(cancelText)}</button>
                    <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'} tc-confirm-ok">${escapeHtml(confirmText)}</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(root);
    window.tcRenderIcons(root);

    const done = (answer) => {
        document.removeEventListener('keydown', onKey);
        root.remove();
        resolve(answer);
    };
    const onKey = (event) => {
        if (event.key === 'Escape') {
            done(false);
        }
    };
    document.addEventListener('keydown', onKey);
    root.querySelector('.tc-confirm-ok')?.addEventListener('click', () => done(true));
    root.querySelector('.tc-confirm-cancel')?.addEventListener('click', () => done(false));
    root.querySelector('.modal-overlay')?.addEventListener('click', (event) => {
        if (event.target === event.currentTarget) {
            done(false);
        }
    });
    root.querySelector('.tc-confirm-ok')?.focus();
});

/* ---------- Foco al primer campo inválido tras validar en servidor ---------- */

const focusFirstInvalidField = () => {
    for (const error of document.querySelectorAll('.form-error')) {
        const fieldId = error.closest('.form-group')?.getAttribute('data-field');
        const field = fieldId ? document.getElementById(fieldId) : null;
        if (field && field.offsetParent !== null) {
            field.focus();
            field.setAttribute('aria-invalid', 'true');
            return;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => window.tcRenderIcons());
document.addEventListener('livewire:load', () => window.tcRenderIcons());
document.addEventListener('livewire:morphed', (event) => {
    window.tcRenderIcons(event?.target ?? document);
    // El morph puede reemplazar el icono por un <i data-lucide> sin renderizar
    window.tcRenderIcons();
    // La validación de servidor acaba de pintar errores: foco al primero.
    window.setTimeout(focusFirstInvalidField, 0);
});
