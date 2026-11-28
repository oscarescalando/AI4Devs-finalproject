<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inscripciones: entries con dorsal y ajustes acumulables.
// Reglas en la base (INV-03/04/05/09): UNIQUE (event_id, bib),
// UNIQUE (event_id, person_id) y CHECK persona XOR equipo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('race_id')->constrained('races')->restrictOnDelete();
            $table->foreignId('wave_id')->constrained('waves')->restrictOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->restrictOnDelete(); // individual
            $table->foreignId('team_id')->nullable()->constrained('teams')->restrictOnDelete(); // equipo
            $table->unsignedInteger('bib'); // dorsal
            $table->string('status', 20)->default('registered');
            $table->timestamps();

            $table->unique(['event_id', 'bib']); // INV-03
            $table->unique(['event_id', 'person_id']); // INV-04 (NULL repetido permitido para equipos)
            $table->index(['event_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE entries ADD CONSTRAINT entries_person_xor_team CHECK ('
            .'(person_id IS NOT NULL AND team_id IS NULL) OR (person_id IS NULL AND team_id IS NOT NULL))'
        );

        Schema::create('entry_adjustments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->string('type', 20); // penalty|bonus|historical
            $table->bigInteger('delta_ms'); // con signo
            $table->string('reason', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_adjustments');
        DB::statement('ALTER TABLE entries DROP CONSTRAINT entries_person_xor_team');
        Schema::dropIfExists('entries');
    }
};
