<?php

// Identidad y navegación del panel (ISSUE-107): el menú se genera desde aquí,
// nunca hardcodeado en componentes; cada entrada exige un permiso real del
// servidor y solo aparece si su ruta existe (las rutas crecen con TQ-004/EP-25).
//
// i18n (TQ-007, ISSUE-118): los labels de nav y roles son CLAVES de
// lang/es/ui.php (dominios ui.nav / ui.roles), no texto. El config se cachea
// en producción (config:cache serializa este array): si aquí se guardara el
// resultado de __() la traducción quedaría congelada al momento del cacheo y
// el arranque cargaría el traductor antes de tiempo. Las vistas resuelven la
// clave con __() en cada render.

return [
    'brand' => [
        'name' => env('APP_NAME', 'TDChronos'),
        // Logo opcional (ruta pública); sin él, la marca usa el monograma del tema.
        'logo' => env('TC_LOGO'),
        'season' => env('TC_TEMPORADA', '2026'),
    ],

    // Claves de etiqueta de rol para la UI (SRS §2): se pintan con
    // __(config('tc-ui.roles.…')) — ver profile/show y lang/es/ui.php.
    'roles' => [
        'superadmin' => 'ui.roles.superadmin',
        'admin' => 'ui.roles.admin',
        'organizer' => 'ui.roles.organizer',
        'official' => 'ui.roles.official',
        'timekeeper' => 'ui.roles.timekeeper',
        'athlete' => 'ui.roles.athlete',
    ],

    // Grupos del menú lateral. `permission` null = cualquier autenticado.
    // `route` debe existir para que la entrada se pinte (sin rutas fantasma).
    // `event` true = ruta por evento (ISSUE-115): enlaza con el ULID activo
    // en sesión (tc.event_ulid, fijado por el hub) o cae al listado events.index.
    'nav' => [
        [
            'label' => 'ui.nav.main',
            'items' => [
                ['label' => 'ui.nav.console', 'route' => 'panel.home', 'permission' => null, 'icon' => 'gauge'],
                ['label' => 'ui.nav.profile', 'route' => 'profile', 'permission' => null, 'icon' => 'user-round'],
            ],
        ],
        [
            'label' => 'ui.nav.directory',
            'items' => [
                ['label' => 'ui.nav.events', 'route' => 'events.index', 'permission' => 'events.view', 'icon' => 'calendar-days'],
                ['label' => 'ui.nav.persons', 'route' => 'directory.persons', 'permission' => 'directory.view', 'icon' => 'users'],
                ['label' => 'ui.nav.organizations', 'route' => 'directory.organizations', 'permission' => 'directory.view', 'icon' => 'building-2'],
                ['label' => 'ui.nav.teams', 'route' => 'directory.teams', 'permission' => 'directory.view', 'icon' => 'users-round'],
                ['label' => 'ui.nav.championships', 'route' => 'championships.index', 'permission' => 'championships.view', 'icon' => 'trophy'],
            ],
        ],
        [
            'label' => 'ui.nav.operation',
            'items' => [
                ['label' => 'ui.nav.entries', 'route' => 'events.entries', 'permission' => 'entries.view', 'icon' => 'clipboard-list', 'event' => true],
                ['label' => 'ui.nav.start', 'route' => 'events.start', 'permission' => 'start.manage', 'icon' => 'flag', 'event' => true],
                ['label' => 'ui.nav.marks', 'route' => 'events.marks', 'permission' => 'marks.view', 'icon' => 'timer', 'event' => true],
                ['label' => 'ui.nav.results', 'route' => 'events.results', 'permission' => 'results.view', 'icon' => 'medal', 'event' => true],
            ],
        ],
    ],
];
