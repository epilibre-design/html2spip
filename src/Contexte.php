<?php
/**
 * État d'une conversion : options, avertissements des passes
 */

namespace Html2Spip;

final class Contexte
{
	private array $avertissements = array();

	public function __construct(public readonly Options $options) {
	}

	/**
	 * Structure abandonnée ou incomplète (lien non résolu, note sans appel, paramètres non déductibles…)
	 */
	public function avertir(string $message): void {
		$this->avertissements[] = $message;
	}

	/**
	 * @return string[]
	 */
	public function avertissements(): array {
		return $this->avertissements;
	}
}
