<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas de la suite no dependen del build de Vite: renderizan
        // las vistas sin el manifest (el pipeline construye los assets por
        // separado y el humo de navegador los verifica de verdad).
        $this->withoutVite();
    }
}
