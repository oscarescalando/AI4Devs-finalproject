<?php

declare(strict_types=1);

namespace App\Livewire\Directory;

use App\Actions\Directory\Person\CreatePerson;
use App\Actions\Directory\Person\DeletePerson;
use App\Actions\Directory\Person\FindPerson;
use App\Actions\Directory\Person\ListPersons;
use App\Actions\Directory\Person\UpdatePerson;
use App\Support\FormatDate;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Directorio de personas (ISSUE-161, TQ-028): sustituye al placeholder Volt.
 * CRUD con acciones = CLASE Livewire (convención del proyecto); el
 * componente no toca la base ni importa modelos: lee y escribe vía Actions
 * de dominio (ADR-011/ADR-012, custodiado por Pest Arch).
 *
 * PII (RNF-13): en la lista el documento SIEMPRE sale enmascarado
 * (MaskDocument: «V-1••••4») y la fecha de nacimiento no sale — solo la edad
 * calculada. El documento completo solo vive en el modal de edición, al que
 * se llega con directory.view (ruta) + directory.write (acción).
 *
 * Validación (columnas del esquema 000001): documento V-/E- + 6-8 dígitos
 * único global, nombres requeridos, nacimiento pasado y coherente (edad
 * 5-120), género M/F/X. El borrado lo bloquea el Action cuando la persona
 * tiene inscripciones o cuenta de acceso.
 *
 * Autorización: la ruta exige `directory.view` (ISSUE-159) y cada acción
 * que muta comprueba la habilidad `directory.write` (convención en
 * AppServiceProvider) — la UI oculta, el servidor decide.
 */
#[Layout('components.layouts.panel')]
#[Title('Personas')]
final class PersonManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /** Géneros del esquema (char(1): M/F/X) para el selector del modal. */
    public const GENDERS = ['M', 'F', 'X'];

    #[Url(as: 'q', except: '')]
    public string $q = '';

    /** @var array{document: string, first_name: string, last_name: string, birthdate: string, gender: string} */
    public array $form = ['document' => '', 'first_name' => '', 'last_name' => '', 'birthdate' => '', 'gender' => ''];

    public ?string $editingUlid = null;

    public function updatedQ(): void
    {
        // Cambiar la búsqueda devuelve al primer bloque de resultados.
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('open-person-modal');
    }

    public function startEdit(string $ulid): void
    {
        $person = app(FindPerson::class)($ulid);

        if ($person === null) {
            $this->dispatch('toast', type: 'error', message: __('ui.directory.persons.toast_missing'));

            return;
        }

        $this->editingUlid = $person->ulid;
        $this->form = [
            'document' => $person->document,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'birthdate' => FormatDate::iso($person->birthdate),
            'gender' => $person->gender,
        ];

        $this->resetValidation();
        $this->dispatch('open-person-modal');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-person-modal');
    }

    public function save(): void
    {
        // La UI oculta el modal sin permiso de escritura; el servidor
        // autoriza igual (ISSUE-109).
        $this->authorize('directory.write');

        $creating = $this->editingUlid === null;

        try {
            if ($creating) {
                app(CreatePerson::class)($this->form);
            } else {
                app(UpdatePerson::class)($this->editingUlid, $this->form);
            }
        } catch (ValidationException $e) {
            // El error del servidor viaja al campo del formulario: es la
            // única fuente de verdad.
            throw ValidationException::withMessages($this->prefixFormKeys($e->errors()));
        }

        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-person-modal');
        $this->dispatch(
            'toast',
            type: 'success',
            message: $creating ? __('ui.directory.persons.toast_created') : __('ui.directory.persons.toast_updated'),
        );
    }

    public function deletePerson(string $ulid): void
    {
        $this->authorize('directory.write');

        try {
            app(DeletePerson::class)($ulid);
        } catch (ValidationException $e) {
            // Borrado bloqueado por inscripciones o cuenta: error de
            // negocio en toast, la fila sigue en la tabla.
            $this->dispatch(
                'toast',
                type: 'error',
                title: __('ui.directory.persons.toast_delete_failed'),
                message: (string) collect($e->errors())->flatten()->first(),
            );

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.directory.persons.toast_deleted'));
    }

    public function render(): View
    {
        return view('livewire.directory.person-manager', [
            'persons' => app(ListPersons::class)(['q' => $this->q]),
            'genders' => self::GENDERS,
            'headers' => [
                ['label' => __('ui.directory.persons.header_document'), 'class' => 'mono'],
                ['label' => __('ui.directory.persons.header_name')],
                ['label' => __('ui.directory.persons.header_gender')],
                ['label' => __('ui.directory.persons.header_age'), 'align' => 'right', 'class' => 'mono'],
                ['label' => __('ui.directory.persons.header_actions'), 'align' => 'right'],
            ],
        ]);
    }

    private function resetForm(): void
    {
        $this->form = ['document' => '', 'first_name' => '', 'last_name' => '', 'birthdate' => '', 'gender' => ''];
        $this->editingUlid = null;
    }

    /**
     * Mueve las claves de error de la Action (document, birthdate…) al
     * camino del formulario Livewire (form.*) para que el kit las pinte
     * bajo el campo.
     *
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private function prefixFormKeys(array $errors): array
    {
        $prefixed = [];

        foreach ($errors as $key => $messages) {
            $prefixed['form.'.$key] = $messages;
        }

        return $prefixed;
    }
}
