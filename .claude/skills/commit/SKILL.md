---
name: commit
description: >-
  Flujo completo de commit automático para este repositorio: verifica los cambios, escanea
  secretos, redacta un Conventional Commit en español SIN metadatos de autoría (Co-Authored-By),
  incrementa la versión SemVer, registra la entrada en CHANGELOG.md y hace push a la rama actual.
  Activar cuando el usuario diga "realizar commit", "enviar commit", "hacer commit", "commitear",
  "subir los cambios" o "sube los cambios".
allowed-tools: Bash(git status*), Bash(git diff*), Bash(git add*), Bash(git commit*), Bash(git log*), Bash(git show*), Bash(git branch*), Bash(git tag*), Bash(git describe*), Bash(git push*), Bash(.claude/hooks/*), Bash(bash .claude/hooks/*), Read, Edit, Write
---

# Commit convencional con versionado y changelog

Ejecuta el flujo completo **sin pedir confirmaciones ni información innecesaria**: el usuario
ya autorizó todo el flujo al invocar el skill. Solo detente si hay un bloqueo real (secreto
detectado, nada que commitear o error de git).

## Proceso secuencial

### 1. Verificar cambios

```bash
git status --porcelain
git branch --show-current
```

- Si no hay cambios (staged o unstaged): informa "no hay cambios para commitear" y **termina**.
- Guarda la rama actual para el push final. Si está en detached HEAD, informa y no hagas push.

### 2. Preparar el stage

```bash
git add -A
git diff --cached --stat          # resumen
git diff --cached                 # análisis técnico (léelo para redactar el mensaje)
```

Si el diff es muy grande, léelo por archivos (`git diff --cached -- <ruta>`); nunca redactes
el mensaje sin haber visto el diff real.

### 3. Escaneo de secretos (obligatorio, no omitible)

```bash
echo '{"tool_name":"Bash","tool_input":{"command":"git commit -m test"}}' | bash .claude/hooks/validate-commit.sh
```

- `exit 0` → continúa.
- `exit 2` → **ABORTA**: no commit, no push. Reporta al usuario los hallazgos exactos
  (archivo/línea) y sugiere mover la credencial a una variable de entorno o archivo ignorado.
  Nunca "pases por alto" un hallazgo ni reintentes con el secreto aún en el stage.

Además, al leer el diff en el paso 2, si ves contraseñas en texto claro, claves privadas,
connection strings con credenciales o tokens hardcodeados (aunque el patrón no dispare el
script), tratalos como hallazgo y aborta igual.

### 4. Tipo, scope y SemVer

Redacta el mensaje según la convención del repo (español, prefijo en inglés):

| Contexto del cambio | Tipo | Impacto SemVer |
|---|---|---|
| Nueva funcionalidad / archivo base | `feat` | minor |
| Corrección de bug / defecto | `fix` | patch |
| Documentación (`.md`, ADRs, specs) | `docs` | patch |
| Formato / estilos sin efecto | `style` | patch |
| Reestructuración sin cambio de conducta | `refactor` | patch |
| Optimización de velocidad/consumo | `perf` | patch |
| Pruebas nuevas o corregidas | `test` | patch |
| Build, dependencias, Makefile, config | `build` | patch |
| CI / scripts de integración | `ci` | patch |
| Mantenimiento general | `chore` | patch |
| Ruptura de compatibilidad (cualquiera de arriba) | `tipo!` + `BREAKING CHANGE:` | major |

- **Scope**: usa `pay` para el microservicio de pago (es el estándar del historial:
  `feat(pay): …`, `docs(pay): …`). Para algo transversal al repo, omite el scope.
- Si el cambio toca **ambas implementaciones** (Python y Laravel), menciónalo en el cuerpo
  ("Python + Laravel"), como hace el historial del repo.

### 5. Versionado

Determina la versión actual y calcula la siguiente:

```bash
git describe --tags --abbrev=0 2>/dev/null    # ej. v2.0.0
head -3 CHANGELOG.md 2>/dev/null              # si existe, la versión más reciente va arriba
```

- Bump según la tabla del paso 4 (`major.minor.patch`).
- Si no hay tag ni CHANGELOG, parte de `0.0.0`.
- Escribe la nueva versión en el archivo `VERSION` de la raíz (crea el archivo si no existe,
  solo el número, ej. `2.1.0`).

### 6. Registrar en CHANGELOG.md (Keep a Changelog + SemVer)

Inserta la entrada nueva **debajo del encabezado principal**. Si el archivo no existe,
crea el encabezado primero:

```markdown
# Changelog

Todos los cambios notables de este proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/); versionado SemVer.
```

Plantilla de la entrada (usa la fecha del día, `date +%Y-%m-%d`; sin hash — el commit aún
no existe):

```markdown
## [2.1.0] - 2026-08-14
### 🚀 Agregado
- descripción concisa del cambio (feat)

### 🐛 Corregido
- ...

### Otros
- ...
```

Secciones según tipo: `feat`→🚀 Agregado · `fix`→🐛 Corregido · `docs`→📝 Documentación ·
`refactor`→♻️ Refactorización · `perf`→⚡ Rendimiento · `test`→✅ Pruebas ·
`build`/`ci`/`chore`/`style`→🧹 Otros. Incluye solo la sección que aplique. Un cambio
`BREAKING CHANGE` añade una nota bajo `### ⚠️ Cambios rupturistas`.

Añade `CHANGELOG.md` y `VERSION` al mismo commit (`git add CHANGELOG.md VERSION`).

### 7. Commit (multi `-m`, sin autoría)

Reglas estrictas del mensaje:

- **Español 100%**, modo imperativo, minúsculas, sin punto final, máx **50 caracteres** la cabecera.
- **PROHIBIDO** añadir `Co-Authored-By`, `Signed-off-by`, `Generated with …` o cualquier
  metadato de autoría/tool — el usuario lo exige y el hook `.claude/hooks/validate-commit.sh`
  bloquea el commit si aparece. Solo el mensaje, nada más.
- Usa múltiples flags `-m` (nunca `\n` literal dentro de un solo `-m`): el primer `-m` es la
  cabecera y cada `-m` adicional un párrafo/bullet del cuerpo.

```bash
git commit -m "feat(pay): compensación de residual en moneda del pago" \
  -m "- Python + Laravel: paymentreport n=99 en la moneda propia del pago." \
  -m "- Evita IGTF/mixto al liquidar residuales < 0.009 USD."
```

Si hay `BREAKING CHANGE`, el último `-m` empieza con `BREAKING CHANGE: descripción`.

### 8. Push a la rama actual

```bash
git push origin <rama_del_paso_1>
```

- Si la rama no tiene upstream: `git push -u origin <rama>`.
- Si el push falla (remoto rechazado / red), reporta el error tal cual; no hagas `--force`.

### 9. Reporte final (minimalista)

```
✔ Commit: <tipo>(<scope>): <cabecera>  (<hash corto>)
✔ Versión: <anterior> → <nueva> (minor|patch|major)
✔ Push: origin/<rama>
```

## Opciones (si el usuario las pasa al invocar)

- `--type <tipo>` / `--scope <scope>` / `--message <texto>`: fuerzan cabecera o clasificación.
- `--major` / `--minor` / `--patch`: fuerzan el bump de versión.
- `--no-push`: todo el flujo excepto el push.
- `--no-changelog`: commit sin tocar CHANGELOG/VERSION (solo si el usuario lo pide explícitamente).
