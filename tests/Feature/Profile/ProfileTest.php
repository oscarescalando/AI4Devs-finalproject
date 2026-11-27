<?php

declare(strict_types=1);

use App\Actions\Auth\UpdateOwnPassword;
use App\Models\Account;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ISSUE-113 (RF-71, TQ-005) — Perfil propio: datos de la cuenta en solo
 * lectura (sin selector de rol ni control de activación), cambio de
 * contraseña exigiendo la actual con errores bajo el campo, campos vacíos
 * tras guardar (nunca el hash) y cierre de las demás sesiones.
 */
uses(RefreshDatabase::class);

function profileAccount(string $role = 'timekeeper', string $password = 'secreta-123'): Account
{
    $account = Account::factory()->create(['password' => Hash::make($password)]);
    $account->assignRole($role);

    return $account;
}

function seedSessionRow(string $id, int $accountId): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $accountId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'prueba',
        'payload' => 'prueba',
        'last_activity' => time(),
    ]);
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Acceso y render (cualquier cuenta autenticada, sin permiso)
|--------------------------------------------------------------------------
*/

it('redirige a los invitados al login', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
});

it('renderiza para cualquier rol autenticado sin selector de rol ni control de activación', function (string $role) {
    $account = profileAccount($role);

    $html = $this->actingAs($account)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee($account->email)
        ->assertSee($account->person->first_name)
        ->assertSee($account->person->last_name)
        ->assertSee(config('tc-ui.roles.'.$role))
        ->getContent();

    // ISSUE-113: la propia cuenta no se autogestiona roles ni estado — no
    // existe ningún control para ello en la pantalla.
    expect($html)
        ->not->toContain('<select')
        ->not->toContain('name="role')
        ->not->toContain('Desactivar')
        ->not->toContain('Activar cuenta');
})->with(['admin', 'timekeeper', 'athlete']);

it('el dropdown del avatar del panel contiene la entrada Perfil', function () {
    $html = $this->actingAs(profileAccount('timekeeper'))
        ->get(route('panel.home'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('href="'.route('profile').'"')
        ->toContain('dd-item')
        ->toContain('Perfil');
});

/*
|--------------------------------------------------------------------------
| Cambio de contraseña (RF-71: la actual se exige en el servidor)
|--------------------------------------------------------------------------
*/

it('rechaza el cambio sin la contraseña actual con error bajo el campo', function () {
    $this->actingAs(profileAccount('admin'));

    Livewire::test('profile.show')
        ->set('password', 'nueva-clave-9')
        ->set('password_confirmation', 'nueva-clave-9')
        ->call('updatePassword')
        ->assertHasErrors(['current_password'])
        ->assertSee('El campo contraseña actual es obligatorio.');
});

it('rechaza la contraseña actual incorrecta con el mismo error y mensaje genérico', function () {
    $account = profileAccount('admin');
    $this->actingAs($account);

    Livewire::test('profile.show')
        ->set('current_password', 'incorrecta-del-todo')
        ->set('password', 'nueva-clave-9')
        ->set('password_confirmation', 'nueva-clave-9')
        ->call('updatePassword')
        ->assertHasErrors(['current_password'])
        ->assertSee('La contraseña actual no es correcta.');

    // La contraseña no cambió.
    expect(Hash::check('secreta-123', $account->fresh()->password))->toBeTrue();
});

it('cambia la contraseña con la actual correcta: hash nuevo, campos vacíos y toast', function () {
    $account = profileAccount('admin');
    $this->actingAs($account);

    $component = Livewire::test('profile.show')
        ->set('current_password', 'secreta-123')
        ->set('password', 'nueva-clave-9')
        ->set('password_confirmation', 'nueva-clave-9')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '')
        ->assertDispatched('toast', type: 'success', message: 'Contraseña actualizada');

    expect(Hash::check('nueva-clave-9', $account->fresh()->password))->toBeTrue()
        // Tras guardar los campos quedan VACÍOS: el hash jamás viaja a la
        // respuesta (ISSUE-113).
        ->and($component->html())->not->toContain($account->fresh()->password);
});

it('rechaza la confirmación distinta y la contraseña corta', function () {
    $this->actingAs(profileAccount('admin'));

    Livewire::test('profile.show')
        ->set('current_password', 'secreta-123')
        ->set('password', 'nueva-clave-9')
        ->set('password_confirmation', 'otra-cosa-00')
        ->call('updatePassword')
        ->assertHasErrors(['password'])
        ->assertSee('El campo contraseña nueva de confirmación no coincide.');

    Livewire::test('profile.show')
        ->set('current_password', 'secreta-123')
        ->set('password', 'corta')
        ->set('password_confirmation', 'corta')
        ->call('updatePassword')
        ->assertHasErrors(['password'])
        ->assertSee('El campo contraseña nueva debe tener al menos 8 caracteres.');
});

/*
|--------------------------------------------------------------------------
| Invalidación de las demás sesiones (ISSUE-113)
|--------------------------------------------------------------------------
*/

it('invalida las demás sesiones al cambiar la contraseña y la propia sigue', function () {
    $account = profileAccount('admin');
    $this->actingAs($account);

    seedSessionRow('sesion-ajena-0000000000000000000000000001', $account->id);

    Livewire::test('profile.show')
        ->set('current_password', 'secreta-123')
        ->set('password', 'nueva-clave-9')
        ->set('password_confirmation', 'nueva-clave-9')
        ->call('updatePassword')
        ->assertHasNoErrors();

    // La sesión ajena desapareció; la cuenta sigue autenticada en la suya.
    expect(DB::table('sessions')->where('id', 'sesion-ajena-0000000000000000000000000001')->exists())->toBeFalse();
    $this->assertAuthenticatedAs($account);

    // La sesión cerrada ya no entra con la vieja; la nueva sí (RF-68).
    Livewire::test('auth.login')
        ->set('login', $account->email)
        ->set('password', 'secreta-123')
        ->call('authenticate')
        ->assertHasErrors(['login']);

    Livewire::test('auth.login')
        ->set('login', $account->email)
        ->set('password', 'nueva-clave-9')
        ->call('authenticate')
        ->assertRedirect(route('panel.home'));
});

it('la action conserva la fila de la sesión actual y solo borra las demás', function () {
    $account = profileAccount('timekeeper');
    $this->actingAs($account);
    $ownSessionId = session()->getId();

    seedSessionRow($ownSessionId, $account->id);
    seedSessionRow('sesion-ajena-0000000000000000000000000002', $account->id);

    app(UpdateOwnPassword::class)($account, [
        'current_password' => 'secreta-123',
        'password' => 'nueva-clave-9',
        'password_confirmation' => 'nueva-clave-9',
    ]);

    expect(DB::table('sessions')->where('id', $ownSessionId)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'sesion-ajena-0000000000000000000000000002')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Paridad de roles (ISSUE-113: un user edita su perfil sin selector de rol)
|--------------------------------------------------------------------------
*/

it('un cronometrador cambia su contraseña igual que un admin', function () {
    $account = profileAccount('timekeeper');
    $this->actingAs($account);

    Livewire::test('profile.show')
        ->set('current_password', 'secreta-123')
        ->set('password', 'nueva-clave-9')
        ->set('password_confirmation', 'nueva-clave-9')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'Contraseña actualizada');

    expect(Hash::check('nueva-clave-9', $account->fresh()->password))->toBeTrue();
});
