<?php
/**
 * Passe Typographie : retire ce que la typographie de SPIP ajoute et remettra au rendu (annexe A de la spec)
 *
 * - apostrophe typographique ’ : apostrophe droite, que SPIP rend de nouveau en ’ ;
 * - espace insécable à un endroit où SPIP en ajoute une : espace normale ;
 * - toute autre espace insécable : ~, nœud raccourci (l'écriture échappe un ~ du texte).
 * Comme corriger_typo(), le texte du code et les attributs ne sont pas traités.
 */

namespace Html2Spip\Reconnaissance;

use Html2Spip\Arbre\Arbre;
use Html2Spip\Arbre\Noeuds;
use Html2Spip\Contexte;

final class Typographie implements Passe
{
	/**
	 * Éléments dont le texte n'est pas traité
	 */
	private const PROTEGES = array('code', 'textarea', 'script', 'style', Noeuds::RACCOURCI);

	private const INSECABLE = "\u{a0}";

	public function appliquer($body, Contexte $contexte): void {
		$fr = self::tableFr($contexte->options->langue);
		foreach (self::textes($body) as $texte) {
			self::traiter($texte, $fr);
		}
	}

	/**
	 * Table de typographie de SPIP pour une langue, comme lang_typo() (ecrire/inc/lang.php)
	 *
	 * @return bool true : table fr ; false : table en
	 */
	public static function tableFr(string $langue): bool {
		return in_array($langue, array('fr', 'eo', 'cpf'), true) or str_starts_with($langue, 'fr_');
	}

	/**
	 * Espace insécable ajoutée par la typographie de SPIP (annexe A, règles fr 3 à 6, en 2 et en 2 bis : celles de
	 * plugins-dist/textwheel/typographie/, qui remplacent celles d'ecrire/typographie/)
	 *
	 * @param string $avant texte qui précède l'insécable
	 * @param string $apres texte qui la suit
	 * @param bool $fr table fr
	 */
	public static function ajoutee(string $avant, string $apres, bool $fr): bool {
		// Communes aux deux tables : devant -, --, et %, après Mr et Mr.
		if (preg_match('/^(?:--?,|%(?:\W|$))/u', $apres) or preg_match('/Mr\.?$/u', $avant)) {
			return true;
		}
		if (!$fr) {
			return false;
		}
		return preg_match('/^(?:[;!?»]|:(?!:)(?:\W|$)|%(?:\W|$))/u', $apres)
			or preg_match('/(?:«|M(?:M?\.|mes?|r\.?)|[MnN]°)$/u', $avant);
	}

	/**
	 * Nœuds texte hors des éléments protégés
	 *
	 * @param \Dom\Node|\DOMNode $racine
	 * @return array
	 */
	private static function textes($racine): array {
		$textes = array();
		foreach ($racine->childNodes as $noeud) {
			if (Arbre::estTexte($noeud)) {
				$textes[] = $noeud;
			}
			elseif (Arbre::estElement($noeud) and !in_array(Noeuds::nom($noeud), self::PROTEGES, true)) {
				array_push($textes, ...self::textes($noeud));
			}
		}
		return $textes;
	}

	/**
	 * @param \Dom\Text|\DOMText $noeud
	 * @param bool $fr
	 */
	private static function traiter($noeud, bool $fr): void {
		$morceaux = explode(self::INSECABLE, str_replace('’', "'", $noeud->data));
		// Texte découpé aux insécables voulues, les autres devenues des espaces
		$segments = array();
		$courant = array_shift($morceaux);
		foreach ($morceaux as $morceau) {
			if (self::ajoutee($courant, $morceau, $fr)) {
				$courant .= ' ' . $morceau;
			}
			else {
				$segments[] = $courant;
				$courant = $morceau;
			}
		}
		$segments[] = $courant;
		$noeud->data = array_shift($segments);
		$precedent = $noeud;
		foreach ($segments as $segment) {
			$tilde = Noeuds::creer($noeud, Noeuds::RACCOURCI);
			$tilde->setAttribute('data-spip', '~');
			$tilde->setAttribute('data-bloc', '0');
			$suite = $noeud->ownerDocument->createTextNode($segment);
			$precedent->after($tilde, $suite);
			$precedent = $suite;
		}
	}
}
