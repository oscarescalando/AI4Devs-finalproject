# ADR-018 — Pruebas E2E con Playwright

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- El nuevo sistema exige **verificación automatizada de flujo completo**: los objetivos O1-O5 y la paridad de casos de
  uso (O3) no son demostrables solo con pruebas unitarias y de feature. La herramienta E2E debe soportar: interacciones
  `wire:` (Livewire), componentes Alpine, service workers/IndexedDB y **simulación de red cortada**.
- **Hecho nuevo:** se formaliza la elección como decisión propia para que la DoD ([../05-dod.md](../05-dod.md)) pueda
  exigirla y definir **suites y gates** concretos por hito.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | Cobertura E2E de los casos de uso (paridad O3) y de todas las pantallas | Alto |
| 2 | Soporte de contextos offline (service worker, IndexedDB) para la estación (ADR-014) | Alto |
| 3 | Integración con CI (Docker) y ejecución multi-navegador | Alto |
| 4 | Suites explícitas (smoke / paridad / estación offline) exigibles como gate de merge y de hito | Alto |
| 5 | Velocidad y estabilidad de ejecución en el pipeline del equipo (2 personas) | Medio |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **Playwright** (elegida) | Soporta contextos offline (`setOffline`), service workers y múltiples orígenes; multi-navegador (Chromium/Firefox/WebKit); trazas, vídeos y capturas para depurar; paraleliza en CI | Curva inicial de selectores Livewire (fácil: anclas por atributo de testing); ejecución algo más pesada que unitarios |
| Cypress | Buena experiencia de desarrollo y time-travel | Simulación de escenarios offline/service worker y de pestañas múltiples más incómoda; apoyo histórico a Livewire irregular |
| Selenium/WebdriverIO | Estándar veterano, muchos entornos | Más lento y frágil de mantener para un equipo pequeño |
| Pruebas manuales + ensayos | Cero infraestructura | No repetible; no sirve como gate de CI ni de hito |

## Decisión

**Playwright es la herramienta oficial E2E del nuevo sistema.** Suites definidas desde F1:

| Suite | Contenido | Cuándo corre |
|---|---|---|
| `smoke` | Todas las pantallas del mapa to-be cargan con cada rol y sin errores de consola | Cada push (CI) |
| `cu-paridad` | Un escenario E2E por cada caso de uso del checklist O3 ([../05-dod.md §4](../05-dod.md)) | Cada push (CI) |
| `estacion-offline` | Captura con red cortada, recarga, resincronización, 0 perdidas/duplicadas (ADR-014) | Nocturna + antes de cada hito |
| `tiempo-real` | Clasificación que se actualiza por Reverb y se resincroniza por `seq` (ADR-013) | Nocturna |
| `descargas` | PDF de planillas y exportaciones (ADR-017) | Nocturna |

Convenciones: los componentes Livewire exponen anclas de test (atributos dedicados); los E2E corren contra el stack
Docker con datos de prueba controlados (nunca contra producción); una prueba E2E roja **bloquea** el merge, igual que
las unitarias.

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | La paridad O3 se comprueba automáticamente en cada push, no en una maratón previa a la entrega |
| Positiva | El escenario crítico de ADR-014 (offline) es repetible en CI nocturno y antes de cada hito |
| Positiva | Detecta regresiones de flujo completo (permisos, estados, navegación por evento) que las pruebas de feature Livewire no cubren |
| Negativa (mitigada) | Mantenimiento de selectores ante cambios de UI: mitigado con anclas de test y páginas-objeto |
| Negativa (mitigada) | Tiempo de pipeline mayor: mitigado con paralelización y separación nocturna de suites lentas |
| Neutral | Vitest queda para JS/Alpine aislado si aparece; k6 para carga; ninguno sustituye a Playwright |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| Formaliza | Playwright como herramienta E2E oficial del núcleo, con sus suites (`smoke`, `cu-paridad`, `estacion-offline`) y gates de merge y de hito |
| Complementa | Pest (unitarias/feature), Larastan/Pint (estático), Pest Arch (arquitectura), k6 (carga), gitleaks (secretos) |
| Habilita | Las verificaciones E2E exigidas por ADR-013 (resincronización), ADR-014 (offline), ADR-017 (descargas) y la DoD de pantalla ([../05-dod.md §2](../05-dod.md)) |
| Verificación | DoD global y checklist O3 en [../05-dod.md](../05-dod.md); criterio de salida de F1 (suite E2E operando en CI) en [../08-roadmap.md](../08-roadmap.md) |

## Cumplimiento verificable

- `playwright` en `devDependencies` con configuración en el repositorio; job de CI "E2E" separado del job unitario.
- Criterio de salida de F1: `smoke` de todas las pantallas verdea en CI contra el stack Docker.
- Criterio de salida de F2/M3: `cu-paridad` y `estacion-offline` verdes; el ensayo M3 exige ambos en verde + manual.
- Informe de ejecución (trazas/capturas) archivado por corrida fallida; regla de merge bloqueado documentada en
  `CONTRIBUTING`.
