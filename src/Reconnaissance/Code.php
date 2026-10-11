<?php
/**
 * Passe Code : blocs dont SPIP garde le texte brut ou les lignes
 *
 * - <cadre> : textarea.spip_cadre, remplacé par le raccourci (texte brut) ;
 * - <poesie> : blockquote.spip_poesie, une ligne par div, réécrit en pre que l'écriture rend en <poesie> (le texte
 *   des lignes y est converti : gras, liens…).
 * <code> (code.spip_code) et <quote> (blockquote.spip) sont rendus par l'écriture.
 */

namespace Html2Spip\Reconnaissance;

use Html2Spip\Arbre\Arbre;
use Html2Spip\Arbre\Noeuds;
use Html2Spip\Contexte;

final class Code implements Passe
{
	public function appliquer($body, Contexte $contexte): void {
		foreach (Noeuds::descendants($body, 'textarea') as $cadre) {
			if (Noeuds::aClasse($cadre, 'spip_cadre')) {
				Noeuds::remplacer($cadre, "<cadre>\n" . trim($cadre->textContent, "\n") . "\n</cadre>", true);
			}
		}
		foreach (Noeuds::descendants($body, 'blockquote') as $poesie) {
			if (Noeuds::aClasse($poesie, 'spip_poesie')) {
				self::poesie($poesie);
			}
		}
	}

	/**
	 * @param \Dom\Element|\DOMElement $poesie blockquote.spip_poesie
	 */
	private static function poesie($poesie): void {
		$pre = Noeuds::creer($poesie, 'pre');
		foreach (iterator_to_array($poesie->childNodes) as $ligne) {
			if (!Arbre::estElement($ligne) or Noeuds::nom($ligne) !== 'div') {
				continue;
			}
			if ($pre->firstChild) {
				$pre->appendChild($poesie->ownerDocument->createTextNode("\n"));
			}
			foreach (iterator_to_array($ligne->childNodes) as $noeud) {
				$pre->appendChild($noeud);
			}
		}
		$poesie->replaceWith($pre);
	}
}
