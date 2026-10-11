<?php
/**
 * Passe de reconnaissance : remplace dans l'arbre des structures produites par SPIP par des nœuds que l'écriture
 * sait lire (Html2Spip\Arbre\Noeuds), ou réécrit l'arbre. Ce qui n'est pas reconnu est laissé intact.
 */

namespace Html2Spip\Reconnaissance;

use Html2Spip\Contexte;

interface Passe
{
	/**
	 * @param \Dom\Element|\DOMElement $body
	 */
	public function appliquer($body, Contexte $contexte): void;
}
