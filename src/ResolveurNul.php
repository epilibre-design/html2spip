<?php
/**
 * Résolveur par défaut, hors SPIP : aucun objet n'est retrouvé
 */

namespace Html2Spip;

final class ResolveurNul implements Resolveur
{
	public function objet(string $url): ?array {
		return null;
	}

	public function titreAffiche(string $type, int $id): ?string {
		return null;
	}
}
