<?php
/**
 * Options d'une conversion dans un site SPIP : résolveur du site
 */

namespace Html2Spip\Spip;

use Html2Spip\Options;

final class OptionsSpip
{
	/**
	 * @param string $langue langue du texte (typographie à inverser)
	 */
	public static function options(string $langue = 'fr'): Options {
		return new Options(resolveur: new ResolveurSpip(), langue: $langue);
	}
}
