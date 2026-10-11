<?php
/**
 * Classes de html2spip (src/) chargées hors Composer ; la copie de Masterminds (lib/) est chargée par
 * Html2Spip\Arbre\Arbre si besoin
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

if (!class_exists('Html2Spip\Convertisseur')) {
	spl_autoload_register(function ($classe) {
		if (str_starts_with($classe, 'Html2Spip\\')) {
			$fichier = __DIR__ . '/src/' . str_replace('\\', '/', substr($classe, strlen('Html2Spip\\'))) . '.php';
			if (is_file($fichier)) {
				require $fichier;
			}
		}
	});
}
