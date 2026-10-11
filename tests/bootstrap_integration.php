<?php
/**
 * Amorce des tests d'intégration : le SPIP jetable de tests/spip-jetable/spip (scripts/installer-spip-jetable.sh)
 */
declare(strict_types=1);

$racine = dirname(__DIR__);
$racine_spip = $racine . '/tests/spip-jetable/spip';
if (!is_file($racine_spip . '/config/connect.php')) {
	fwrite(STDERR, "SPIP jetable absent : lancer composer installer-spip-jetable\n");
	exit(1);
}

if (!defined('_SPIP_TEST_INC')) {
	define('_SPIP_TEST_INC', $racine_spip);
}
if (!defined('_SPIP_TEST_CHDIR')) {
	define('_SPIP_TEST_CHDIR', $racine_spip);
}

putenv('APP_ENV=test');
chdir($racine_spip);
require_once $racine . '/vendor/autoload.php';
if (is_file($racine_spip . '/vendor/autoload.php')) {
	require_once $racine_spip . '/vendor/autoload.php';
}
require_once $racine_spip . '/ecrire/inc_version.php';

include_spip('inc/plugin');
actualise_plugins_actifs();

require_once $racine . '/tests/spip/corpus.php';
require_once $racine . '/tests/spip/rendu.php';
html2spip_test_type_url();
// Ne pas charger tests/bootstrap.php : seul l'autoload est commun
