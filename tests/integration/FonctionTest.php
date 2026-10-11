<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * html2spip() et la commande html2spip:convertir dans le SPIP jetable
 */
final class FonctionTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		include_spip('inc/html2spip');
	}

	public function testAllerRetour(): void
	{
		$this->assertSame("Voir [->40] pour plus d'infos.", html2spip(html2spip_test_rendu("Voir [->40] pour plus d'infos.")));
	}

	public function testLangue(): void
	{
		// Table en : SPIP n'ajoute pas d'insécable devant « : », celle-ci était voulue
		$this->assertSame("It's~:", html2spip("<p>It&#8217;s&nbsp;:</p>", 'en'));
		$this->assertSame("C'est :", html2spip("<p>C&#8217;est&nbsp;:</p>", 'fr'));
	}

	public function testAvertissements(): void
	{
		html2spip('<p><a href="spip.php?article999">article 999</a></p>');
		$this->assertNotSame(array(), html2spip_avertissements());
	}

	public function testCommande(): void
	{
		$racine = dirname(__DIR__, 2);
		$processus = proc_open(
			array($racine . '/vendor/bin/spip', '--no-ansi', 'html2spip:convertir', '-v'),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$tubes,
			$racine . '/tests/spip-jetable/spip'
		);
		fwrite($tubes[0], '<p>Un <strong>gras</strong>&nbsp;!</p><p><a href="spip.php?article999">article 999</a></p>');
		fclose($tubes[0]);
		$sortie = stream_get_contents($tubes[1]);
		$erreurs = stream_get_contents($tubes[2]);
		$this->assertSame(0, proc_close($processus), $erreurs);
		$this->assertSame("Un {{gras}} !\n\n[->999]\n", $sortie);
		$this->assertStringContainsString('999', $erreurs);
	}
}
