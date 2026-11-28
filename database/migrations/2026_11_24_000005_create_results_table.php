<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Resultados: tabla derivada que solo escribe el job de cálculo del
// servidor (INV-07 / ISSUE-158); nunca se edita a mano.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('entry_id')->unique()->constrained('entries')->cascadeOnDelete();
            $table->bigInteger('net_ms'); // llegada − salida + Σ ajustes
            $table->unsignedInteger('rank_general')->nullable();
            $table->unsignedInteger('rank_gender')->nullable();
            $table->unsignedInteger('rank_category')->nullable();
            $table->unsignedInteger('points')->nullable(); // liga (v2)
            $table->string('status', 20)->default('provisional'); // provisional|official|dsq
            $table->timestamps();

            $table->index('rank_general');
            $table->index(['entry_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
