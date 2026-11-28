<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Cronometraje: timing_marks es append-only (INV-01) — sin updated_at,
// con triggers que rechazan UPDATE y DELETE a nivel de base de datos.
// Idempotencia: client_mark_id (UUIDv7 del dispositivo) UNIQUE (INV-02).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timing_marks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('entries')->restrictOnDelete(); // null = huérfana
            $table->char('client_mark_id', 36)->unique(); // UUIDv7, idempotencia del reenvío
            $table->datetime('captured_at', 3); // hora oficial: captura corregida por offset (INV-10)
            $table->bigInteger('device_time_ms'); // reloj crudo del dispositivo
            $table->string('source', 20)->default('station'); // station|chip_reader|manual
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_id', 'created_at']);
            $table->index(['event_id', 'entry_id']);
        });

        // INV-01 en la base de datos: la marca nunca se modifica ni se borra;
        // toda corrección es una fila en timing_mark_corrections.
        DB::unprepared(
            'CREATE TRIGGER timing_marks_no_update BEFORE UPDATE ON timing_marks '
            ."FOR EACH ROW SIGNAL SQLSTATE '45000' "
            ."SET MESSAGE_TEXT = 'timing_marks es append-only (INV-01): use timing_mark_corrections'"
        );
        DB::unprepared(
            'CREATE TRIGGER timing_marks_no_delete BEFORE DELETE ON timing_marks '
            ."FOR EACH ROW SIGNAL SQLSTATE '45000' "
            ."SET MESSAGE_TEXT = 'timing_marks es append-only (INV-01): la marca no se borra'"
        );

        Schema::create('timing_mark_corrections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('timing_mark_id')->constrained('timing_marks')->cascadeOnDelete();
            $table->string('operation', 20); // adjust|reassign|void
            $table->string('reason', 500); // motivo obligatorio (INV-11)
            $table->foreignId('account_id')->nullable()->constrained('accounts')->restrictOnDelete(); // quién corrige
            $table->json('before_after'); // auditoría {before:…, after:…}
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timing_mark_corrections');
        DB::unprepared('DROP TRIGGER IF EXISTS timing_marks_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS timing_marks_no_update');
        Schema::dropIfExists('timing_marks');
    }
};
