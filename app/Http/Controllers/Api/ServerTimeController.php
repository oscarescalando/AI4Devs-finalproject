<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Timing\ServerTime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class ServerTimeController extends Controller
{
    /**
     * GET /api/v1/time — hora oficial del servidor (referencia del offset).
     *
     * El endpoint sigue siendo público (ADR-012), pero desde TQ-021, si el
     * llamador PRESENTA un Bearer, tiene que ser un token de dispositivo
     * válido con la ability device:marks: la pantalla /station valida ahí
     * su emparejamiento sin efectos secundarios — la otra vía autenticada,
     * el lote de marcas, escribe. Sin cabecera (reloj de pantallas públicas)
     * el comportamiento no cambia.
     */
    public function __invoke(ServerTime $action, Request $request): JsonResponse
    {
        $bearer = $request->bearerToken();

        if ($bearer !== null) {
            $accessToken = PersonalAccessToken::findToken($bearer);

            abort_if($accessToken === null, 401, 'Token de dispositivo inválido.');
            abort_if(! $accessToken->can('device:marks'), 403, 'El token no tiene la ability device:marks.');
        }

        return response()->json($action());
    }
}
