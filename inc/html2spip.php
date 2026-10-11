<?php
/**
 * html2spip() : HTML en raccourcis SPIP, y compris le HTML produit par SPIP ; remplace sale()
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

use Html2Spip\Convertisseur;
use Html2Spip\Spip\OptionsSpip;

/**
 * @param string $html fragment HTML (sortie de propre(), contenu d'un éditeur…)
 * @param string|null $langue langue du texte, qui choisit la typographie à inverser ; null : langue courante
 * @return string texte SPIP
 */
function html2spip(string $html, ?string $langue = null): string {
	$convertisseur = new Convertisseur(OptionsSpip::options($langue ?? $GLOBALS['spip_lang']));
	$texte = $convertisseur->convertir($html);
	$GLOBALS['html2spip_avertissements'] = $convertisseur->avertissements();
	return $texte;
}

/**
 * Avertissements de la dernière conversion par html2spip()
 *
 * @return string[]
 */
function html2spip_avertissements(): array {
	return $GLOBALS['html2spip_avertissements'] ?? array();
}
