---
id: TQ-017
titulo: "Infra de tiempo real: Reverb + Redis/Horizon + canales + Echo"
tipo: chore
prioridad: P0
estimacion: L
estado: hecho
refiere_a: ISSUE-141, ISSUE-143, ISSUE-144
parte_de: TQ-016
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-017 — Infra de tiempo real: Reverb + Redis/Horizon + canales + Echo

## Objetivo

Montar el sustrato de ADR-013/010: Reverb operativo, cola Redis con Horizon, canales por evento
con autorización (RF-75), cliente Echo en el bundle y el componente live-indicator (G13), con un
evento piloto (`WaveStarted`) emitido afterCommit desde `MarkWaveStart`.

## Contexto

- phpredis cargado (Herd); servicios Redis de Herd disponibles.
- Canales to-be: `event.{ulid}.public` (anónimo, sin PII) y `event.{ulid}.ops` (roles
  operativos; el ámbito por equipo-evento llega con F2 — documentar).
- Convención v/seq del leaderboard público como semilla del contrato de ISSUE-141.

## Contratos públicos

- Nuevos: dependencias reverb/horizon/echo; `routes/channels.php`; evento `WaveStarted`
  (ShouldBroadcast, cola broadcast); Echo en `app.js` + live-indicator `x-ui.live-indicator`;
  pantallas siguen funcionando sin WS (degradación a sondeo).
- Modificados: `.env.example` (catálogo BROADCAST/REVERB/QUEUE/HORIZON), `MarkWaveStart` emite.

## Tareas

- [x] Instalar y configurar Reverb + broadcasting; queue redis + Horizon (gate superadmin).
- [x] Canales autorizados: public anónimo sin PII; ops exige rol operativo (pruebas).
- [x] WaveStarted afterCommit desde MarkWaveStart (nada emitido en rollback — prueba).
- [x] Echo (reverb) en app.js + live-indicator accesible (G13) disponible para pantallas.
- [x] Degradación: sin WS, las pantallas actuales siguen operativas por sondeo.
- [x] Pruebas + gates completos en verde.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] Reverb arranca (`reverb:start`), Horizon sirve el dashboard solo a superadmin, y el
      evento piloto llega al canal tras el commit (prueba de cola de broadcast).
- [x] Anónimo rechazado en ops y aceptado en public (RF-75).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado como fase 1 del plan TQ-016 |
| 2026-11-27 | en-curso | Delegado a subagent (orquestador) |
| 2026-11-27 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 266/266 (14 nuevas: canales vía /broadcasting/auth con firma HMAC, WaveStarted commit/rollback, live-indicator, regla Arch de eventos) · reverb/redis activos en config · E2E REAL del agente: navegador suscrito a event.{ulid}.ops recibió wave.started tras MarkWaveStart por tinker; degradación sin WS sin errores JS. Decisiones: ShouldDispatchAfterCommit difiere TODO el dispatch al commit raíz; WaveStarted solo a .ops por ahora; gate Horizon superadmin (local lo autoriza por defecto del paquete) |
