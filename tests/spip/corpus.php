<?php
/**
 * Lecture du corpus de tests/corpus/sources
 *
 * Une source : lignes « clé: valeur », une ligne ---, puis le texte. Le nom de fichier (sans .spip) nomme l'objet
 * dans les captures et les sorties attendues.
 */

/**
 * Types d'URL des captures et des tests d'intégration
 */
const HTML2SPIP_TEST_TYPES_URL = array('page', 'propres');

/**
 * Type d'objet => table, clé primaire
 */
const HTML2SPIP_TEST_OBJETS_TABLES = array(
	'rubrique' => array('spip_rubriques', 'id_rubrique'),
	'article' => array('spip_articles', 'id_article'),
	'breve' => array('spip_breves', 'id_breve'),
	'groupe_mots' => array('spip_groupes_mots', 'id_groupe'),
	'mot' => array('spip_mots', 'id_mot'),
	'site' => array('spip_syndic', 'id_syndic'),
	'document' => array('spip_documents', 'id_document'),
);

/**
 * @param string|null $type type d'objet (article…), ou null pour tous
 * @return array nom => champs de l'en-tête, texte, nom
 */
function html2spip_test_sources(?string $type = null): array {
	$sources = array();
	foreach (glob(dirname(__DIR__) . '/corpus/sources/*.spip') as $fichier) {
		[$entete, $texte] = explode("\n---\n", file_get_contents($fichier), 2) + array(1 => '');
		$champs = array();
		foreach (explode("\n", trim($entete)) as $ligne) {
			[$cle, $valeur] = array_map('trim', explode(':', $ligne, 2));
			$champs[$cle] = $valeur;
		}
		$champs['texte'] = rtrim($texte, "\n");
		$champs['nom'] = basename($fichier, '.spip');
		if ($type === null or $champs['type'] === $type) {
			$sources[$champs['nom']] = $champs;
		}
	}
	ksort($sources, SORT_NATURAL);
	return $sources;
}
