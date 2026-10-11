<?php
/**
 * Résolveur d'un site SPIP : URL décodées par urls_decoder_url(), objets et titres lus en base
 *
 * Utilisable seulement dans SPIP.
 */

namespace Html2Spip\Spip;

use Html2Spip\Resolveur;

final class ResolveurSpip implements Resolveur
{
	public function objet(string $url): ?array {
		include_spip('inc/urls');
		$adresse = self::locale($url);
		if ($adresse === null or $adresse === '') {
			return null;
		}
		// Document : lien vers son fichier
		if (str_starts_with($adresse, 'IMG/')) {
			$id = sql_getfetsel('id_document', 'spip_documents', 'fichier=' . sql_quote(substr($adresse, strlen('IMG/'))));
			return $id ? array('type' => 'document', 'id' => intval($id)) : null;
		}
		[$fond, $contexte] = array_pad((array) urls_decoder_url($adresse), 2, array());
		$type = $fond ? objet_type($fond) : '';
		$cle = $type ? id_table_objet($type) : '';
		if (!$type or empty($contexte[$cle])) {
			// spip.php?page=article&id_article=3 : non décodé par urls_decoder_url()
			parse_str((string) parse_url($adresse, PHP_URL_QUERY), $contexte);
			$type = isset($contexte['page']) ? objet_type($contexte['page']) : '';
			$cle = $type ? id_table_objet($type) : '';
			if (!$type or empty($contexte[$cle])) {
				return null;
			}
		}
		$id = intval($contexte[$cle]);
		return sql_countsel(table_objet_sql($type), "$cle=$id") ? array('type' => $type, 'id' => $id) : null;
	}

	public function titreAffiche(string $type, int $id): ?string {
		include_spip('inc/lien');
		include_spip('inc/texte');
		$titre = traiter_raccourci_titre($id, $type);
		return isset($titre['titre']) ? typo($titre['titre']) : null;
	}

	/**
	 * Adresse relative au site, ou null pour une adresse d'un autre site
	 */
	private static function locale(string $url): ?string {
		$base = url_de_base();
		if (str_starts_with($url, $base)) {
			return substr($url, strlen($base));
		}
		if (preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $url)) {
			return null;
		}
		return ltrim(preg_replace('#^\./#', '', $url), '/');
	}
}
