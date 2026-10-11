<?php
/**
 * Charge le corpus dans le SPIP jetable, avec des identifiants fixes
 *
 * Lancé par scripts/installer-spip-jetable.sh : spip php:eval "include '…/tests/spip/charger_corpus.php';"
 * Un objet déjà présent est remplacé : le chargement peut être relancé.
 */

include_once __DIR__ . '/corpus.php';

/**
 * Colonne du texte de la source, pour les types d'objets dont ce n'est pas texte
 */
const HTML2SPIP_TEST_COLONNES_DESCRIPTIF = array('groupe_mots', 'site', 'document');

/**
 * Champs de l'en-tête qui ne sont pas des colonnes
 */
const HTML2SPIP_TEST_HORS_COLONNES = array('type', 'id', 'nom', 'texte', 'limite', 'fichier', 'liens');

function html2spip_test_charger(array $source): void {
	[$table, $cle] = HTML2SPIP_TEST_OBJETS_TABLES[$source['type']];
	$colonne_texte = in_array($source['type'], HTML2SPIP_TEST_COLONNES_DESCRIPTIF, true) ? 'descriptif' : 'texte';
	$id = intval($source['id']);
	$ligne = array($cle => $id);
	foreach ($source as $nom => $valeur) {
		if (!in_array($nom, HTML2SPIP_TEST_HORS_COLONNES, true)) {
			$ligne[$nom] = $valeur;
		}
	}
	if ($source['texte'] !== '') {
		$ligne[$colonne_texte] = $source['texte'];
	}
	if ($source['type'] === 'document') {
		$ligne += html2spip_test_fichier($source['fichier']);
		sql_delete('spip_documents_liens', 'id_document=' . $id);
		foreach (array_filter(array_map('trim', explode(',', $source['liens'] ?? ''))) as $lien) {
			[$objet, $id_objet] = explode('/', $lien);
			sql_insertq('spip_documents_liens', array('id_document' => $id, 'objet' => $objet, 'id_objet' => intval($id_objet), 'vu' => 'non'));
		}
	}
	sql_delete($table, "$cle=$id");
	sql_insertq($table, $ligne);
}

/**
 * Fichier d'un document : copié de tests/corpus/documents dans IMG/<extension>/
 *
 * @return array colonnes de spip_documents décrivant le fichier
 */
function html2spip_test_fichier(string $nom): array {
	$source = dirname(__DIR__) . '/corpus/documents/' . $nom;
	$extension = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
	@mkdir(_DIR_IMG . $extension, 0777, true);
	copy($source, _DIR_IMG . "$extension/$nom");
	$taille = @getimagesize($source);
	return array(
		'fichier' => "$extension/$nom",
		'extension' => $extension,
		'media' => sql_getfetsel('media_defaut', 'spip_types_documents', 'extension=' . sql_quote($extension)) ?: 'file',
		'largeur' => $taille ? $taille[0] : 0,
		'hauteur' => $taille ? $taille[1] : 0,
		'taille' => filesize($source),
		'mode' => 'document',
		'distant' => 'non',
		'statut' => 'publie',
	);
}

foreach (html2spip_test_sources() as $source) {
	html2spip_test_charger($source);
}
include_spip('inc/rubriques');
calculer_rubriques();
propager_les_secteurs();

// Vérification : scripts/installer-spip-jetable.sh échoue sans la ligne « Corpus chargé » (une insertion qui
// échoue peut arrêter ce script sans erreur visible)
$absents = array();
foreach (html2spip_test_sources() as $nom => $source) {
	[$table, $cle] = HTML2SPIP_TEST_OBJETS_TABLES[$source['type']];
	if (!sql_countsel($table, "$cle=" . intval($source['id']))) {
		$absents[] = $nom;
	}
}
echo $absents
	? 'Corpus incomplet, objets absents : ' . join(', ', $absents) . "\n"
	: 'Corpus chargé : ' . count(html2spip_test_sources()) . " objets\n";
