# 09 — Guía de estilo de la interfaz (Blade/Livewire/Tailwind)

> **Fecha:** 2026-11-26 · **Estado:** vigente · Documento de especificación de TDChronos 2.0.
> Cómo se ve y cómo se construye la UI: principios, tokens, layout, componentes `x-ui.*`, estados,
> iconos y formato de datos. Complementa a [ADR-011](03-arquitectura/ADR-011-ui-tall-livewire.md)
> (stack TALL) y a la DoD de pantalla de [05-dod.md](05-dod.md) §2.4 (G11/G13, P1–P7).
> Arquitectura de tokens y componentes adaptada del sistema de diseño previo del equipo,
> re-expresada con la identidad de TDChronos.

## 1. Principios

1. **Operativo y legible**: jerarquía por peso y tamaño de tipografía, no por color; una sola
   tipografía UI (Instrument Sans) y una monoespaciada para datos de cronometraje.
2. **El color comunica estado**: chrono (azul) = acción y selección; live (ámbar) = novedad,
   resaltado de llegadas, en vivo; los semánticos solo para estado (éxito/alerta/error/info);
   las identidades de dominio (tipo de carrera, estado de marca) tienen color fijo.
3. **Los datos de cronometraje son sagrados**: tiempos, dorsales, posiciones y diferencias
   SIEMPRE en monoespaciada con `font-variant-numeric: tabular-nums` — jamás tipografía
   proporcional (las columnas de una clasificación no pueden bailar). Formato único
   `FormatDuration` (`h:mm:ss`, > 24 h, décimas opcionales).
4. **Una pantalla = un propósito**: el header dice dónde estoy (título + breadcrumb) y el
   sidebar a dónde puedo ir — **solo** lo que el rol puede abrir; lo no permitido no se pinta
   (sin candados) y un grupo vacío desaparece.
5. **La UI oculta, el servidor autoriza** (ADR-011): todo control condicionado por policy
   probada; la ausencia del control es la única pista.
6. **Panel dual, oscuro por defecto** (mismo mecanismo de clase `.dark` persistida, arrancando
   en oscuro salvo preferencia guardada); **las pantallas públicas y kiosco son SIEMPRE
   oscuras** (TV/proyección a la intemperie) y sin PII (INV-06).
7. **Login sin pistas**: la pantalla de acceso no describe roles, flujos ni dominios; los
   placeholders usan ejemplos genéricos.
8. **Flujos por pasos** para operaciones críticas (crear evento, cerrar evento, corregir marca):
   el usuario siempre ve en qué paso está y qué sigue.

## 2. Stack y estructura de archivos

| Capa | Tecnología | Dónde |
|---|---|---|
| Framework UI | Livewire 4 + Volt (pantallas = Volt funcional; CRUD = clase Livewire) | `app/Livewire`, `resources/views/livewire` |
| Estilos | Tailwind CSS 4 (`@tailwindcss/vite`): utilidades desde `@theme` + componentes en CSS plano | `resources/css/app.css`, `resources/css/theme.css` |
| Interactividad ligera | Alpine.js (atributos `x-data`, `x-show`…) | incluido con Livewire |
| Iconos | Lucide (`npm lucide`) renderizados por `data-lucide` vía `<x-ui.icon>` | se instala con TQ-002 |
| Fuentes | Instrument Sans (UI, ya en `@theme`) + mono del sistema para cronometraje | `resources/css/app.css` |
| Menú/branding | Configuración PHP, no componentes fijos | `config/tc-ui.php` (nace con TQ-002) |

```
resources/
├── css/app.css                    # @import tailwind + @theme (tokens) + @import theme.css
├── css/theme.css                  # clases del sistema (.btn, .card, .table, .badge, .sidebar…)
└── views/
    ├── components/layouts/        # panel · guest (login) · public (siempre oscuro)
    ├── components/ui/*.blade.php  # componentes x-ui.* (kit transversal + dominio)
    └── livewire/                  # pantallas Volt y vistas de componentes CRUD
```

Reglas de estructura: CSS de componentes **plano, sin `@apply`**, en `theme.css`; los componentes
Blade son **anónimos** y solo envuelven clases del sistema; cada componente abre con un comentario
de ejemplo de uso; las vistas usan clases del sistema + utilidades Tailwind, nunca `<style>` inline
ni estilos globales que sangren a otras vistas (P5).

## 3. Tokens de marca

Definidos una vez en `@theme` de `app.css` (genera utilidades Tailwind: `bg-primary-600`…,
`text-success-500`…) y duplicados como variables `--tc-*` en `theme.css` para las clases de
componente. **Nunca un hex suelto en una vista o componente: siempre la clave del token.**

| Token | Valores | Uso |
|---|---|---|
| `primary` (chrono) | sky: 50 `#F0F9FF` · 100 `#E0F2FE` · 200 `#BAE6FD` · 300 `#7DD3FC` · 400 `#38BDF8` · 500 `#0EA5E9` · 600 `#0284C7` · 700 `#0369A1` · 800 `#075985` · 900 `#0C4A6E` | Botón primario, enlaces, ítem activo del menú, anillos de foco. Claro usa `600`; oscuro usa `400` |
| `accent` (live) | amber: 50 `#FFFBEB` · 100 `#FEF3C7` · 200 `#FDE68A` · 300 `#FCD34D` · 400 `#FBBF24` · 500 `#F59E0B` · 600 `#D97706` · 700 `#B45309` | Resaltado de llegadas nuevas, punto «En vivo», novedades en proyección |
| `neutral` | slate: 50 `#F8FAFC` · 100 `#F1F5F9` · 200 `#E2E8F0` · 300 `#CBD5E1` · 400 `#94A3B8` · 500 `#64748B` · 600 `#475569` · 700 `#334155` · 800 `#1E293B` · 900 `#0F172A` · 950 `#020617` | Fondos, bordes (`200`/`800`), texto (`900/700/500/400`) |
| `success` | emerald: 50 `#ECFDF5` · 100 `#D1FAE5` · 200 `#A7F3D0` · 400 `#34D399` · 500 `#10B981` · 600 `#059669` · 700 `#047857` | Éxito, marca asignada, resultado oficial, punto activo |
| `danger` | rose: 50 `#FFF1F2` · 100 `#FFE4E6` · 200 `#FECDD3` · 400 `#FB7185` · 500 `#F43F5E` · 600 `#E11D48` · 700 `#BE123C` | Error, destructivas, huérfana crítica |
| `warning` | orange: 50 `#FFF7ED` · 100 `#FFEDD5` · 200 `#FED7AA` · 400 `#FB923C` · 500 `#F97316` · 600 `#EA580C` · 700 `#C2410C` | Alerta, marca huérfana, datos sin confirmar |
| `info` | violet: 50 `#F5F3FF` · 100 `#EDE9FE` · 200 `#DDD6FE` · 400 `#A78BFA` · 500 `#8B5CF6` · 600 `#7C3AED` · 700 `#6D28D9` | Información neutral, duplicado |
| Dominio: `calle` · `trail` · `ocr` | `#0EA5E9` · `#65A30D` · `#7C3AED` | Identidad fija de tipo de carrera |
| `--radius-card` / botones | `12px` / `8px` | Tarjetas y modales / botones e inputs |
| Sombras | `--shadow-card` suave · `--shadow-pop` para menús/modales | — |
| Tipografía | Instrument Sans; base **14px**; mono del sistema (`ui-monospace, SFMono-Regular, Menlo, Consolas`) para cronometraje | `.page-title` 22px/700 · `h2` 20 · `h3` 17 · `.card-title` 15 · `.kpi-label` 12 mayúsculas |

Superficies: claro → fondo `#FFFFFF`, tarjeta `#FFFFFF` con borde `neutral-200`, muted
`neutral-50`. Oscuro (`.dark`) → fondo `neutral-950`, tarjeta `neutral-900`, borde
`neutral-800`, muted `#0B1220` — **equivalente, no invertido**: mismas jerarquías, contraste AA.

Mapeo semántico de dominio (fijo): marca `assigned`→`success`, `orphan`→`warning`,
`duplicate`→`info`; resultado `provisional`→`accent` (borde discontinuo), `oficial`→`success`.

## 4. Layout del panel

```
┌──────────────┬──────────────────────────────────────────────────────────┐
│ logo         │ ☰  Título / breadcrumb        [acciones] ☾ (avatar ▾)    │ ← header sticky 64px
│──────────────┼──────────────────────────────────────────────────────────│
│ PRINCIPAL    │   <main class="content">                                  │
│   Eventos    │       x-ui.page-header (título + subtítulo + acciones)   │
│ DIRECTORIO   │       cards / tablas / formularios                        │
│   Personas   │                                                          │
│   Organiz.   │                                                          │
│   Equipos    │                                                          │
│ EVENTO       │ ← grupo contextual: Inscripciones · Salidas ·            │
│              │   Registros · Clasificación (si hay evento activo)       │
│──────────────│                                                          │
│ ▣ EVT-2026   │ ← pill del evento activo en sesión                       │
└──────────────┴──────────────────────────────────────────────────────────┘
 sidebar 264px (colapsa a 76px; off-canvas < 1024px)
```

- El menú se genera desde `config('tc-ui.nav')`: `route`, `match`, `roles`/permisos, `badge` —
  jamás hardcoded en el componente; branding (nombre, logo, temporada) desde configuración/BD
  (ISSUE-107).
- Estado del sidebar y del tema persisten en `localStorage`; la clase `.dark` se aplica en el
  `<head>` antes de pintar para evitar parpadeo; el panel arranca en oscuro sin preferencia.
- Layouts: `layouts/panel` (autenticado), `layouts/guest` (login, dos bloques, sin pistas),
  `layouts/public` (oscuro fijo, ya existente).

## 5. Componentes `x-ui.*`

Kit transversal (nace en TQ-003; toda pantalla del panel se construye **solo** con el kit):

| Componente | Uso | Props / slots clave |
|---|---|---|
| `x-ui.page-header` | Título + subtítulo + acciones de página | `title`, `subtitle`, slot |
| `x-ui.card` | Contenedor con header/footer opcional | `title`, `subtitle`, slots `actions`, `footer` |
| `x-ui.kpi` | Métrica destacada (dashboard, evento) | `label`, `value`, `icon`, `delta`, `trend`, `note` |
| `x-ui.table` | Tabla con cabecera y pie | `headers` (string o `['label','align','class','href','icon']` ordenable), `compact`, slot `footer` |
| `x-ui.button` | Botón o enlace | `variant` (primary·secondary·outline·soft·ghost·danger·danger-soft), `size`, `icon`, `href` |
| `x-ui.icon-button` | Acción de tabla | `icon`, `tip` (**obligatorio**), `color`, `href` |
| `x-ui.form-group` | Campo con label/ayuda/error | `label`, `for`, `required`, `help`, `error` |
| `x-ui.badge` | Estado / etiqueta | `variant`, `dot`, `icon` |
| `x-ui.alert` | Mensaje en línea | `type`, `title`, `dismissible` |
| `x-ui.modal` | Diálogo Alpine | `show` (expresión), `title`, `size`, slot `footer` |
| `x-ui.empty-state` | Lista vacía con CTA | `icon`, `title`, `text`, slot |
| `x-ui.icon` | Icono Lucide | `name` |
| `x-ui.sidebar` · `x-ui.header` · `x-ui.nav-link` | Partes del shell (TQ-002) | — |

Componentes de dominio (nacen con la pantalla que los necesita, misma convención):

| Componente | Uso |
|---|---|
| `x-ui.time-cell` | Duración desde ms vía `FormatDuration`: mono tabular, décimas opcionales, diferencias negativas |
| `x-ui.bib` | Dorsal: chip mono tabular, nunca proporcional |
| `x-ui.delta` | Diferencia con el líder (`+h:mm:ss`), atenuada salvo top 3 |
| `x-ui.position` | Posición con ordinal (1.º, 2.º, 3.º) y resaltado de top 3 |
| `x-ui.live-indicator` | «En vivo» / «Reconectando…» / «Sin conexión (N pendientes)» — G13, punto `accent` pulsante |
| `x-ui.category-dot` | Punto/chip de categoría con el color del evento |

## 6. Estados (siempre visibles, nunca silencio)

- **Carga**: `wire:loading` acotado al área afectada; `.skeleton` en tablas y KPIs; ninguna
  acción queda con spinner colgado (P4).
- **Éxito/info**: toasts (`tcToast`, dispatch desde Livewire) accesibles (rol `status`, cierre
  automático, español).
- **Destructivas**: confirmación previa SIEMPRE (`tcConfirm` o `wire:confirm`); doble envío
  bloqueado.
- **Error**: interceptor global → toast con id de correlación; 401/419/500 jamás en silencio;
  419 → login conservando destino (P4).
- **Vacío**: `x-ui.empty-state` con CTA accionable, nunca tabla fantasma.
- **Conexión** (G13): `x-ui.live-indicator` en toda pantalla en vivo; la marca nunca falla por
  el broadcast (INV-08).

## 7. Iconos (Lucide)

Acciones de tabla **por icono con tooltip obligatorio**, en el orden fijo:
`eye` ver · `pencil` editar · `copy` duplicar · acción operativa · `trash-2` eliminar.
Convención de dominio: `timer` captura de llegada · `flag` cerrar evento · `rotate-ccw` deshacer
salida · `calendar-clock` reprogramar oleada · `play`/`pause` operación · `download` exportar ·
`key-round` contraseña · `user-x` desactivar.

## 8. Formato de datos

- Duraciones: `FormatDuration` → `h:mm:ss` (soporta > 24 h, décimas opcionales, negativas) —
  única vía, jamás formato ad-hoc en vistas.
- Fechas: `dd/mm/yyyy` en UI, ISO en BD; hora de Caracas en pantalla.
- Números de posición con ordinal (`1.º`); dorsales sin ceros a la izquierda salvo serie del
  evento; todo dato cronometrado en mono tabular (§1.3).

## 9. Accesibilidad y responsive

- **WCAG 2.1 AA** (G11): contraste verificado por par de tokens; foco visible (`--tc-ring`);
  teclado completo; `label` asociado a todo control (`x-ui.form-group`); foco gestionado al
  abrir modales y al validar; `lang="es"`.
- **Responsive 360px → TV** (P5): mobile-first en panel; pantallas públicas escalan a TV/kiosco
  (modo kiosco ya existente); sin estilos globales que sangren a otras vistas.
- `[x-cloak]` oculto por defecto en `app.css`.

## 10. i18n

Todo texto de UI por `lang/` PHP en español (ISSUE-118); sin literales en componentes ni vistas
(chequeo en CI); mensajes de validación en español; revisión ortográfica al cerrar pantalla.

## 11. Pantallas públicas y kiosco

Siempre oscuras (§1.6), sin PII (INV-06: menores con iniciales), sondeo `wire:poll` con contrato
`v`/`seq`, resaltado de llegadas nuevas con `accent` y transición que decae, reloj en vivo mono
tabular, `Cache-Control` corto. El kiosco es la misma pantalla en modo pantalla completa.

## 12. Verificación de la guía

- Prueba/chequeo CI: sin literales de UI; sin hex fuera de `app.css`/`theme.css` (colores por
  clave de token); iconos de tabla con tooltip; `[x-cloak]` presente.
- La guía es **viva**: todo PR que toque UI actualiza este documento en el mismo cambio (DoD G8);
  la galería de componentes (`/ui/componentes`, solo local/testing) llega con TQ-003.
