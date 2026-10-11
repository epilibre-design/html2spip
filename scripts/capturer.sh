#!/bin/bash
# Régénère les captures du corpus (tests/corpus/captures/<type_url>/), un processus par type d'URL.
# Les captures sont versionnées : relire le diff avant de les commiter.
set -euo pipefail

racine=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
spip_racine="$racine/tests/spip-jetable/spip"
[ -f "$spip_racine/config/connect.php" ] || { echo "SPIP jetable absent : lancer composer installer-spip-jetable" >&2; exit 1; }
for type in page propres; do
	(cd "$spip_racine" && HTML2SPIP_TYPE_URL=$type "$racine/vendor/bin/spip" --no-ansi php:eval "include '$racine/tests/spip/capturer.php';")
done
