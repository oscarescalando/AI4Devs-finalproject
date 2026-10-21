# ADR-017 — Generación de PDF con dompdf para las planillas de inscripción

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- El nuevo sistema debe **emitir la planilla de inscripción** que el organizador hoy imprime fuera del sistema: una
  versión **por inscripción** (datos del competidor, texto legal de términos de participación y liberación de
  responsabilidades, firma) y una versión **en blanco**, con plantilla configurable por organizador (RF-36). Hay que
  elegir y registrar la librería de PDF.
- **Documentos previos:** la propuesta base del corpus de origen mencionaba "**spatie/browsershot** o **dompdf** para
  PDF" sin decidir; ISSUE-079 (exportaciones) e ISSUE-080 (diplomas, v2) cubren otros documentos. El texto legal de la
  planilla está conservado en los documentos del corpus de origen y debe reproducirse fiel.
- **Hecho nuevo:** el product owner confirma la generación de planillas dentro del alcance del núcleo.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | La planilla conserva texto legal vigente que debe reproducirse fiel (cláusulas + firma) | Alto |
| 2 | Operación en sede sin Internet (O5): nada puede depender de servicios de terceros para generar PDF | Alto |
| 3 | Nodo de sede con recursos modestos: sin runtimes extra (Node/Chrome) si no son necesarios | Alto |
| 4 | Plantilla fija y simple (una página, tablas y texto): no se necesita CSS moderno | Medio |
| 5 | Futuro v2 (diplomas, ISSUE-080): la elección debe permitir reevaluar sin arrastrar acoplamientos | Bajo |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **dompdf** (elegida) | PHP puro, sin binarios ni runtimes extra; plantillas Blade reutilizables casi tal cual; funciona offline en el nodo de sede; suficiente para documentos de texto/tabla | Motor CSS limitado (sin grid moderno, soporte parcial de flexbox): obliga a plantillas simples; fuentes deben empaquetarse; render de páginas complejas más lento |
| spatie/browsershot (Chrome headless vía Puppeteer) | Fidelidad CSS total (cualquier HTML moderno) | Exige Node + Chromium instalados y actualizados en el nodo de sede; más memoria; contratiempo de despliegue para una planilla fija |
| maatwebsite/excel | Ya en el stack (import/export) | Produce hojas de cálculo, no PDF con diseño legal |
| Servicio externo de PDF (API) | Cero mantenimiento | Contradice O5 y añade un proveedor y envío de PII fuera de la sede |
| No generar planillas en el núcleo | Menos alcance | Mantiene el proceso manual fuera del sistema y desaprovecha el texto legal ya consolidado |

## Decisión

**dompdf** es la librería de PDF del núcleo. Las planillas se construyen como **plantillas Blade para dompdf** —
planilla de inscripción **por inscripción** (datos del competidor, texto legal íntegro, firma) y **versión en
blanco**, con **plantilla por organizador (RF-36)** —, adaptando el CSS a las capacidades del motor:

- Una **ruta/Action protegida por policy** (solo personal del evento con permiso de inscripción; auditada si exporta
  datos personales): la descarga existe, funciona y queda autorizada desde el primer día.
- Generación en el request para una planilla individual, y por cola (Horizon) para lotes (p. ej., planillas de todo un
  evento), entregadas como descarga asíncrona (canal `private-account.{ulid}`, `export.ready` — ADR-013).
- Fuentes empaquetadas en el repositorio (sin descargas en runtime).

Si en v2 los diplomas (ISSUE-080) exigen CSS que dompdf no soporta, se reevalúa browsershot **solo** para ese tipo de
documento mediante un ADR posterior; dompdf sigue siendo la base del núcleo.

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | La planilla de inscripción es una funcionalidad operativa del núcleo, con autorización y auditoría |
| Positiva | Sin runtimes adicionales: el mismo contenedor PHP genera PDF en dev, VPS y nodo de sede, también sin Internet |
| Positiva | El texto legal vive en una sola fuente versionada (la plantilla), no en documentos sueltos |
| Negativa (mitigada) | Plantillas limitadas en CSS: mitigado manteniendo el diseño fijo (tablas + texto) y verificando el render en el test |
| Negativa (mitigada) | Rendimiento en lotes grandes: mitigado generando por cola con progreso |
| Neutral | `spatie/browsershot` queda como opción documentada para documentos de diseño complejo en v2 |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| Decide | La alternativa "spatie/browsershot **o** dompdf" dejada abierta por la propuesta base del corpus de origen |
| Complementa | ISSUE-079 (exportaciones), ISSUE-080 (diplomas v2); el portal (ISSUE-092, fase SaaS, ADR-016) usará la misma base de PDF del núcleo |
| Requiere | ADR-010 (stack), ADR-013 (entrega asíncrona de lotes) |
| Verificación | DoD "feature" en [../05-dod.md §2](../05-dod.md) |

## Cumplimiento verificable

- `composer.json` requiere `dompdf/dompdf`; no hay dependencias de Node para documentos en el pipeline de producción.
- Test: la planilla de un inscrito se genera y contiene el título "PLANILLA DE INSCRIPCIÓN", el número, el hit y el
  texto legal íntegro (comparación del texto extraído del PDF); la versión en blanco, ídem sin datos.
- Test de plantilla por organizador (RF-36): la variante de plantilla seleccionable se aplica al render.
- Test de autorización: sin permiso sobre el evento, la descarga responde 403 y queda registrada en auditoría cuando
  procede; con permiso, entrega `application/pdf`.
- Test de lote: 100 planillas encoladas generan un único entregable con progreso y sin superar el timeout del worker.
- Playwright (ADR-018): smoke de la descarga desde la pantalla de inscripciones.
