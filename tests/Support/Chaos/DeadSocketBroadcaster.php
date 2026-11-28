<?php

declare(strict_types=1);

namespace Tests\Support\Chaos;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;

/**
 * TQ-019 — Doble de caos, nivel «fallo del WORKER de broadcast».
 *
 * El broadcaster está «vivo» como conexión pero su socket está muerto:
 * cada broadcast() revienta con BroadcastException, como un Reverb caído
 * cuando Horizon ya tomó el job de la cola `broadcast`. Sirve para
 * procesar BroadcastEvent reales por queue:work y probar que el fallo
 * muere aislado en failed_jobs sin tocar la cola `default` ni los datos.
 */
final class DeadSocketBroadcaster implements Broadcaster
{
    public function auth($request)
    {
        return null;
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        throw new BroadcastException('chaos: el servidor de WS no responde');
    }
}
