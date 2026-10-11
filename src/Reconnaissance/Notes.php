<?php
/**
 * Passe Notes : notes recousues à leur appel, si l'appel et la note sont dans le même fragment
 *
 * Appel : span.spip_note_ref contenant a.spip_note[href=#nbN] ; note : div#nbN (rendue par #NOTES, dans un
 * div.notes ou non), dont le premier paragraphe commence par le renvoi span.spip_note_ref > a[href=#nhN].
 * L'appel devient un nœud h2s-note qui reçoit le contenu de la note, sans le renvoi ; data-numero pour un numéro
 * qui n'est pas le suivant des numéros automatiques (note numérotée à la main). Appel sans note : retiré ; note sans
 * appel : gardée en paragraphes à la fin du texte ; avertissement dans les deux cas.
 */

namespace Html2Spip\Reconnaissance;

use Html2Spip\Arbre\Arbre;
use Html2Spip\Arbre\Noeuds;
use Html2Spip\Contexte;

final class Notes implements Passe
{
	public function appliquer($body, Contexte $contexte): void {
		$notes = array();
		foreach (Noeuds::descendants($body, 'div') as $div) {
			if (preg_match('/^nb(.+)$/', (string) $div->getAttribute('id'), $m) and self::renvoi($div) !== null) {
				$notes[$m[1]] = $div;
			}
		}
		$suivant = 1;
		foreach (Noeuds::descendants($body, 'a') as $appel) {
			if (!Noeuds::connecte($appel) or !Noeuds::aClasse($appel, 'spip_note') or !preg_match('/^#nb(.+)$/', (string) $appel->getAttribute('href'), $m)) {
				continue;
			}
			$numero = $m[1];
			$conteneur = Noeuds::aClasse($appel->parentNode, 'spip_note_ref') ? $appel->parentNode : $appel;
			if (!isset($notes[$numero])) {
				$contexte->avertir("Appel de note sans note dans le fragment : $numero");
				$conteneur->remove();
				continue;
			}
			$note = Noeuds::creer($appel, Noeuds::NOTE);
			if ($numero === (string) $suivant) {
				$suivant++;
			}
			else {
				$note->setAttribute('data-numero', $numero);
			}
			$div = $notes[$numero];
			unset($notes[$numero]);
			self::renvoi($div)->remove();
			foreach (iterator_to_array($div->childNodes) as $enfant) {
				$note->appendChild($enfant);
			}
			$div->remove();
			$conteneur->replaceWith($note);
		}
		foreach ($notes as $numero => $div) {
			$contexte->avertir("Note sans appel dans le fragment : $numero");
			self::renvoi($div)->remove();
			foreach (iterator_to_array($div->childNodes) as $enfant) {
				$body->appendChild($enfant);
			}
			$div->remove();
		}
		foreach (Noeuds::descendants($body, 'div') as $div) {
			if (Noeuds::aClasse($div, 'notes') and trim($div->textContent) === '') {
				$div->remove();
			}
		}
	}

	/**
	 * Renvoi d'une note vers son appel
	 *
	 * @param \Dom\Element|\DOMElement $div
	 * @return \Dom\Element|\DOMElement|null span.spip_note_ref, ou le lien seul
	 */
	private static function renvoi($div) {
		foreach (Noeuds::descendants($div, 'a') as $lien) {
			if (Noeuds::aClasse($lien, 'spip_note') and str_starts_with((string) $lien->getAttribute('href'), '#nh')) {
				return Noeuds::aClasse($lien->parentNode, 'spip_note_ref') ? $lien->parentNode : $lien;
			}
		}
		return null;
	}
}
