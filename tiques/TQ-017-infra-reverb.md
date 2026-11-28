---
id: TQ-017
titulo: "Infra de tiempo real: Reverb + Redis/Horizon + canales + Echo"
tipo: chore
prioridad: P0
estimacion: L
estado: en-curso
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

- [ ] Instalar y configurar Reverb + broadcasting; queue redis + Horizon (gate superadmin).
- [ ] Canales autorizados: public anónimo sin PII; ops exige rol operativo (pruebas).
- [ ] WaveStarted afterCommit desde MarkWaveStart (nada emitido en rollback — prueba).
- [ ] Echo (reverb) en app.js + live-indicator accesible (G13) disponible para pantallas.
- [ ] Degradación: sin WS, las pantallas actuales siguen operativas por sondeo.
- [ ] Pruebas + gates completos en verde.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] Reverb arranca (`reverb:start`), Horizon sirve el dashboard solo a superadmin, y el
      evento piloto llega al canal tras el commit (prueba de cola de broadcast).
- [ ] Anónimo rechazado en ops y aceptado en public (RF-75).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado como fase 1 del plan TQ-016 |
| 2026-11-27 | en-curso | Delegado a subagent (orquestador) |
