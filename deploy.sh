#!/usr/bin/env bash
set -e

HOST="idfabrik"
REMOTE="/usr/home/idfabr/public_html/dev.tawanarun.fr/blog/"
LOCAL="$(cd "$(dirname "$0")" && pwd)/"

FILES=(admin.php index.php)

echo "→ Déploiement vers $HOST:$REMOTE"
echo ""

for f in "${FILES[@]}"; do
  rsync -az --progress "$LOCAL$f" "$HOST:$REMOTE$f"
  echo "  ✓ $f"
done

echo ""
echo "Terminé."
