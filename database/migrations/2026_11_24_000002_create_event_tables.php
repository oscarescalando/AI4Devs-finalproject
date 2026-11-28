<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Eventos: evento, carreras/distancias, oleadas y categorías.
// Los instantes usan datetime(3) y las duraciones bigint ms (INV-10).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique(); // identificador público de rutas/API/broadcast
            $table->string('name', 150)->unique();
            $table->string('slug', 150)->unique(); // rutas públicas /e/{slug}
            $table->date('date');
            $table->string('timezone', 64)->default('America/Caracas');
            $table->string('status', 20)->default('live'); // draft|registration|live|finished|closed
            $table->timestamps();
        });

        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->string('name', 50); // 21K, 10K, Trail 12K…
            $table->timestamps();
        });

        Schema::create('waves', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('race_id')->constrained('races')->restrictOnDelete();
            $table->string('name', 50)->nullable(); // Oleada 1, Élite…
            $table->datetime('scheduled_start_at', 3)->nullable(); // hora programada
            $table->datetime('actual_start_at', 3)->nullable(); // hora real (salida dada)
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->string('name', 50);
            $table->boolean('for_teams')->default(false);
            $table->unsignedInteger('min_age')->nullable();
            $table->unsignedInteger('max_age')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('waves');
        Schema::dropIfExists('races');
        Schema::dropIfExists('events');
    }
};
