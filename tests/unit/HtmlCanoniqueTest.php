<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Tests\Outils\HtmlCanonique;
use PHPUnit\Framework\TestCase;

final class HtmlCanoniqueTest extends TestCase
{
	public function testEntitesDecodees(): void
	{
		// propre("C’est") et propre("C'est") sur SPIP 4.4
		$this->assertSame(HtmlCanonique::de('<p>C’est</p>'), HtmlCanonique::de('<p>C&#8217;est</p>'));
		$this->assertSame(HtmlCanonique::de("<p>a\u{a0}: b</p>"), HtmlCanonique::de('<p>a&nbsp;: b</p>'));
	}

	public function testAttributsTries(): void
	{
		$this->assertSame(HtmlCanonique::de('<a class="spip_in" href="x">t</a>'), HtmlCanonique::de("<a href='x' class=\"spip_in\">t</a>"));
	}

	public function testBlancsEntreBlocs(): void
	{
		$this->assertSame(HtmlCanonique::de("<p>a</p>\n\n<p>b</p>"), HtmlCanonique::de('<p>a</p><p>b</p>'));
	}

	public function testDifferences(): void
	{
		$this->assertNotSame(HtmlCanonique::de('<p>a b</p>'), HtmlCanonique::de('<p>a  b</p>'));
		$this->assertNotSame(HtmlCanonique::de('<p><strong>a</strong></p>'), HtmlCanonique::de('<p><b>a</b></p>'));
		$this->assertNotSame(HtmlCanonique::de('<pre>a</pre>'), HtmlCanonique::de("<pre>a\n</pre>"));
	}
}
