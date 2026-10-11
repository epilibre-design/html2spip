<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Arbre\Arbre;
use Html2Spip\Arbre\Noeuds;
use PHPUnit\Framework\TestCase;

final class NoeudsTest extends TestCase
{
	public function testRemplacer(): void
	{
		$body = Arbre::body('<p>a <b>x</b> b</p>');
		$gras = Noeuds::descendants($body, 'b')[0];
		Noeuds::remplacer($gras, '[->12]', false);
		$this->assertSame('<p>a <h2s-raccourci data-spip="[->12]" data-bloc="0"></h2s-raccourci> b</p>', Arbre::serialiser($body->firstChild));
	}

	public function testClasses(): void
	{
		$body = Arbre::body('<a class=" spip_in  autre">x</a>');
		$lien = $body->firstChild;
		$this->assertSame(array('spip_in', 'autre'), Noeuds::classes($lien));
		$this->assertTrue(Noeuds::aClasse($lien, 'spip_in'));
		$this->assertFalse(Noeuds::aClasse($lien, 'spip'));
		$this->assertFalse(Noeuds::aClasse($body->ownerDocument->createTextNode('x'), 'spip_in'));
	}

	public function testDescendantsFiges(): void
	{
		$body = Arbre::body('<p><a href="1">a</a><a href="2">b</a></p>');
		$liens = Noeuds::descendants($body, 'a');
		$liens[0]->remove();
		$this->assertCount(2, $liens);
		$this->assertSame('2', $liens[1]->getAttribute('href'));
	}

	public function testTexteNormalise(): void
	{
		$this->assertSame('L’article cible : premier', Noeuds::texteNormalise(" L’article\u{a0}cible \u{a0}: \n premier "));
		$this->assertSame('L’article cible : premier', Noeuds::texteHtml('L&#8217;article cible&nbsp;: <i>premier</i>'));
	}
}
