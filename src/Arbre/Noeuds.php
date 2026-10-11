<?php
/**
 * Nœuds de l'arbre partagés par les passes de reconnaissance et l'écriture
 *
 * Une passe remplace une structure reconnue par un nœud privé que l'écriture sait lire : h2s-raccourci (texte SPIP
 * déjà écrit), h2s-note (contenu d'une note, à écrire entre [[ et ]]).
 */

namespace Html2Spip\Arbre;

final class Noeuds
{
	/**
	 * Texte SPIP déjà écrit : attributs data-spip (le texte) et data-bloc (1 : sur son propre paragraphe)
	 */
	public const RACCOURCI = 'h2s-raccourci';

	/**
	 * Note : contient les nœuds de la note ; attribut data-numero pour une note numérotée à la main
	 */
	public const NOTE = 'h2s-note';

	/**
	 * Nouvel élément dans le document d'un nœud
	 *
	 * @param \Dom\Node|\DOMNode $voisin
	 * @param string $nom
	 * @return \Dom\Element|\DOMElement
	 */
	public static function creer($voisin, string $nom) {
		return $voisin->ownerDocument->createElement($nom);
	}

	/**
	 * Remplace un nœud par un nœud raccourci
	 *
	 * @param \Dom\Node|\DOMNode $noeud
	 * @param string $spip texte SPIP
	 * @param bool $bloc sur son propre paragraphe
	 */
	public static function remplacer($noeud, string $spip, bool $bloc): void {
		$raccourci = self::creer($noeud, self::RACCOURCI);
		$raccourci->setAttribute('data-spip', $spip);
		$raccourci->setAttribute('data-bloc', $bloc ? '1' : '0');
		$noeud->replaceWith($raccourci);
	}

	/**
	 * @param \Dom\Element|\DOMElement $element
	 * @return string nom en minuscules
	 */
	public static function nom($element): string {
		return strtolower($element->localName);
	}

	/**
	 * @param \Dom\Element|\DOMElement $element
	 * @return string[]
	 */
	public static function classes($element): array {
		return preg_split('/\s+/', trim((string) $element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * @param mixed $noeud
	 * @param string $classe
	 * @return bool faux pour un nœud qui n'est pas un élément
	 */
	public static function aClasse($noeud, string $classe): bool {
		return Arbre::estElement($noeud) and in_array($classe, self::classes($noeud), true);
	}

	/**
	 * Éléments descendants, dans l'ordre du document, en liste figée : l'arbre peut être modifié pendant le parcours
	 * (un élément retiré garde sa place dans la liste ; isConnected dit s'il est encore dans le document)
	 *
	 * @param \Dom\Element|\DOMElement $racine
	 * @param string $nom nom d'élément, ou * pour tous
	 * @return array
	 */
	public static function descendants($racine, string $nom = '*'): array {
		return iterator_to_array($racine->getElementsByTagName($nom), false);
	}

	/**
	 * Texte comparable : blancs, espaces insécables et fines compris, réduits à une espace ; bords retirés
	 *
	 * @param string $texte
	 * @return string
	 */
	public static function texteNormalise(string $texte): string {
		return trim(preg_replace('/[\s\x{a0}\x{202f}]+/u', ' ', $texte));
	}

	/**
	 * Texte comparable d'un HTML (titre affiché par SPIP…) : balises retirées, entités décodées, blancs réduits
	 *
	 * @param string $html
	 * @return string
	 */
	public static function texteHtml(string $html): string {
		return self::texteNormalise(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}
}
