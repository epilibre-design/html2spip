<?php
/**
 * Relie les URL du site aux objets SPIP, et donne le titre que SPIP affiche d'un objet
 */

namespace Html2Spip;

interface Resolveur
{
	/**
	 * Objet désigné par une URL du site
	 *
	 * @param string $url sans ancre
	 * @return array{type: string, id: int}|null null si l'URL ne désigne pas un objet existant
	 */
	public function objet(string $url): ?array;

	/**
	 * Titre tel que SPIP l'affiche comme texte d'un lien vers l'objet (supprimer_numero + typo)
	 *
	 * @return string|null HTML, ou null si l'objet n'existe pas
	 */
	public function titreAffiche(string $type, int $id): ?string;
}
