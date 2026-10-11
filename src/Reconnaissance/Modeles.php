<?php
/**
 * Passe Modèles : HTML des modèles dist doc, img, emb de SPIP 4.4, rendu en <docN|align>
 *
 * La passe reconnaît une forme HTML (annexe B de la spec), pas l'origine du modèle : un modèle de squelette ou de
 * plugin qui produit la même structure est converti de la même façon. Seul l'alignement est retrouvé ; les autres
 * paramètres (alt, lien, largeur…) sont perdus. Tout autre modèle passe à la conversion générique.
 */

namespace Html2Spip\Reconnaissance;

use Html2Spip\Arbre\Noeuds;
use Html2Spip\Contexte;

final class Modeles implements Passe
{
	/**
	 * Alignement que SPIP donne à <docN> sans paramètre (annexe B) : non écrit
	 */
	private const ALIGNEMENT_PAR_DEFAUT = 'center';

	public function appliquer($body, Contexte $contexte): void {
		foreach (Noeuds::descendants($body, 'div') as $div) {
			if (!Noeuds::connecte($div)) {
				continue;
			}
			$raccourci = self::raccourci(Noeuds::classes($div));
			if ($raccourci !== null) {
				Noeuds::remplacer($div, $raccourci, true);
			}
		}
	}

	/**
	 * @param string[] $classes classes du div
	 * @return string|null <docN|align>, ou null si ce n'est pas le modèle d'un document
	 */
	private static function raccourci(array $classes): ?string {
		if (!in_array('spip_document', $classes, true)) {
			return null;
		}
		$id = null;
		$align = self::ALIGNEMENT_PAR_DEFAUT;
		foreach ($classes as $classe) {
			if (preg_match('/^spip_document_(\d+)$/', $classe, $m)) {
				$id = $m[1];
			} elseif (preg_match('/^spip_documents_(left|right|center)$/', $classe, $m)) {
				$align = $m[1];
			}
		}
		if ($id === null) {
			return null;
		}
		return ($align === self::ALIGNEMENT_PAR_DEFAUT) ? "<doc$id>" : "<doc$id|$align>";
	}
}
