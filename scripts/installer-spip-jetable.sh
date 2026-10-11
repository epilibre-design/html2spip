#!/bin/bash
# Installe le SPIP jetable des tests d'intégration dans tests/spip-jetable/spip : dernière SPIP 4.4 en SQLite,
# html2spip lié depuis le dépôt (aucun autre plugin), corpus chargé avec des identifiants fixes.
# Les étapes déjà faites sont sautées ; le corpus est rechargé à chaque fois. --reinitialiser repart de zéro.
set -euo pipefail

racine=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
jetable="$racine/tests/spip-jetable"
spip_racine="$jetable/spip"
spip_cli="$racine/vendor/bin/spip"

erreur() {
	echo "ERREUR : $*" >&2
	exit 1
}

spip() {
	(cd "$spip_racine" && "$spip_cli" --no-ansi "$@")
}

[ -x "$spip_cli" ] || erreur "SPIP-Cli absent de vendor/bin : lancer composer install"
if [ "${1:-}" = --reinitialiser ]; then
	rm -rf "$jetable"
fi

# 1. Dernière SPIP 4.4, préparée et installée en SQLite
if [ ! -f "$spip_racine/ecrire/inc_version.php" ]; then
	mkdir -p "$jetable"
	(cd "$jetable" && "$spip_cli" --no-ansi core:telecharger spip -R 4.4 -d "$spip_racine")
fi
spip core:preparer
if [ ! -f "$spip_racine/config/connect.php" ]; then
	spip core:installer --db-server sqlite3 --db-database spip --db-prefix spip \
		--admin-login admin --admin-email admin@example.test \
		"--admin-pass=$(php -r 'echo bin2hex(random_bytes(12));')" --adresse-site http://localhost
fi
[ -f "$spip_racine/config/connect.php" ] || erreur "SPIP non installé (config/connect.php absent)"

# 2. html2spip, lié depuis le dépôt
mkdir -p "$spip_racine/plugins"
[ -e "$spip_racine/plugins/html2spip" ] || ln -s "$racine" "$spip_racine/plugins/html2spip"
spip plugins:activer html2spip -y
spip plugins:maj:bdd

# 3. Corpus
spip php:eval "include '$racine/tests/spip/charger_corpus.php';"

# 4. Version installée, contrôlée par tests/integration/EnvironnementTest.php
version_spip=$(spip php:eval 'echo $GLOBALS["spip_version_branche"];')
printf 'spip=%s\n' "$version_spip" >"$jetable/VERSION"
echo "SPIP jetable prêt : $spip_racine (SPIP $version_spip)"
