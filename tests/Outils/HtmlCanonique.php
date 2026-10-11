<?php
/**
 * Forme canonique d'un fragment HTML, pour comparer deux rendus de SPIP
 *
 * Les deux fragments sont analysés par le même analyseur HTML5 : entités décodées en caractères UTF-8, attributs
 * triés, nœuds texte faits seulement de blancs retirés (hors pre, textarea, code). Les blancs à l'intérieur du texte
 * sont comparés tels quels. Un retour à la ligne suit chaque fin de bloc, pour des différences lisibles.
 */

declare(strict_types=1);

namespace Html2Spip\Tests\Outils;

use Html2Spip\Arbre\Arbre;

final class HtmlCanonique
{
	private const TELS_QUELS = array('pre', 'textarea', 'code');

	private const BLOCS = array(
		'p', 'div', 'li', 'ul', 'ol', 'dl', 'dt', 'dd', 'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'blockquote',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'figure', 'figcaption', 'hr', 'br', 'pre', 'textarea',
	);

	public static function de(string $html): string {
		return self::enfants(Arbre::body($html), false);
	}

	private static function enfants($parent, bool $brut): string {
		$html = '';
		foreach ($parent->childNodes as $noeud) {
			$html .= self::noeud($noeud, $brut);
		}
		return $html;
	}

	private static function noeud($noeud, bool $brut): string {
		if (Arbre::estTexte($noeud)) {
			if (!$brut and trim($noeud->data, " \t\n\r\f") === '') {
				return '';
			}
			return htmlspecialchars($noeud->data, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
		}
		if (!Arbre::estElement($noeud)) {
			return '';
		}
		$nom = strtolower($noeud->localName);
		$attributs = array();
		foreach ($noeud->attributes as $attribut) {
			$attributs[$attribut->nodeName] = $attribut->value;
		}
		ksort($attributs);
		$html = "<$nom";
		foreach ($attributs as $attribut => $valeur) {
			$html .= " $attribut=\"" . htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
		}
		$html .= '>';
		$fin = in_array($nom, self::BLOCS, true) ? "\n" : '';
		if (in_array($nom, Arbre::VIDES, true)) {
			return $html . $fin;
		}
		return $html . self::enfants($noeud, $brut or in_array($nom, self::TELS_QUELS, true)) . "</$nom>" . $fin;
	}
}
