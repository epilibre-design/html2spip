<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Integration;

use Html2Spip\Convertisseur;
use Html2Spip\Spip\OptionsSpip;
use Html2Spip\Tests\Outils\HtmlCanonique;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Chaque article du corpus, pour le type d'URL de la session (HTML2SPIP_TYPE_URL) :
 * - la capture versionnée est à jour (sinon : changement de SPIP 4.4.x ou du corpus, à revoir puis composer capturer) ;
 * - la conversion de la capture donne la sortie attendue (attendus/<type_url>/<nom>.spip, attendus/<nom>.spip, ou
 *   le texte de la source, écrit sous forme canonique) ;
 * - sauf pour un cas perdu (limite: oui), la sortie est rendue par SPIP comme la source, notes comprises
 */
final class CorpusTest extends TestCase
{
	private const CORPUS = __DIR__ . '/../corpus';

	public static function articles(): array
	{
		$articles = array();
		foreach (array_keys(html2spip_test_sources('article')) as $nom) {
			$articles[$nom] = array($nom);
		}
		return $articles;
	}

	#[DataProvider('articles')]
	public function testCaptureAJour(string $nom): void
	{
		$source = self::source($nom);
		$this->assertSame(self::capture($nom), html2spip_test_rendu($source['texte'], $source['lang'] ?? 'fr') . "\n", "capture de $nom à revoir, puis composer capturer");
	}

	#[DataProvider('articles')]
	public function testFormeCanonique(string $nom): void
	{
		$this->assertSame(self::attendu($nom), self::convertir($nom));
	}

	#[DataProvider('articles')]
	public function testRenduEquivalent(string $nom): void
	{
		$source = self::source($nom);
		if (($source['limite'] ?? 'non') === 'oui') {
			$this->markTestSkipped("$nom : cas perdu (limite)");
		}
		$lang = $source['lang'] ?? 'fr';
		$this->assertSame(
			HtmlCanonique::de(html2spip_test_rendu($source['texte'], $lang)),
			HtmlCanonique::de(html2spip_test_rendu(self::convertir($nom), $lang))
		);
	}

	private static function source(string $nom): array
	{
		return html2spip_test_sources('article')[$nom];
	}

	private static function capture(string $nom): string
	{
		return (string) @file_get_contents(self::CORPUS . '/captures/' . html2spip_test_type_url() . "/$nom.html");
	}

	private static function attendu(string $nom): string
	{
		foreach (array(html2spip_test_type_url() . "/$nom.spip", "$nom.spip") as $chemin) {
			if (is_file(self::CORPUS . "/attendus/$chemin")) {
				return rtrim(file_get_contents(self::CORPUS . "/attendus/$chemin"), "\n");
			}
		}
		return self::source($nom)['texte'];
	}

	private static function convertir(string $nom): string
	{
		$options = OptionsSpip::options(self::source($nom)['lang'] ?? 'fr');
		return (new Convertisseur($options))->convertir(self::capture($nom));
	}
}
