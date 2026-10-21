#!/usr/bin/env bash
# Hook PreToolUse (Bash · git commit) para specMicroServicioPago.
#
# 1) Bloquea mensajes de commit con metadatos de autoría
#    (Co-Authored-By / Signed-off-by / "Generated with"): la política de este
#    repositorio es NO incluirlos.
# 2) Escanea el diff staged en busca de secretos de alta confianza
#    (claves privadas, tokens AWS/Stripe/GitHub/Slack/Google, JWT).
#
# Entrada: JSON del hook por stdin. Sale con código 2 para bloquear el tool
# call (el stderr se muestra al modelo); 0 para permitir.

set -uo pipefail

CMD=$(jq -r '.tool_input.command // empty')

# Solo aplica a comandos `git commit` (cualquier variante).
case "$CMD" in
  *"git commit"*) ;;
  *) exit 0 ;;
esac

# --- 1) Metadatos de autoría ---------------------------------------------
if printf '%s' "$CMD" | grep -qiE 'co-authored-by|signed-off-by|generated with|🤖'; then
  echo "BLOQUEADO: el mensaje del commit incluye metadatos de autoría (Co-Authored-By, Signed-off-by o 'Generated with'). La política de este repositorio es no incluirlos; reescribe el mensaje sin esos trailers." >&2
  exit 2
fi

# --- 2) Secretos en el stage ----------------------------------------------
# Patrones de ALTA confianza (baja probabilidad de falso positivo).
# Se excluyen *.example/*.sample (placeholders legítimos) y .claude/**
# (documentación de patrones de este mismo hook/skill).
PATTERNS='-----BEGIN (RSA |EC |OPENSSH |DSA |PGP )?PRIVATE KEY-----|AKIA[0-9A-Z]{16}|ASIA[0-9A-Z]{16}|sk-[A-Za-z0-9_-]{20,}|ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{20,}|xox[baprs]-[A-Za-z0-9-]{10,}|AIza[0-9A-Za-z_-]{35}|eyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}'

FINDINGS=$(git diff --cached -- . ':(exclude)*.example' ':(exclude)*.sample' ':(exclude).claude/**' 2>/dev/null \
  | grep -E '^\+' \
  | grep -m 5 -E -e "$PATTERNS")

if [ -n "$FINDINGS" ]; then
  echo "BLOQUEADO: posible secreto en el stage. Corrige estos hallazgos (quita la credencial o muévela a una variable de entorno / archivo ignorado) antes de commitear:" >&2
  printf '%s\n' "$FINDINGS" | cut -c1-160 >&2
  exit 2
fi

exit 0
