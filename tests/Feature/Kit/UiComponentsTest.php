<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

/**
 * ISSUE-110/TQ-003 — Kit de componentes x-ui.*: contrato de render de cada
 * pieza transversal del panel (spec/09-ui-estilo.md §5). La interacción
 * (modales, toasts, doble envío) se ejercita en la demo Organizaciones.
 */
uses(RefreshDatabase::class);

/**
 * Blade::render de plantillas EN CADENA con <x-slot:...> deja un buffer de
 * salida abierto la primera vez que compila esa cadena: se restaura al nivel
 * base para que PHPUnit no marque la prueba como riesgosa.
 */
function kitRender(string $template, array $data = []): string
{
    $level = ob_get_level();
    $html = Blade::render($template, $data);
    while (ob_get_level() > $level) {
        ob_end_clean();
    }

    return $html;
}

/*
|--------------------------------------------------------------------------
| x-ui.button
|--------------------------------------------------------------------------
*/

it('button renderiza variante, icono y botón por defecto', function () {
    $html = kitRender('<x-ui.button variant="primary" icon="plus">Crear</x-ui.button>');

    expect($html)
        ->toContain('btn btn-primary')
        ->toContain('type="button"')
        ->toContain('data-lucide="plus"')
        ->toContain('Crear');
});

it('button con href renderiza un enlace', function () {
    $html = kitRender('<x-ui.button variant="ghost" href="/destino">Ver</x-ui.button>');

    expect($html)
        ->toContain('<a')
        ->toContain('href="/destino"')
        ->toContain('btn-ghost');
});

it('button size sm añade la clase compacta', function () {
    $html = kitRender('<x-ui.button size="sm">Filtrar</x-ui.button>');

    expect($html)->toContain('btn-sm');
});

it('button ignora variantes desconocidas y cae a secondary', function () {
    $html = kitRender('<x-ui.button variant="neón">X</x-ui.button>');

    expect($html)
        ->toContain('btn btn-secondary')
        ->not->toContain('btn-neón');
});

/*
|--------------------------------------------------------------------------
| x-ui.icon-button
|--------------------------------------------------------------------------
*/

it('icon-button exige icono y expone el tip accesible', function () {
    $html = kitRender('<x-ui.icon-button icon="pencil" tip="Editar" />');

    expect($html)
        ->toContain('icon-btn')
        ->toContain('data-lucide="pencil"')
        ->toContain('title="Editar"')
        ->toContain('aria-label="Editar"');
});

it('icon-button sin icono lanza excepción', function () {
    $level = ob_get_level();

    try {
        Blade::render('<x-ui.icon-button tip="Sin icono" />');
        $this->fail('Debió fallar el render por falta de icono.');
    } catch (ViewException $e) {
        // Blade puede anidar la excepción original: se recorre la cadena.
        $previous = $e;
        while ($previous !== null && ! $previous instanceof InvalidArgumentException) {
            $previous = $previous->getPrevious();
        }

        expect($previous)->toBeInstanceOf(InvalidArgumentException::class)
            ->and($e->getMessage())->toContain('exige la prop $icon');
    } finally {
        // El render abortado deja buffers abiertos: solo se bajan los abiertos aquí.
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }
});

it('icon-button acepta color de peligro para destructivas', function () {
    $html = kitRender('<x-ui.icon-button icon="trash-2" tip="Eliminar" color="danger" />');

    expect($html)->toContain('icon-btn icon-btn-danger');
});

/*
|--------------------------------------------------------------------------
| x-ui.form-group
|--------------------------------------------------------------------------
*/

it('form-group asocia label, marca obligatorio y pinta el error del servidor', function () {
    $html = kitRender(
        '<x-ui.form-group label="Nombre" for="org-name" required help="Ayuda" :error="$error"><input id="org-name"></x-ui.form-group>',
        ['error' => 'El campo nombre es obligatorio.'],
    );

    expect($html)
        ->toContain('for="org-name"')
        ->toContain('data-field="org-name"')
        ->toContain('form-label')
        ->toContain('class="req"')
        ->toContain('form-error')
        ->toContain('role="alert"')
        ->toContain('El campo nombre es obligatorio.')
        // la ayuda cede ante el error, nunca se pintan ambas
        ->not->toContain('form-help');
});

it('form-group muestra la ayuda cuando no hay error', function () {
    $html = kitRender(
        '<x-ui.form-group label="Nombre" for="org-name" help="Hasta 150 caracteres."><input id="org-name"></x-ui.form-group>',
    );

    expect($html)
        ->toContain('form-help')
        ->toContain('Hasta 150 caracteres.')
        ->not->toContain('form-error');
});

/*
|--------------------------------------------------------------------------
| x-ui.table
|--------------------------------------------------------------------------
*/

it('table acepta cabeceras como texto plano', function () {
    $html = kitRender('<x-ui.table :headers=\'["Nombre", "Región"]\'><tr><td>x</td></tr></x-ui.table>');

    expect($html)
        ->toContain('table-wrap')
        ->toContain('<th')
        ->toContain('Nombre')
        ->toContain('Región');
});

it('table alinea a la derecha la columna declarada', function () {
    $html = kitRender(
        '<x-ui.table :headers=\'[["label" => "Total", "align" => "right"]]\'><tr><td class="td-right">1</td></tr></x-ui.table>',
    );

    expect($html)
        ->toContain('Total')
        ->toContain('td-right');
});

it('table marca la columna ordenable con enlace e icono', function () {
    $html = kitRender(
        '<x-ui.table :headers=\'[["label" => "Nombre", "href" => "/?sort=name", "icon" => "arrow-up"]]\'><tr><td>x</td></tr></x-ui.table>',
    );

    expect($html)
        ->toContain('href="/?sort=name"')
        ->toContain('th-sort')
        ->toContain('data-lucide="arrow-up"');
});

it('table soporta modo compacto y pie de tabla', function () {
    $html = kitRender(
        '<x-ui.table :headers=\'["A"]\' compact><tr><td>1</td></tr><x-slot:footer>paginación</x-slot:footer></x-ui.table>',
    );

    expect($html)
        ->toContain('table-compact')
        ->toContain('table-hover')
        ->toContain('table-footer')
        ->toContain('paginación');
});

/*
|--------------------------------------------------------------------------
| x-ui.badge
|--------------------------------------------------------------------------
*/

it('badge pinta cada variante con punto', function (string $variant) {
    $html = kitRender('<x-ui.badge :variant="$variant" dot>Activo</x-ui.badge>', ['variant' => $variant]);

    expect($html)
        ->toContain('badge-'.$variant)
        ->toContain('badge-dot')
        ->toContain('Activo');
})->with(['primary', 'success', 'danger', 'warning', 'info', 'neutral']);

it('badge expone el icono opcional', function () {
    $html = kitRender('<x-ui.badge variant="info" icon="copy">Duplicado</x-ui.badge>');

    expect($html)->toContain('data-lucide="copy"');
});

/*
|--------------------------------------------------------------------------
| x-ui.alert
|--------------------------------------------------------------------------
*/

it('alert cerrable incluye Alpine y botón accesible', function () {
    $html = kitRender('<x-ui.alert type="warning" title="Atención" dismissible>Revisa los campos.</x-ui.alert>');

    expect($html)
        ->toContain('alert-warning')
        ->toContain('x-data')
        ->toContain('x-show="visible"')
        ->toContain('alert-close')
        ->toContain('aria-label="Cerrar"')
        ->toContain('Atención')
        ->toContain('role="alert"');
});

it('alert de éxito usa rol status', function () {
    $html = kitRender('<x-ui.alert type="success">Guardado.</x-ui.alert>');

    expect($html)
        ->toContain('alert-success')
        ->toContain('role="status"');
});

it('alert re-renderiza idéntico: el cierre es estado de cliente, no de servidor', function () {
    // ISSUE-116a: dos renders consecutivos del mismo markup producen el
    // mismo HTML — el dismissible no depende de estado del servidor, así
    // un re-render (p. ej. tras una acción Livewire) no "revive" el aviso
    // cerrado porque Alpine mantiene `visible` en el cliente.
    $template = '<x-ui.alert type="warning" title="Atención" dismissible>Revisa los campos.</x-ui.alert>';

    expect(kitRender($template))->toBe(kitRender($template));
});

it('alert sin dismissible no ofrece botón de cierre', function () {
    $html = kitRender('<x-ui.alert type="info">Informativo.</x-ui.alert>');

    expect($html)
        ->not->toContain('alert-close')
        ->not->toContain('aria-label="Cerrar"')
        ->toContain('Informativo.');
});

it('alert cae a info ante un tipo desconocido', function () {
    $html = kitRender('<x-ui.alert type="critico">Algo.</x-ui.alert>');

    expect($html)
        ->toContain('alert-info')
        ->not->toContain('alert-critico');
});

/*
|--------------------------------------------------------------------------
| x-ui.modal
|--------------------------------------------------------------------------
*/

it('modal monta el diálogo Alpine con la expresión show', function () {
    $html = kitRender(
        '<x-ui.modal show="orgModal" title="Nueva organización" size="lg"><p>cuerpo</p><x-slot:footer>pie</x-slot:footer></x-ui.modal>',
    );

    expect($html)
        ->toContain('x-show="orgModal"')
        ->toContain('@keydown.escape.window="orgModal = false"')
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('modal-dialog modal-lg')
        ->toContain('Nueva organización')
        ->toContain('cuerpo')
        ->toContain('modal-footer')
        ->toContain('pie');
});

it('modal cierra también con clic en el fondo y foca el diálogo', function () {
    $html = kitRender('<x-ui.modal show="abrir">cuerpo</x-ui.modal>');

    expect($html)
        ->toContain('@click.self="abrir = false"')
        ->toContain('tabindex="-1"')
        ->toContain('x-effect');
});

/*
|--------------------------------------------------------------------------
| x-ui.empty-state · x-ui.card · x-ui.kpi · x-ui.page-header
|--------------------------------------------------------------------------
*/

it('empty-state muestra icono, textos y el CTA del slot', function () {
    $html = kitRender(
        '<x-ui.empty-state icon="building-2" title="Vacío" text="Sin filas."><button>Crear</button></x-ui.empty-state>',
    );

    expect($html)
        ->toContain('empty-state')
        ->toContain('data-lucide="building-2"')
        ->toContain('empty-title')
        ->toContain('Vacío')
        ->toContain('Sin filas.')
        ->toContain('Crear');
});

it('empty-state admite CTA en el slot sin título ni texto', function () {
    // ISSUE-116a: el slot del CTA es opcional e independiente — sin
    // título/texto no quedan párrafos huérfanos y el icono cae al default.
    $html = kitRender(
        '<x-ui.empty-state><a href="/crear" class="btn btn-primary">Crear organización</a></x-ui.empty-state>',
    );

    expect($html)
        ->toContain('data-lucide="inbox"')
        ->not->toContain('empty-title')
        ->not->toContain('empty-text')
        ->toContain('href="/crear"')
        ->toContain('Crear organización');
});

it('card renderiza cabecera, acciones, cuerpo y pie', function () {
    $html = kitRender(
        '<x-ui.card title="Título" subtitle="Sub"><x-slot:actions>acción</x-slot:actions>cuerpo<x-slot:footer>pie</x-slot:footer></x-ui.card>',
    );

    expect($html)
        ->toContain('card-title')
        ->toContain('Título')
        ->toContain('Sub')
        ->toContain('acción')
        ->toContain('card-body')
        ->toContain('card-footer')
        ->toContain('pie');
});

it('card puede prescindir del padding del cuerpo', function () {
    $html = kitRender('<x-ui.card padding="none">tabla</x-ui.card>');

    expect($html)->toContain('card-body--flush');
});

it('kpi muestra la métrica tabular con delta y nota', function () {
    $html = kitRender('<x-ui.kpi label="Inscritos" value="114" icon="users" delta="+12" trend="up" note="vs. anterior" />');

    expect($html)
        ->toContain('kpi-label')
        ->toContain('Inscritos')
        ->toContain('kpi-value')
        ->toContain('114')
        ->toContain('kpi-up')
        ->toContain('+12')
        ->toContain('kpi-note')
        ->toContain('data-lucide="users"');
});

it('kpi colorea la tendencia a la baja', function () {
    $html = kitRender('<x-ui.kpi label="Abandonos" value="3" delta="-2" trend="down" />');

    expect($html)->toContain('kpi-down');
});

it('page-header junta título, subtítulo y acciones', function () {
    $html = kitRender(
        '<x-ui.page-header title="Organizaciones" subtitle="Directorio"><x-slot:actions><button>Nueva</button></x-slot:actions></x-ui.page-header>',
    );

    expect($html)
        ->toContain('page-title')
        ->toContain('Organizaciones')
        ->toContain('page-subtitle')
        ->toContain('page-actions')
        ->toContain('Nueva');
});

/*
|--------------------------------------------------------------------------
| Paginación del kit (links de Livewire)
|--------------------------------------------------------------------------
*/

it('la paginación del kit usa los links de Livewire y resume en español', function () {
    $paginator = new LengthAwarePaginator(range(1, 10), 11, 10, 1, ['path' => '/directorio']);

    $html = $paginator->links('components.ui.pagination')->render();

    expect($html)
        ->toContain('wire:click="nextPage(\'page\')"')
        ->toContain('wire:click="gotoPage(2, \'page\')"')
        ->toContain('1–10 de 11')
        ->toContain('aria-label="Página siguiente"');
});
