<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Convertisseur;
use Html2Spip\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Passe Code (cadre, poésie) et structures rendues par SPIP 4.4 que l'écriture convertit seule (annexe B de la spec)
 */
final class CodeTest extends TestCase
{
	public static function rendus(): array
	{
		return array(
			'cadre' => array("<textarea readonly='readonly' cols='40' rows='2' class='spip_cadre spip_cadre_block' dir='ltr'>a {{b}}\nif (a &lt; b)</textarea>", "<cadre>\na {{b}}\nif (a < b)\n</cadre>"),
			'zone de texte quelconque' => array('<textarea>x</textarea>', '<textarea>x</textarea>'),
			'poésie' => array("<blockquote class=\"spip_poesie\"><div>v1</div>\n<div>v2 <strong>g</strong></div>\n</blockquote>", "<poesie>\nv1\nv2 {{g}}\n</poesie>"),
			'citation' => array("<blockquote class=\"spip\">\n<p>cité</p>\n</blockquote>", "<quote>\ncité\n</quote>"),
			'code en ligne' => array("<p><code class='spip_code spip_code_inline' dir='ltr'>x&lt;y</code></p>", '<code>x<y</code>'),
			'intertitre' => array('<h2 class="spip">Inter</h2>', '{{{Inter}}}'),
			'listes' => array("<ul class=\"spip\" role=\"list\"><li> a\n<ul class=\"spip\" role=\"list\"><li> b</li></ul></li><li> c</li></ul>", "-* a\n-** b\n-* c"),
			'filet' => array('<hr class="spip" />', '----'),
			'retours' => array("<p>l1<br class='autobr' />\nl2<br class='manualbr' />l3</p>", "l1\nl2\n_ l3"),
			'ancre' => array('<p><a id="ici"></a> texte</p>', '[ici<-] texte'),
		);
	}

	#[DataProvider('rendus')]
	public function testRendu(string $html, string $attendu): void
	{
		$this->assertSame($attendu, (new Convertisseur(new Options(passes: array('code'))))->convertir($html));
	}

	public function testSansLaPasse(): void
	{
		// Cadre gardé en HTML par l'écriture
		$html = "<textarea class='spip_cadre'>x</textarea>";
		$this->assertSame('<textarea class="spip_cadre">x</textarea>', (new Convertisseur(new Options(passes: array())))->convertir($html));
	}
}
