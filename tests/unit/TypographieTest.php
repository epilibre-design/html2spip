<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Convertisseur;
use Html2Spip\Options;
use Html2Spip\Reconnaissance\Typographie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Inversion de la typographie de SPIP 4.4 (annexe A de la spec)
 */
final class TypographieTest extends TestCase
{
	public static function francais(): array
	{
		return array(
			// Rendu de SPIP 4.4.28 (annexe B) : « C'est: oui; non! quoi? « a » Mme X n° 5 10 % a -- b 10:30 a~b »
			'annexe B' => array(
				'<p>C&#8217;est&nbsp;: oui&nbsp;; non&nbsp;! quoi&nbsp;? &#171;&nbsp;a&nbsp;&#187; Mme&nbsp;X n&#176;&nbsp;5 10&nbsp;% a &mdash; b 10:30 a&nbsp;b</p>',
				"C'est : oui ; non ! quoi ? « a » Mme X n° 5 10 % a — b 10:30 a~b",
			),
			'abréviations' => array('<p>M.&nbsp;A, MM.&nbsp;B, Mmes&nbsp;C, Mr&nbsp;D, N&#176;&nbsp;3</p>', 'M. A, MM. B, Mmes C, Mr D, N° 3'),
			'tiret virgule' => array('<p>a&nbsp;-, b&nbsp;--, c</p>', 'a -, b --, c'),
			'deux points suivis d’un mot' => array('<p>a&nbsp;:b</p>', 'a~:b'),
			'pour cent suivi d’un mot' => array('<p>10&nbsp;%x</p>', '10~%x'),
			'insécable voulue' => array('<p>100&nbsp;km</p>', '100~km'),
			'plusieurs insécables voulues' => array('<p>a&nbsp;b&nbsp;c</p>', 'a~b~c'),
			'code protégé' => array('<p><code>a&nbsp;: b&#8217;</code></p>', "<code>a\u{a0}: b’</code>"),
			'lien' => array('<p><a href="https://e.test/">l&#8217;article&nbsp;!</a></p>', "[l'article !->https://e.test/]"),
			'fine gardée' => array("<p>a\u{202f}: b</p>", "a\u{202f}: b"),
		);
	}

	#[DataProvider('francais')]
	public function testFrancais(string $html, string $attendu): void
	{
		$this->assertSame($attendu, (new Convertisseur(new Options(passes: array('typographie'))))->convertir($html));
	}

	public static function anglais(): array
	{
		return array(
			'apostrophe et ponctuation' => array('<p>It&#8217;s: a; b! c?</p>', "It's: a; b! c?"),
			'insécable avant tiret virgule' => array('<p>a&nbsp;-, b</p>', 'a -, b'),
			// Règles de textwheel (annexe A, en 2 et en 2 bis), relevées par la capture de l'article 22
			'insécable avant pour cent' => array('<p>10&nbsp;% here</p>', '10 % here'),
			'insécable après Mr' => array('<p>Mr&nbsp;Smith and Mr.&nbsp;Jones</p>', 'Mr Smith and Mr. Jones'),
			'pour cent suivi d’un mot : voulue' => array('<p>10&nbsp;%x</p>', '10~%x'),
			'insécable avant deux points : voulue' => array('<p>a&nbsp;: b</p>', 'a~: b'),
		);
	}

	#[DataProvider('anglais')]
	public function testAnglais(string $html, string $attendu): void
	{
		$this->assertSame($attendu, (new Convertisseur(new Options(langue: 'en', passes: array('typographie'))))->convertir($html));
	}

	public function testTables(): void
	{
		foreach (array('fr', 'fr_ca', 'fr_tu', 'eo', 'cpf') as $langue) {
			$this->assertTrue(Typographie::tableFr($langue), $langue);
		}
		foreach (array('en', 'de', 'es', 'ar') as $langue) {
			$this->assertFalse(Typographie::tableFr($langue), $langue);
		}
	}
}
