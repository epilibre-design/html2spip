#!/bin/bash
# Tests d'intégration : SPIP jetable installé ou mis à jour, puis la suite integration pour chaque type d'URL
set -euo pipefail

racine=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
bash "$racine/scripts/installer-spip-jetable.sh"
for type in page propres; do
	echo "== URL $type"
	HTML2SPIP_TYPE_URL=$type "$racine/vendor/bin/phpunit" --testsuite integration --bootstrap "$racine/tests/bootstrap_integration.php" "$@"
done
