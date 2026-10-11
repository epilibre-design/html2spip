<?php
/**
 * Options d'une conversion
 */

namespace Html2Spip;

final class Options
{
	/**
	 * Passes de reconnaissance, dans leur ordre d'application
	 */
	public const PASSES = array('code', 'notes', 'modeles', 'liens', 'tableaux', 'typographie');

	/**
	 * @param Resolveur $resolveur objets du site (liens internes)
	 * @param string $langue langue du texte : choisit la typographie à inverser (fr pour fr, fr_*, eo, cpf ; en sinon)
	 * @param array|null $passes passes actives (noms de PASSES) ; null : toutes ; array() : écriture seule
	 * @param array $ecriture options de l'écriture (Ecrivain::spip()) : autop
	 */
	public function __construct(
		public Resolveur $resolveur = new ResolveurNul(),
		public string $langue = 'fr',
		public ?array $passes = null,
		public array $ecriture = array(),
	) {
	}

	public function passeActive(string $passe): bool {
		return $this->passes === null or in_array($passe, $this->passes, true);
	}
}
