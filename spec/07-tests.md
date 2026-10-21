# 07 — Estrategia de pruebas de TDChronos 2.0

> **Fecha:** 2026-10-20 · **Estado:** vigente · Documento de especificación de TDChronos 2.0.
> Fuentes: [02-srs.md](02-srs.md) (requisitos), [03-arquitectura/ADR-018-e2e-playwright.md](03-arquitectura/ADR-018-e2e-playwright.md) (E2E), [05-dod.md](05-dod.md) (G1–G15), [04-backlog/](04-backlog/README.md) (épicas), [06-invariantes.md](06-invariantes.md).

## 1. Objetivo y principios

1. **Las pruebas son parte de la definición de cada requisito.** Ningún criterio de aceptación vale si no es observable en una prueba, una revisión o una medición (convención de formato de issue de [04-backlog/README.md](04-backlog/README.md) §7).
2. **El criterio de día de carrera es la integridad**: 0 marcas perdidas y 0 marcas duplicadas, con cualquier combinación de fallos de red o de servicios (INV-02, INV-08, INV-12 de [06-invariantes.md](06-invariantes.md)).
3. **CI verde es condición de merge** (criterio G15 de [05-dod.md](05-dod.md)); cobertura ≥ 80 % en el dominio crítico — Timing, Results y Registration (criterio G3).
4. **Trazabilidad completa**: cada suite se mapea a requisitos (RF/RNF), historias (HU), épicas (EP) e invariantes (INV) — §4 de este documento.

## 2. Pirámide de pruebas

### 2.1 Unitarias — Pest

Objeto: Actions de dominio y reglas puras, sin HTTP ni base de datos real.

- **Timing**: idempotencia por `client_mark_id` (INV-02), estado por marca (`assigned` / `orphan` / `duplicate`), corrección por offset de reloj (INV-10), append-only de marcas (INV-01).
- **Results**: `net_ms = llegada − salida + Σ ajustes`, posiciones con `RANK()` (general/género/categoría), ajustes acumulables de `entry_adjustments`, esquemas de puntos de la liga configurables (INV-07).
- **Registration**: categorización por rango de edad (con recategorización en lote y previsualización), generación de dorsal, importador XLSX fila a fila (informe de rechazos).
- Casos borde de cada caso de uso (tiempos > 24 h, empates, filas inválidas de importación, dorsales repetidos).

### 2.2 Feature — Pest

Objeto: flujos completos por HTTP y Livewire contra la base de datos real (migraciones + factories/seeders sintéticos).

- Matriz rol × acción completa (INV-14): organizador, cronometrador, juez, oficial de salida, atleta, club, administración; 401/403 con semántica correcta.
- Unicidades en base de datos: dorsal por evento, persona-evento, XOR persona/equipo (INV-03, INV-04, INV-05).
- Correcciones con motivo obligatorio y auditoría completa (INV-11), con previsualización y reversión.
- Privacidad: ninguna respuesta pública contiene documento, nacimiento, email ni teléfono (INV-06).
- Orden transacción → broadcast en los Actions que emiten eventos (INV-08).

### 2.3 Arquitectura — Pest Arch

Objeto: que el diseño decidido en [03-arquitectura/](03-arquitectura/README.md) se cumpla en cada PR, automáticamente.

- Flujo obligatorio componente Livewire / endpoint de API → **Action de dominio** → modelo (ADR-011, ADR-012).
- Límites de módulo del monolito modular y prohibiciones de los ADRs (sin lógica de negocio en componentes ni en cliente).
- Prohibiciones estructurales: nada de actualización sobre `timing_marks` (INV-01), nada de cálculo de posiciones en el cliente (INV-07).

### 2.4 Extremo a extremo — Playwright (ADR-018)

| Suite | Cuándo corre | Alcance |
|---|---|---|
| `smoke` | En cada PR (CI) | Camino principal por rol: login, navegación por evento (`/events/{ulid}/…`), pantallas clave del panel, pantalla pública y estación |
| `cu-paridad` | En CI y nocturna | Los 26 casos de uso del sistema definidos en [02-srs.md](02-srs.md) (§6), pantalla a pantalla (ISSUE-140, puerta de salida de EP-25) |
| `estacion-offline` | Nocturna | Escenario completo de la estación: emparejamiento, captura sin red de ≥ 500 marcas, recarga y cierre del navegador, sincronización de la cola, doble estación (ISSUE-152, puerta de salida de EP-27) |

### 2.5 Caos

Objeto: demostrar las invariantes de integridad bajo fallos, no solo en el camino feliz.

| Escenario | Inyección de fallo | Criterio de éxito |
|---|---|---|
| Broker caído | Reverb detenido durante la captura | La marca responde 200; al volver, el broadcast se recupera (INV-08) — ISSUE-145, EP-26 |
| Red intermitente | Corte de red cada 30 s durante la operación | La cola local se drena completa al volver: 0 perdidas, 0 duplicadas (INV-02, INV-12) |
| Lote offline grande | ≥ 500 marcas capturadas sin red, recarga y cierre del navegador | Ninguna marca se pierde al recargar; sincronización íntegra (INV-12) |
| Doble estación | Dos estaciones capturan la misma llegada dentro de la ventana anti-duplicados configurable | Se acepta una sola marca por la regla configurada (por defecto, la primera por `captured_at`) (INV-02) |
| Sincronización en segundo plano | 401/403 revocado a mitad de sincronización | Mensaje claro y cola local preservada; nada se descarta por un fallo de autorización |

### 2.6 Carga — k6

- **Clasificación en vivo**: ≥ 200 espectadores conectados al canal del evento con delta de actualización < 2 s en p95 (ISSUE-146, EP-26).
- **Ingesta de marcas**: 60 marcas/minuto por estación con 4 estaciones simultáneas, sin errores ni degradación.
- Leaderboard público cacheable: medición de latencia del endpoint y del canal.

### 2.7 Transversal (estático y seguridad)

- **Larastan nivel 8** sin errores nuevos y **Pint** sin diff (G4).
- **gitleaks**: cero secretos en el repositorio (G10, INV-13).
- **`composer audit`**: dependencias sin vulnerabilidades conocidas.
- **Prueba automática de ausencia de PII** en respuestas y canales públicos (INV-06, G9).
- **Accesibilidad** (G11): WCAG 2.1 AA verificable en las pantallas del panel tocadas por la PR (teclado completo, labels, foco visible, contraste, `lang="es"`).

## 3. Gates de CI (orden de ejecución)

| # | Gate | Criterio | DoD |
|---|---|---|---|
| 1 | Pint | Sin diff de estilo | G4 |
| 2 | Larastan 8 | Sin errores nuevos | G4 |
| 3 | Pest unit + feature | Todo verde; cobertura ≥ 80 % en Timing / Results / Registration | G2, G3 |
| 4 | Pest Arch | Reglas de arquitectura en verde | G5 |
| 5 | gitleaks | Sin secretos | G10 |
| 6 | `composer audit` | Sin vulnerabilidades | G10 |
| 7 | Playwright `smoke` | Suite en verde | G12 |
| 8 | Build de la imagen Docker | Compila y arranca (app, MySQL, Redis, Reverb, Horizon) | G15 |

**Condición de merge**: los 8 gates en verde sobre la PR, más la checklist de la Definition of Done ([05-dod.md](05-dod.md), G1–G15). Las suites `cu-paridad` y `estacion-offline` corren en CI nocturno y son puerta de salida de sus épicas (EP-25 y EP-27).

## 4. Trazabilidad prueba → requisito / HU / épica / invariante

| Nivel o suite | Verifica | Requisitos · HU · Épica | Invariantes |
|---|---|---|---|
| Pest unit (Timing) | Idempotencia, offset de reloj, append-only | RF-37…RF-44, RF-82 · HU-47 · EP-27 | INV-01, INV-02, INV-10 |
| Pest unit (Results) | `net_ms`, `RANK()`, ajustes, esquemas de puntos | RF-51…RF-63, RF-76 · EP-25 | INV-07 |
| Pest unit (Registration) | Categorización, dorsales, importador XLSX | RF-26…RF-31, RF-64…RF-67 · EP-25 | INV-03, INV-04 |
| Pest feature (acceso) | Matriz rol × acción, menú por permisos | RF-68…RF-73, RF-79 · HU-48 · EP-24 | INV-14 |
| Pest feature (privacidad) | Sin PII en superficies públicas | RNF-13 · HU-52 · EP-25/EP-26 | INV-06 |
| Pest feature (correcciones) | Motivo, auditoría, reversión | RF-46…RF-50, RF-84 · HU-49 · EP-25 | INV-01, INV-11 |
| Pest Arch | Livewire → Actions, límites de módulo | ADR-011, ADR-012, ADR-015 · EP-24 | INV-01, INV-07, INV-14 |
| Playwright `smoke` | Camino principal por rol | G12 · todas las épicas | — |
| Playwright `cu-paridad` | Los 26 casos de uso | RF-01…RF-86 · EP-25 (ISSUE-140) | — |
| Playwright `estacion-offline` | Captura sin red, sync, doble estación | RF-41, RF-82 · HU-47 · EP-27 (ISSUE-152) | INV-02, INV-12 |
| Caos (EP-26/EP-27) | 0 perdidas / 0 duplicadas; marca 200 con broker caído | RF-74, RF-84 · EP-26 (ISSUE-145) · EP-27 | INV-01, INV-02, INV-08, INV-12 |
| k6 | Latencia en vivo y tasa de ingesta | RNF de rendimiento ([02-srs.md](02-srs.md) §4) · RF-80 · EP-26 (ISSUE-146) | — |
| Transversal CI | Estilo, estático, secretos, PII, a11y | G4, G9, G10, G11 | INV-06, INV-13 |

## 5. Referencias

- [03-arquitectura/ADR-018-e2e-playwright.md](03-arquitectura/ADR-018-e2e-playwright.md) — decisión E2E con Playwright y suites.
- [03-arquitectura/ADR-013-tiempo-real-reverb.md](03-arquitectura/ADR-013-tiempo-real-reverb.md) — reglas de broadcast que verifican el caos.
- [05-dod.md](05-dod.md) — criterios G1–G15 y DoD por tipo de entrega.
- [06-invariantes.md](06-invariantes.md) — invariantes y su verificación.
- [04-backlog/EP-26-tiempo-real-reverb.md](04-backlog/EP-26-tiempo-real-reverb.md) (ISSUE-145, ISSUE-146) y [04-backlog/EP-27-estacion-offline-pwa.md](04-backlog/EP-27-estacion-offline-pwa.md) (ISSUE-149, ISSUE-152) — issues de pruebas como entregable.
