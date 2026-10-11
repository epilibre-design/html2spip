<?php
/**
 * Passe Tableaux : résumé d'un tableau rendu par SPIP (caption small.summary, après un saut de ligne), sorti de la
 * légende vers l'attribut data-h2s-resume du tableau, que l'écriture rend en || légende | résumé ||
 *
 * Le reste du tableau (en-têtes, cellules fusionnées) est rendu par l'écriture ; les attributs id, headers,
 * aria-describedby et les classes de rangées sont ignorés.
 */

namespace Html2Spip\Reconnaissance;

use Html2Spip\Arbre\Arbre;
use Html2Spip\Arbre\Noeuds;
use Html2Spip\Contexte;

final class Tableaux implements Passe
{
	public function appliquer($body, Contexte $contexte): void {
		foreach (Noeuds::descendants($body, 'caption') as $legende) {
			foreach (Noeuds::descendants($legende, 'small') as $resume) {
				if (!Noeuds::aClasse($resume, 'summary')) {
					continue;
				}
				$legende->parentNode->setAttribute('data-h2s-resume', Noeuds::texteNormalise($resume->textContent));
				$resume->remove();
				self::finDeLegende($legende);
			}
		}
	}

	/**
	 * Blancs et saut de ligne qui précédaient le résumé, retirés de la fin de la légende
	 *
	 * @param \Dom\Element|\DOMElement $legende
	 */
	private static function finDeLegende($legende): void {
		while ($dernier = $legende->lastChild) {
			// && et non and : and est moins prioritaire que l'affectation
			$blanc = Arbre::estTexte($dernier) && trim($dernier->data) === '';
			$saut = Arbre::estElement($dernier) && Noeuds::nom($dernier) === 'br';
			if (!$blanc and !$saut) {
				return;
			}
			$dernier->remove();
		}
	}
}
