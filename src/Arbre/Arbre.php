<?php
/**
 * Arbre HTML5 de html2spip
 *
 * Dom\HTMLDocument à partir de PHP 8.4 ; sinon Masterminds HTML5-PHP (PHP 8.1 à 8.3), chargé par Composer ou depuis
 * la copie livrée dans lib/masterminds-html5/. Seule cette classe connaît les classes du parseur : le reste de
 * html2spip manipule les nœuds par elle et par les propriétés et méthodes communes aux deux familles de classes
 * (localName, childNodes, textContent, data, attributes, getAttribute(), setAttribute(), hasAttribute(),
 * replaceWith(), remove(), appendChild(), before(), isConnected, ownerDocument).
 */

namespace Html2Spip\Arbre;

use ReflectionClass;
use RuntimeException;

final class Arbre
{
	/**
	 * Éléments sans balise fermante
	 */
	public const VIDES = array(
		'area', 'base', 'basefont', 'bgsound', 'br', 'col', 'embed', 'frame', 'hr', 'img', 'input', 'keygen', 'link',
		'meta', 'param', 'source', 'track', 'wbr',
	);

	/**
	 * Éléments dont le texte est sérialisé sans échappement
	 */
	public const TEXTE_BRUT = array('style', 'script', 'xmp', 'iframe', 'noembed', 'noframes', 'plaintext', 'noscript');

	private static bool $autoload = false;

	/**
	 * Body du HTML analysé en document HTML5
	 *
	 * @param string $html
	 * @return \Dom\Element|\DOMElement
	 * @throws RuntimeException aucun parseur HTML5 disponible
	 */
	public static function body($html) {
		if (class_exists('Dom\HTMLDocument')) {
			return \Dom\HTMLDocument::createFromString(self::source($html), LIBXML_NOERROR)->body;
		}
		if ($erreur = self::verifier()) {
			throw new RuntimeException($erreur);
		}
		return self::bodyMasterminds($html);
	}

	/**
	 * Body du HTML analysé par Masterminds, quelle que soit la version de PHP (tests des deux parseurs)
	 *
	 * @param string $html
	 * @return \DOMElement
	 */
	public static function bodyMasterminds($html) {
		self::chargerMasterminds();
		$arbre = new ConstructeurMasterminds(false, array('disable_html_ns' => true));
		$scanner = new \Masterminds\HTML5\Parser\Scanner(self::source($html), 'UTF-8');
		(new \Masterminds\HTML5\Parser\Tokenizer($scanner, $arbre, \Masterminds\HTML5\Parser\Tokenizer::CONFORMANT_HTML))->parse();
		return $arbre->document()->getElementsByTagName('body')->item(0);
	}

	/**
	 * @param mixed $noeud
	 * @return bool
	 */
	public static function estTexte($noeud) {
		return ($noeud instanceof \Dom\Text or $noeud instanceof \DOMText);
	}

	/**
	 * @param mixed $noeud
	 * @return bool
	 */
	public static function estElement($noeud) {
		return ($noeud instanceof \Dom\Element or $noeud instanceof \DOMElement);
	}

	/**
	 * HTML d'un nœud, contenu compris
	 *
	 * Dom\HTMLDocument::saveHtml() pour l'arbre natif ; sinon l'algorithme de sérialisation du standard HTML5, qui donne
	 * le même résultat (le sérialiseur de Masterminds écrit autrement les attributs booléens et le SVG).
	 *
	 * @param mixed $noeud
	 * @return string
	 */
	public static function serialiser($noeud) {
		if ($noeud instanceof \Dom\Node) {
			return $noeud->ownerDocument->saveHtml($noeud);
		}
		if (self::estTexte($noeud)) {
			$parent = $noeud->parentNode;
			if (self::estElement($parent) and in_array($parent->localName, self::TEXTE_BRUT)) {
				return $noeud->data;
			}
			return str_replace(array('&', "\u{a0}", '<', '>'), array('&amp;', '&nbsp;', '&lt;', '&gt;'), $noeud->data);
		}
		if (!self::estElement($noeud)) {
			return ($noeud->nodeType === XML_COMMENT_NODE) ? '<!--' . $noeud->data . '-->' : '';
		}
		$html = '<' . $noeud->localName;
		foreach ($noeud->attributes as $attribut) {
			$html .= ' ' . $attribut->nodeName . '="' . str_replace(array('&', "\u{a0}", '"'), array('&amp;', '&nbsp;', '&quot;'), $attribut->value) . '"';
		}
		$html .= '>';
		if (in_array($noeud->localName, self::VIDES)) {
			return $html;
		}
		foreach ($noeud->childNodes as $enfant) {
			$html .= self::serialiser($enfant);
		}
		return $html . '</' . $noeud->localName . '>';
	}

	/**
	 * Message si l'arbre HTML5 ne peut pas être construit
	 *
	 * @param bool $dom_natif Dom\HTMLDocument présent (PHP 8.4)
	 * @param bool $dom extension DOM chargée
	 * @param bool $masterminds Masterminds chargé, ou sa copie présente dans lib/
	 * @return string message, ou '' si un parseur est disponible
	 */
	public static function erreur($dom_natif, $dom, $masterminds) {
		if ($dom_natif) {
			return '';
		}
		if (!$dom) {
			return 'Extension PHP DOM absente (PHP ' . PHP_VERSION . ') : elle est nécessaire à la conversion du HTML.';
		}
		if (!$masterminds) {
			return 'Bibliothèque Masterminds HTML5-PHP introuvable (lib/masterminds-html5/ de html2spip) : elle est nécessaire à la conversion du HTML sous PHP ' . PHP_VERSION . ', avant PHP 8.4.';
		}
		return '';
	}

	/**
	 * @return string message, ou '' si un parseur est disponible
	 */
	public static function verifier() {
		return self::erreur(
			class_exists('Dom\HTMLDocument'),
			class_exists('DOMDocument'),
			class_exists('Masterminds\HTML5') or is_file(self::dossierMasterminds() . '/src/HTML5.php')
		);
	}

	/**
	 * Parseur utilisé, pour le mode verbeux
	 *
	 * @return string
	 */
	public static function parseur() {
		if (class_exists('Dom\HTMLDocument')) {
			return 'Dom\HTMLDocument (PHP ' . PHP_VERSION . ')';
		}
		self::chargerMasterminds();
		$fichier = realpath((new ReflectionClass('Masterminds\HTML5'))->getFileName());
		$copie = realpath(self::dossierMasterminds());
		if ($copie and str_starts_with($fichier, $copie . DIRECTORY_SEPARATOR)) {
			return 'Masterminds HTML5-PHP (PHP ' . PHP_VERSION . '), copie de html2spip, version ' . trim((string) file_get_contents($copie . '/VERSION'));
		}
		return 'Masterminds HTML5-PHP (PHP ' . PHP_VERSION . '), copie déjà chargée : ' . $fichier;
	}

	/**
	 * @param string $html
	 * @return string
	 */
	private static function source($html) {
		return '<!DOCTYPE html><html><body>' . $html . '</body></html>';
	}

	/**
	 * Dossier de la copie de Masterminds livrée avec html2spip
	 *
	 * @return string
	 */
	private static function dossierMasterminds() {
		return dirname(__DIR__, 2) . '/lib/masterminds-html5';
	}

	/**
	 * Rend les classes Masterminds disponibles : une copie déjà chargée (Composer, autre plugin) est utilisée telle
	 * quelle, sinon celle de lib/
	 */
	private static function chargerMasterminds() {
		if (self::$autoload or class_exists('Masterminds\HTML5')) {
			return;
		}
		self::$autoload = true;
		spl_autoload_register(function ($classe) {
			if (str_starts_with($classe, 'Masterminds\\')) {
				$fichier = self::dossierMasterminds() . '/src/' . str_replace('\\', '/', substr($classe, strlen('Masterminds\\'))) . '.php';
				if (is_file($fichier)) {
					require $fichier;
				}
			}
		});
	}
}
