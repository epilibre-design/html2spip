<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\ResolveurNul;
use Html2Spip\ResolveurTableau;
use PHPUnit\Framework\TestCase;

final class ResolveurTableauTest extends TestCase
{
	public function testResolveurTableau(): void
	{
		$resolveur = new ResolveurTableau(
			array('spip.php?article40' => array('type' => 'article', 'id' => 40)),
			array('article/40' => 'L&#8217;article cible'),
		);
		$this->assertSame(array('type' => 'article', 'id' => 40), $resolveur->objet('spip.php?article40'));
		$this->assertNull($resolveur->objet('spip.php?article41'));
		$this->assertSame('L&#8217;article cible', $resolveur->titreAffiche('article', 40));
		$this->assertNull($resolveur->titreAffiche('article', 41));
	}

	public function testResolveurNul(): void
	{
		$resolveur = new ResolveurNul();
		$this->assertNull($resolveur->objet('spip.php?article40'));
		$this->assertNull($resolveur->titreAffiche('article', 40));
	}
}
