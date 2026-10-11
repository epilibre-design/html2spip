<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Integration;

use Html2Spip\Spip\ResolveurSpip;
use PHPUnit\Framework\TestCase;

final class ResolveurSpipTest extends TestCase
{
	public function testObjets(): void
	{
		$resolveur = new ResolveurSpip();
		$this->assertSame(array('type' => 'article', 'id' => 40), $resolveur->objet('spip.php?article40'));
		$this->assertSame(array('type' => 'article', 'id' => 40), $resolveur->objet('spip.php?page=article&id_article=40'));
		$this->assertSame(array('type' => 'rubrique', 'id' => 1), $resolveur->objet(url_de_base() . 'spip.php?rubrique1'));
		$this->assertSame(array('type' => 'document', 'id' => 1), $resolveur->objet('IMG/png/image-1.png'));
		$this->assertNull($resolveur->objet('spip.php?article999'));
		$this->assertNull($resolveur->objet('https://www.spip.net'));
		$this->assertNull($resolveur->objet(''));
	}

	public function testObjetParUrlPropre(): void
	{
		include_spip('inc/urls');
		$url = generer_objet_url(40, 'article');
		$this->assertSame(array('type' => 'article', 'id' => 40), (new ResolveurSpip())->objet($url), $url);
	}

	public function testTitres(): void
	{
		$resolveur = new ResolveurSpip();
		$this->assertSame('L’article cible : premier', str_replace("\u{a0}", ' ', html_entity_decode($resolveur->titreAffiche('article', 40), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
		$this->assertSame('Article numéroté', $resolveur->titreAffiche('article', 41));
		$this->assertNull($resolveur->titreAffiche('article', 999));
	}
}
