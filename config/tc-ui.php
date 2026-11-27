<?php

// Identidad y navegación del panel (ISSUE-107): el menú se genera desde aquí,
// nunca hardcodeado en componentes; cada entrada exige un permiso real del
// servidor y solo aparece si su ruta existe (las rutas crecen con TQ-004/EP-25).

return [
    'brand' => [
        'name' => env('APP_NAME', 'TDChronos'),
        // Logo opcional (ruta pública); sin él, la marca usa el monograma del tema.
        'logo' => env('TC_LOGO'),
        'season' => env('TC_TEMPORADA', '2026'),
    ],

    // Etiquetas de rol para la UI (SRS §2).
    'roles' => [
        'superadmin' => 'Superadmin',
        'admin' => 'Administrador',
        'organizer' => 'Organizador',
        'official' => 'Juez',
        'timekeeper' => 'Cronometrador',
        'athlete' => 'Atleta',
    ],

    // Grupos del menú lateral. `permission` null = cualquier autenticado.
    // `route` debe existir para que la entrada se pinte (sin rutas fantasma).
    'nav' => [
        [
            'label' => 'Principal',
            'items' => [
                ['label' => 'Consola', 'route' => 'panel.home', 'permission' => null, 'icon' => 'gauge'],
            ],
        ],
        [
            'label' => 'Directorio',
            'items' => [
                ['label' => 'Eventos', 'route' => 'events.index', 'permission' => 'events.view', 'icon' => 'calendar-days'],
                ['label' => 'Personas', 'route' => 'directory.persons', 'permission' => 'directory.view', 'icon' => 'users'],
                ['label' => 'Organizaciones', 'route' => 'directory.organizations', 'permission' => 'directory.view', 'icon' => 'building-2'],
                ['label' => 'Equipos', 'route' => 'directory.teams', 'permission' => 'directory.view', 'icon' => 'users-round'],
                ['label' => 'Liga', 'route' => 'championships.index', 'permission' => 'championships.view', 'icon' => 'trophy'],
            ],
        ],
        [
            'label' => 'Operación',
            'items' => [
                ['label' => 'Inscripciones', 'route' => 'events.entries', 'permission' => 'entries.view', 'icon' => 'clipboard-list'],
                ['label' => 'Salidas', 'route' => 'events.start', 'permission' => 'start.manage', 'icon' => 'flag'],
                ['label' => 'Registros', 'route' => 'events.marks', 'permission' => 'marks.view', 'icon' => 'timer'],
                ['label' => 'Clasificación', 'route' => 'events.results', 'permission' => 'results.view', 'icon' => 'medal'],
            ],
        ],
    ],
];
