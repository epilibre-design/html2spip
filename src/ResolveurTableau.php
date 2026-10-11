<?php
/**
 * Résolveur dont les données sont passées en tableaux : tests, usage hors SPIP
 */

namespace Html2Spip;

final class ResolveurTableau implements Resolveur
{
	/**
	 * @param array $objets URL => array('type' => …, 'id' => …)
	 * @param array $titres "type/id" => titre affiché (HTML)
	 */
	public function __construct(
		private array $objets = array(),
		private array $titres = array(),
	) {
	}

	public function objet(string $url): ?array {
		return $this->objets[$url] ?? null;
	}

	public function titreAffiche(string $type, int $id): ?string {
		return $this->titres["$type/$id"] ?? null;
	}
}
