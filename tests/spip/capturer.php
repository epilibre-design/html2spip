<?php
/**
 * Capture le rendu de chaque article du corpus pour le type d'URL HTML2SPIP_TYPE_URL, dans
 * tests/corpus/captures/<type_url>/<nom>.html
 *
 * Lancé par scripts/capturer.sh, un processus par type d'URL.
 */

include_once __DIR__ . '/corpus.php';
include_once __DIR__ . '/rendu.php';

$type_url = html2spip_test_type_url();
$dossier = dirname(__DIR__) . "/corpus/captures/$type_url";
@mkdir($dossier, 0777, true);
foreach (html2spip_test_sources('article') as $nom => $source) {
	file_put_contents("$dossier/$nom.html", html2spip_test_rendu($source['texte'], $source['lang'] ?? 'fr') . "\n");
}
echo "Captures $type_url : " . count(html2spip_test_sources('article')) . " articles\n";
