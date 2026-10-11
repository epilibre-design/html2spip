<?php
/**
 * Constructeur d'arbre de Masterminds HTML5-PHP complété des règles HTML5 qu'il n'applique pas et dont l'absence
 * changerait le texte converti : section et rangée implicites d'un tableau, paragraphe vide pour un </p> orphelin
 *
 * Chargé par Arbre::bodyMasterminds(), une fois Masterminds disponible.
 */

namespace Html2Spip\Arbre;

use DOMElement;
use Masterminds\HTML5\Parser\DOMTreeBuilder;

class ConstructeurMasterminds extends DOMTreeBuilder
{
	public function startTag($name, $attributes = array(), $selfClosing = false) {
		$nom = strtolower($name);
		$parent = ($this->current instanceof DOMElement) ? $this->current->localName : '';
		// Rangée ou cellule directement dans le tableau : dans un tbody implicite
		if (in_array($nom, array('tr', 'td', 'th')) and $parent === 'table') {
			parent::startTag('tbody');
			$parent = 'tbody';
		}
		// Cellule directement dans une section : dans une rangée implicite
		if (in_array($nom, array('td', 'th')) and in_array($parent, array('tbody', 'thead', 'tfoot'))) {
			parent::startTag('tr');
		}
		return parent::startTag($name, $attributes, $selfClosing);
	}

	public function endTag($name) {
		// </p> sans paragraphe ouvert : un paragraphe vide, qui sépare le texte qui l'entoure
		if (strtolower($name) === 'p' and !$this->isAncestor('p')) {
			parent::startTag('p');
		}
		parent::endTag($name);
	}
}
