<?php
/**
 * Rendu d'un texte SPIP tel que le capture le corpus
 */

/**
 * propre() puis, s'il y a des notes, #NOTES dans un div.notes (comme squelettes-dist, sans son filet)
 *
 * L'état des notes est remis à zéro avant : deux rendus du même texte sont identiques.
 */
function html2spip_test_rendu(string $texte, string $lang = 'fr'): string {
	include_spip('inc/texte');
	include_spip('inc/lang');
	include_spip('public/fonctions');
	$notes = charger_fonction('notes', 'inc');
	$notes('', 'reset_all');
	changer_typo($lang);
	$html = propre($texte);
	$html_notes = calculer_notes();
	return $html . ($html_notes !== '' ? "\n<div class=\"notes\">\n$html_notes\n</div>" : '');
}

/**
 * Type d'URL de la session (variable d'environnement HTML2SPIP_TYPE_URL), appliqué en mémoire sans écrire la méta
 */
function html2spip_test_type_url(): string {
	$type = getenv('HTML2SPIP_TYPE_URL') ?: 'page';
	$GLOBALS['type_urls'] = $GLOBALS['meta']['type_urls'] = $type;
	return $type;
}
