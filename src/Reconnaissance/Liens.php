<?php
/**
 * Passe Liens : liens rendus par SPIP (annexe B de la spec)
 *
 * - lien interne (spip_in, ou sans classe vers un objet absent) : href réécrit en raccourci d'objet (40, rub1, doc1…),
 *   texte retiré s'il est le titre que SPIP affiche, bulle gardée ;
 * - URL nue (spip_url auto) : texte ; courriel (spip_mail) : [->adresse] si le texte est l'adresse ;
 * - glossaire (spip_glossaire) : [?terme] si le texte est le terme ;
 * - lien externe (spip_out), appel de note (spip_note) et lien quelconque : laissés à l'écriture ([texte->url]) ou à
 *   la passe Notes.
 */

namespace Html2Spip\Reconnaissance;

use Html2Spip\Arbre\Noeuds;
use Html2Spip\Contexte;

final class Liens implements Passe
{
	/**
	 * Préfixe du raccourci de chaque type d'objet
	 */
	public const RACCOURCIS = array(
		'article' => '', 'rubrique' => 'rub', 'breve' => 'br', 'auteur' => 'aut', 'mot' => 'mot', 'site' => 'site',
		'syndic' => 'site', 'document' => 'doc',
	);

	private const TYPES = 'article|rubrique|breve|auteur|mot|site|syndic|document';

	public function appliquer($body, Contexte $contexte): void {
		foreach (Noeuds::descendants($body, 'a') as $lien) {
			if (!$lien->hasAttribute('href') or Noeuds::aClasse($lien, 'spip_note')) {
				continue;
			}
			$url = trim((string) $lien->getAttribute('href'));
			if (Noeuds::aClasse($lien, 'spip_glossaire')) {
				self::glossaire($lien, $url, $contexte);
			}
			elseif (Noeuds::aClasse($lien, 'auto')) {
				$lien->replaceWith($lien->ownerDocument->createTextNode($lien->textContent));
			}
			elseif (str_starts_with($url, 'mailto:')) {
				self::courriel($lien, $url);
			}
			elseif (!Noeuds::aClasse($lien, 'spip_out')) {
				self::interne($lien, $url, $contexte);
			}
		}
	}

	/**
	 * Objet lisible dans une URL de SPIP, sans résolveur : spip.php?article12, article12.html, spip.php?page=article&id_article=12
	 *
	 * @return array{type: string, id: int}|null
	 */
	public static function objetDansUrl(string $url): ?array {
		if (preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $url)) {
			return null;
		}
		if (preg_match('#(?:^|/)spip\.php\?(' . self::TYPES . ')(\d+)(?:&|$)#', $url, $m)
			or preg_match('#(?:^|/)(' . self::TYPES . ')(\d+)\.html$#', $url, $m)) {
			return array('type' => self::type($m[1]), 'id' => intval($m[2]));
		}
		parse_str((string) parse_url($url, PHP_URL_QUERY), $requete);
		$page = $requete['page'] ?? '';
		if (!preg_match('#^(?:' . self::TYPES . ')$#', $page)) {
			return null;
		}
		$cle = ($page === 'site') ? 'id_syndic' : "id_$page";
		return isset($requete[$cle]) ? array('type' => self::type($page), 'id' => intval($requete[$cle])) : null;
	}

	private static function type(string $type): string {
		return ($type === 'syndic') ? 'site' : $type;
	}

	/**
	 * @param \Dom\Element|\DOMElement $lien
	 */
	private static function interne($lien, string $url, Contexte $contexte): void {
		[$adresse, $ancre] = array_pad(explode('#', $url, 2), 2, '');
		$objet = ($adresse !== '') ? $contexte->options->resolveur->objet($adresse) : null;
		$trouve = ($objet !== null);
		$objet ??= ($adresse !== '') ? self::objetDansUrl($adresse) : null;
		if ($objet === null) {
			if (Noeuds::aClasse($lien, 'spip_in')) {
				$contexte->avertir("Lien interne non résolu : $url");
			}
			return;
		}
		if (!$trouve) {
			$contexte->avertir("Lien vers un objet absent ou non résolu : $url");
		}
		$titre = $trouve ? $contexte->options->resolveur->titreAffiche($objet['type'], $objet['id']) : null;
		// Objet absent : SPIP affiche « type id » (article 999)
		$titre = ($titre !== null) ? Noeuds::texteHtml($titre) : $objet['type'] . ' ' . $objet['id'];
		$lien->setAttribute('href', self::RACCOURCIS[$objet['type']] . $objet['id'] . ($ancre !== '' ? "#$ancre" : ''));
		$bulle = trim((string) $lien->getAttribute('title'));
		if ($bulle !== '') {
			$lien->setAttribute('data-h2s-bulle', $bulle);
		}
		elseif (Noeuds::texteNormalise($lien->textContent) === $titre) {
			while ($lien->firstChild) {
				$lien->firstChild->remove();
			}
		}
	}

	/**
	 * @param \Dom\Element|\DOMElement $lien
	 */
	private static function courriel($lien, string $url): void {
		$adresse = substr($url, strlen('mailto:'));
		if (Noeuds::texteNormalise($lien->textContent) === $adresse) {
			$lien->setAttribute('href', $adresse);
			while ($lien->firstChild) {
				$lien->firstChild->remove();
			}
		}
	}

	/**
	 * @param \Dom\Element|\DOMElement $lien
	 */
	private static function glossaire($lien, string $url, Contexte $contexte): void {
		$terme = str_replace('_', ' ', rawurldecode((string) basename((string) parse_url($url, PHP_URL_PATH))));
		if ($terme !== '' and Noeuds::texteNormalise($lien->textContent) === $terme) {
			Noeuds::remplacer($lien, "[?$terme]", false);
			return;
		}
		$contexte->avertir("Lien de glossaire dont le texte n’est pas le terme : $url");
	}
}
