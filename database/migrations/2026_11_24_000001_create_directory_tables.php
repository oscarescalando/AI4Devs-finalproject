<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Directorio: regiones, organizaciones, personas, cuentas y equipos.
// Regla de oro del núcleo (ADR-015): PK bigint + ULID público char(26) UNIQUE;
// el identificador entero nunca sale al exterior.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->string('name', 150)->index();
            $table->timestamps();
        });

        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('document', 30)->index(); // enmascarado en listados (INV-06)
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('birthdate'); // define minoría de edad para la superficie pública (INV-06)
            $table->char('gender', 1); // M / F / X
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('person_id')->unique()->constrained('persons')->cascadeOnDelete(); // persona ≠ cuenta, 1:1
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->unique(['team_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('regions');
    }
};
