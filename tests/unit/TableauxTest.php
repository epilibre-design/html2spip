<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Convertisseur;
use Html2Spip\Options;
use PHPUnit\Framework\TestCase;

final class TableauxTest extends TestCase
{
	/**
	 * Rendu de SPIP 4.4.28 (annexe B) pour :
	 * || Légende | Résumé ||
	 * | {{A}} | {{B}} |
	 * | 1 | 2 |
	 * | x | < |
	 */
	private const TABLEAU = <<<'HTML'
<table class="table spip" aria-describedby="dby2af6">
<caption>Légende<br /> <small id="dby2af6" class="summary offscreen">Résumé</small></caption>
<thead><tr class='row_first'><th id='id2af6_c0'> A </th><th id='id2af6_c1'> B </th></tr></thead>
<tbody>
<tr class='row_odd odd'>
<td headers='id2af6_c0'>1</td>
<td headers='id2af6_c1'>2</td></tr>
<tr class='row_even even'>
<td colspan='2' headers='id2af6_c0'>x</td></tr>
</tbody>
</table>
HTML;

	public function testTableauDeSpip(): void
	{
		$this->assertSame(
			"|| Légende | Résumé ||\n| {{A}} | {{B}} |\n| 1 | 2 |\n| x | < |",
			(new Convertisseur(new Options(passes: array('tableaux'))))->convertir(self::TABLEAU)
		);
	}

	public function testLegendeSansResume(): void
	{
		$html = '<table class="table spip"><caption>Légende</caption><tbody><tr><td>1</td></tr></tbody></table>';
		$this->assertSame("|| Légende ||\n| 1 |", (new Convertisseur(new Options(passes: array('tableaux'))))->convertir($html));
	}
}
