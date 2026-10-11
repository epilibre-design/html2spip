<?php
/**
 * Point d'entrée : un fragment HTML en texte SPIP
 *
 * Analyse en arbre HTML5, passes de reconnaissance dans l'ordre de Options::PASSES, puis écriture.
 */

namespace Html2Spip;

use Html2Spip\Arbre\Arbre;
use Html2Spip\Ecriture\Ecrivain;

final class Convertisseur
{
	/**
	 * Classe de chaque passe de Options::PASSES
	 */
	private const CLASSES = array(
		'code' => Reconnaissance\Code::class,
		'notes' => Reconnaissance\Notes::class,
		'liens' => Reconnaissance\Liens::class,
		'tableaux' => Reconnaissance\Tableaux::class,
		'typographie' => Reconnaissance\Typographie::class,
	);

	private array $avertissements = array();

	public function __construct(private Options $options = new Options()) {
	}

	public function convertir(string $html): string {
		$html = str_replace(array("\r\n", "\r"), "\n", $html);
		if (trim($html) === '') {
			$this->avertissements = array();
			return '';
		}
		return $this->convertirBody(Arbre::body($html));
	}

	/**
	 * Body déjà analysé (tests des deux parseurs)
	 *
	 * @param \Dom\Element|\DOMElement $body
	 */
	public function convertirBody($body): string {
		$contexte = new Contexte($this->options);
		foreach (Options::PASSES as $passe) {
			if (isset(self::CLASSES[$passe]) and $this->options->passeActive($passe)) {
				$classe = self::CLASSES[$passe];
				(new $classe())->appliquer($body, $contexte);
			}
		}
		$texte = Ecrivain::convertir_body($body, $this->options->ecriture + array('autop' => false));
		$this->avertissements = $contexte->avertissements();
		return $texte;
	}

	/**
	 * Avertissements de la dernière conversion
	 *
	 * @return string[]
	 */
	public function avertissements(): array {
		return $this->avertissements;
	}
}
