# ADR-011 — Interfaz web con Livewire 4 + Volt + Alpine.js + Blade (TALL)

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- El nuevo sistema necesita una UI web de panel, pantallas públicas y liga en la que **los permisos se apliquen en
  servidor**: lo que cada usuario ve y puede hacer debe derivarse de una única fuente (policies), sin lógica de
  autorización ni estado de sesión duplicado en el cliente.
- **Documentos previos:** la propuesta base del corpus de origen (ADR-002) había propuesto una **SPA desacoplada +
  API** (descartando Inertia) y el anexo de plataforma SaaS definió un front independiente para toda la web (bloque B2,
  meta-framework SSR). Con un equipo de 2 personas ([../08-roadmap.md](../08-roadmap.md)) mantener dos capas de
  aplicación (API + cliente JS con su propio estado) para la UI web duplica la superficie de validación, permisos y
  pruebas.
- **Hecho nuevo:** el product owner pide **Livewire** para la interfaz.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | **Una sola capa de estado y permisos**: las policies del servidor gobiernan lo que la UI muestra y hace | Alto |
| 2 | Preferencia explícita del product owner por Livewire | Alto |
| 3 | Validación en servidor por defecto: un solo juego de reglas y mensajes | Alto |
| 4 | Equipo: curva Blade/Livewire frente a mantener un front TS completo | Alto |
| 5 | Menos superficie: sin API dedicada a la UI (coherente con ADR-012) | Medio |
| 6 | Requisito duro O5 (funcionar sin Internet en la estación): exige diseño offline explícito (ver ADR-014) | Medio |
| 7 | Reactividad de la pantalla de clasificación en vivo (O4, < 2 s) | Medio |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **Livewire 4 + Volt + Alpine.js + Blade (TALL)** (elegida) | Una capa de estado/permisos; render en servidor; validación de servidor por defecto; equipo ya maneja Blade; menor superficie de ataque y de pruebas; es la petición del product owner | Reactividad fina requiere diseño (eventos Livewire, Alpine o `wire:poll`); la estación offline exige PWA + IndexedDB explícitos (ADR-014); el ecosistema de componentes UI es más pequeño que el de las SPA |
| SPA desacoplada + API (ADR-002 del corpus de origen) | PWA y caché de pantallas públicas "naturales"; TS tipa el cliente | Dos capas para una sola UI; doble validación y autorización también en cliente; dos pipelines de build/prueba |
| Inertia (capa SPA sobre Laravel) | CRUD rápido; un repo | Descartada: acopla render y datos, complica la PWA offline y las pantallas públicas cacheables; introduce un segundo paradigma |
| Meta-framework SSR para toda la web (bloque B2 del anexo SaaS) | SSR/SEO del portal; despliegue independiente | Tercer paradigma de UI; exige cliente OpenAPI y contratos para todo; sobredimensionado para 2 personas; queda sin efecto |

## Decisión

Toda la **UI web del nuevo sistema se construye con Livewire 4 + Volt + Alpine.js sobre Blade (stack TALL)**, dentro del
monolito Laravel 13 (ADR-010):

- **Panel del organizador** (eventos, categorías, hits, distancias, inscripciones), **bitácora y correcciones**,
  **control de salida**, **pantalla pública/kiosco de clasificación**, **liga** y **resultados oficiales**: componentes
  Livewire/Volt que llaman a los **Actions del dominio**; ninguna vista consume la API REST (ADR-012). Lo que cada
  usuario ve y puede hacer lo deciden las **policies en servidor**.
- **Estación de cronometraje**: PWA cuya captura es local (Alpine + IndexedDB) y cuya gestión es Livewire — detalle en
  **ADR-014**.
- **Fase SaaS (ADR-016):** el bloque B2 del anexo queda sin efecto. La consola de plataforma, el panel del organizador y
  el portal público/atleta también serán Livewire (el portal es HTML renderizado en servidor, con el SEO cubierto por
  Blade). Esta decisión prevalece sobre ADR-S05 y ADR-S01 en lo que toca al frontend web.

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | Una sola fuente de verdad de permisos (policies); sin gates solo de cliente ni estado de sesión duplicado en el navegador |
| Positiva | Validación en servidor por defecto: un solo juego de reglas y mensajes |
| Positiva | Menos superficie de API (ADR-012) y menos código de cliente que mantener; build simplificado (Vite para CSS/JS propio, sin framework SPA) |
| Positiva | Curva de entrada baja para un equipo Blade; demo en cada iteración más barata |
| Positiva | Cero consumo de API desde la UI: lo que se puede hacer en pantalla es exactamente lo que las policies permiten |
| Negativa (mitigada) | **Reactividad fina** (pantalla de clasificación con resaltado de llegadas, contadores de salidas): se resuelve con eventos Livewire entre componentes, Alpine local y `wire:poll` selectivo donde no llegue el evento; medición del O4 (< 2 s) en el ensayo M3 |
| Negativa (mitigada) | **Offline de la estación** no sale "gratis": exige PWA (manifest + service worker) y captura local en IndexedDB — decidido y acotado en ADR-014, con espiga obligatoria en F1 |
| Negativa (mitigada) | Cada interacción relevante pasa por el servidor: en la LAN de sede es el caso nominal (nodo local); fuera de sede solo se usa lectura pública |
| Neutral | El portal SaaS pierde el SSR "de framework JS", pero Blade ya renderiza en servidor; ADR-S04 (repo web TS, corpus de origen) queda sin efecto para la web |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| **Sustituye** | **ADR-002** (SPA desacoplada + API; decisión previa del corpus de origen). Se conserva de ADR-002 el rechazo a Inertia |
| **Deja sin efecto** | Bloque **B2** del anexo SaaS del corpus de origen: **ADR-S05** (y afecta a ADR-S01 y ADR-S04 en su parte web) |
| Adapta | ADR-013 (los clientes del tiempo real consumen desde Livewire/Echo nativo) y ADR-014 (la estación es PWA Livewire) |
| Habilita | ADR-012 (la API deja de tener consumidor web de panel) |
| Hereda sin cambios | Mapa de pantallas to-be del corpus de origen; requisitos de UX del PRD ([../01-prd.md](../01-prd.md)); ADR-001 (monolito, ahora con la UI dentro) |
| Verificación | DoD "pantalla Livewire" en [../05-dod.md §2](../05-dod.md); checklist de paridad O3 en [../05-dod.md §4](../05-dod.md) |

## Cumplimiento verificable

- **No existe** aplicación SPA en `resources/js` ni framework JS de UI en `package.json` del nuevo repositorio
  (prueba de arquitectura que falla si aparecen).
- `composer.json` exige `livewire/livewire` ^4 y `livewire/volt`; Alpine se sirve con el bundle de Vite.
- Prueba de arquitectura: los componentes Livewire **no** realizan llamadas HTTP internas (ni `Http::` ni fetch a
  `/api/v1`); solo invocan Actions/Queries del dominio.
- Prueba de autorización por pantalla: para cada componente, el test verifica la respuesta para cada rol/policy
  (matriz de permisos como código).
- Cada pantalla tiene test de feature Livewire + smoke Playwright (ADR-018) y cumple la DoD de pantalla
  ([../05-dod.md §2](../05-dod.md)).
- La clasificación en vivo mide latencia de actualización en el ensayo M3 y cumple O4 (< 2 s) sin `eval()` ni descarga
  de colecciones completas (antipatrón prohibido por prueba de arquitectura).
