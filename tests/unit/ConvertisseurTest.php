<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Convertisseur;
use Html2Spip\Options;
use Html2Spip\ResolveurNul;
use PHPUnit\Framework\TestCase;

final class ConvertisseurTest extends TestCase
{
	public function testTexteVide(): void
	{
		$this->assertSame('', (new Convertisseur())->convertir(" \n "));
	}

	public function testHtmlBlancsReduits(): void
	{
		// HTML : blancs réduits comme par un navigateur (autop faux par défaut)
		$this->assertSame("Un deux\n\nTrois", (new Convertisseur())->convertir("<p>Un\r\ndeux</p>\n<p>Trois</p>"));
	}

	public function testOptionsDeLEcriture(): void
	{
		$options = new Options(ecriture: array('autop' => true));
		$this->assertSame("Un\n_ deux", (new Convertisseur($options))->convertir("Un\ndeux"));
	}

	public function testOptionsParDefaut(): void
	{
		$options = new Options();
		$this->assertInstanceOf(ResolveurNul::class, $options->resolveur);
		$this->assertSame('fr', $options->langue);
		$this->assertTrue($options->passeActive('liens'));
		$this->assertFalse((new Options(passes: array()))->passeActive('liens'));
		$this->assertTrue((new Options(passes: array('liens')))->passeActive('liens'));
	}

	public function testAvertissementsVides(): void
	{
		$convertisseur = new Convertisseur();
		$convertisseur->convertir('<p>a</p>');
		$this->assertSame(array(), $convertisseur->avertissements());
	}
}
