<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Arbre\Arbre;
use Html2Spip\Arbre\Noeuds;
use Html2Spip\Ecriture\Ecrivain;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Conversion du HTML de Wordpress en raccourcis SPIP (Ecrivain::spip()), sans SPIP
 */
final class EcrivainTest extends TestCase
{
	public static function conversions(): array
	{
		return array(
			// Paragraphes, sauts de ligne, marques
			'paragraphes' => array('<p>Un</p><p>Deux</p>', "Un\n\nDeux"),
			'paragraphe vide' => array('<p></p><p> </p><p>Texte</p>', 'Texte'),
			'saut de ligne' => array('<p>a<br>b</p>', "a\n_ b"),
			'saut de ligne final' => array('<p>a<br></p>', 'a'),
			'deux sauts : paragraphe' => array('a<br><br>b', "a\n\nb"),
			'saut de ligne dans une marque' => array('<p><span><b>A<br>B</b></span></p><h4><b>C<br>- D</b></h4>', "<span>{{A\n_ B}}</span>\n\n{{C - D}}"),
			'gras' => array('<p>a <strong>b</strong> <b>c</b></p>', 'a {{b}} {{c}}'),
			'italique' => array('<p><em>a</em> <i>b</i></p>', '{a} {b}'),
			'blancs hors des marques' => array('<p>Le <strong>gras </strong>et<em> italique</em></p>', 'Le {{gras}} et {italique}'),
			'marque vide' => array('<p>a<strong> </strong>b</p>', 'a b'),
			'gras et italique imbriqués' => array('<p><em><strong>c</strong></em></p>', '{ {{c}} }'),
			'gras dans le gras' => array('<p><b>a <b>b</b> c</b></p>', '{{a b c}}'),
			'intertitres' => array('<h1>Un</h1><h2>Deux</h2><h3>Trois <em>x</em></h3>', "{{{Un}}}\n\n{{{Deux}}}\n\n{{{Trois {x} }}}"),
			'petits titres' => array('<h4>Quatre</h4><h6>Six</h6>', "{{Quatre}}\n\n{{Six}}"),
			'titre vide' => array('<h2> </h2>', ''),
			// Liens
			'lien' => array('<p><a href="https://example.test/a?b=1&amp;c=2">texte <b>gras</b></a></p>', '[texte {{gras}}->https://example.test/a?b=1&c=2]'),
			'lien sans texte' => array('<a href="https://example.test/"></a>', '[->https://example.test/]'),
			'lien et blancs' => array('<p>voir<a href="/x"> ici </a>fin</p>', 'voir [ici->/x] fin'),
			'ancre' => array('<p><a id="haut"></a>Texte</p>', '[haut<-]Texte'),
			'lien sans adresse' => array('<p><a>texte</a></p>', 'texte'),
			'lien sur une image' => array('<a href="https://example.test/a.jpg"><img src="https://example.test/a.jpg" alt=""></a>', '[<img src="https://example.test/a.jpg" alt="">->https://example.test/a.jpg]'),
			// Listes
			'liste' => array('<ul><li>a</li><li>b</li></ul>', "-* a\n-* b"),
			'liste numérotée' => array('<ol><li>a</li><li>b</li></ol>', "-# a\n-# b"),
			'listes imbriquées' => array('<ul><li>a<ol><li>a1<ul><li>x</li></ul></li></ol></li><li>b</li></ul>', "-* a\n-## a1\n-*** x\n-* b"),
			'élément à paragraphes' => array('<ul><li><p>a</p><p>b</p></li></ul>', "-* a\n_ b"),
			'élément vide' => array('<ul><li></li><li>b</li></ul>', "-*\n-* b"),
			'élément hors liste' => array('<li>orphelin</li>', '-* orphelin'),
			'liste non fermée : suite gardée après la liste' => array('<ul><li>a</li>Suite <b>x</b><p>Paragraphe</p><ul><li>b</li></ul></ul>', "-* a\n\nSuite {{x}}\n\nParagraphe\n\n-* b"),
			'sous-liste non fermée : suite dans l’élément' => array('<ul><li>a<ul><li>b</li>suite</ul></li></ul>', "-* a\n-** b\n_ suite"),
			// Tableaux
			'tableau' => array('<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>', "| {{A}} | {{B}} |\n| 1 | 2 |"),
			'tableau, légende et fusion' => array('<table><caption>Légende</caption><tr><td colspan="2">x</td></tr><tr><td>1|2</td><td></td></tr></table>', "|| Légende ||\n| x | < |\n| 1&#124;2 |  |"),
			'tableau, en-tête vide' => array('<table><tr><th>A</th><th></th></tr></table>', '| {{A}} |  |'),
			'tableau imbriqué : HTML' => array('<table><tr><td>a <b>b</b><table><tr><td>x</td></tr></table></td></tr></table>', "<table>\n<tbody>\n<tr><td>a {{b}}<table>\n<tbody>\n<tr><td>x</td></tr>\n</tbody>\n</table></td></tr>\n</tbody>\n</table>"),
			'cellule à plusieurs paragraphes : HTML' => array('<table><tr><td><p>a</p><p>b</p></td><td rowspan="2">c</td></tr></table>', "<table>\n<tbody>\n<tr><td>a\n_ b</td><td rowspan=\"2\">c</td></tr>\n</tbody>\n</table>"),
			// Citations, code, préformaté, filet
			'citation' => array('<blockquote><p>a</p><p>b</p></blockquote>', "<quote>\na\n\nb\n</quote>"),
			'bloc de code' => array("<pre class=\"wp-block-code\"><code>if (a &lt; b) {\n  x();\n}</code></pre>", "<cadre>\nif (a < b) {\n  x();\n}\n</cadre>"),
			'préformaté' => array("<pre>ligne 1\n  ligne 2<br>ligne 3</pre>", "<poesie>\nligne 1\n  ligne 2\nligne 3\n</poesie>"),
			'code en ligne' => array('<p>Le <code>&lt;b&gt;</code> ici</p>', 'Le <code><b></code> ici'),
			'filet' => array('<p>a</p><hr><p>b</p>', "a\n\n----\n\nb"),
			// HTML gardé
			'image' => array('<p>Avant <img src="a.jpg" class="alignleft wp-image-5" alt="x"> après</p>', 'Avant <img src="a.jpg" class="alignleft wp-image-5" alt="x"> après'),
			'lecteur' => array('<figure class="wp-block-audio"><audio controls src="a.mp3"></audio></figure>', '<figure class="wp-block-audio"><audio controls="" src="a.mp3"></audio></figure>'),
			'légende sur une ligne' => array('<figure><img src="a.jpg"><figcaption>Légende <em>x</em></figcaption></figure>', "<figure>\n\n<img src=\"a.jpg\">\n\n<figcaption>Légende {x}</figcaption>\n\n</figure>"),
			'bloc inconnu' => array('<div class="wp-block-group"><p>x</p><p>y</p></div>', "<div class=\"wp-block-group\">\n\nx\n\ny\n\n</div>"),
			'élément en ligne inconnu' => array('<p>H<sub>2</sub>O, un <span class="x">mot </span>de plus</p>', 'H<sub>2</sub>O, un <span class="x">mot</span> de plus'),
			'liste de définitions' => array('<dl><dt>Terme</dt><dd>Définition <b>x</b></dd></dl>', "<dl>\n<dt>Terme</dt>\n<dd>Définition {{x}}</dd>\n</dl>"),
			'script, style et commentaire retirés' => array('<p>a<script>alert(1)</script><style>p{}</style><!-- note -->b</p>', 'ab'),
			// Texte : entités et raccourcis SPIP affichés tels quels
			'entités' => array('<p>a &lt;b&gt; &amp; c&nbsp;: d</p>', "a &lt;b&gt; &amp; c\u{a0}: d"),
			'raccourcis SPIP en texte' => array('<p>{{a}} [b->c] [[note]] | d | ~</p>', '&#123;&#123;a&#125;&#125; &#91;b-&gt;c&#93; &#91;&#91;note&#93;&#93; &#124; d &#124; &#126;'),
			'tiret et souligné en début de ligne' => array("- a\n_ b\nc - d", "&#45; a\n_ _ b\n_ c - d"),
			// wpautop
			'éditeur classique' => array("Un\ndeux\n\nTrois", "Un\n_ deux\n\nTrois"),
			'éditeur classique, blancs' => array("a   b\t c", 'a b c'),
			'éditeur classique, balises de bloc' => array("<ul>\n<li>a</li>\n</ul>\nTexte", "-* a\n\nTexte"),
			// HTML mal formé, textes qui faisaient échouer sale
			'balises croisées' => array('<p>a <b>b <i>c</b> d</i></p>', 'a {{b {c} }} {d}', 'a {{b {c} }} d'),
			'balises non fermées' => array('<p>a <b>b<p>c', "a {{b}}\n\n{{c}}", "a {{b}}\n\nc"),
			'fermantes orphelines' => array('a</b></p>b', "a\n\nb"),
			'texte égaré dans un tableau' => array('<table><tr><td>a</td></tr>égaré</table>', "égaré\n\n| a |"),
			'texte égaré entre deux rangées' => array('<table><tr><td>a</td></tr>x<tr><td>b</td></tr></table>', "x\n\n| a |\n| b |"),
			'section et rangée implicites' => array('<table><td>a</td></table>', '| a |'),
			'cellules non fermées' => array('<table><tr><td>a<td>b<tr><td>c</table>', "| a | b |\n| c |"),
			'éléments de liste non fermés' => array('<ul><li>a<li>b</ul>', "-* a\n-* b"),
			'paragraphe fermé par un bloc' => array('<p>a<div>b</div>c', "a\n\n<div>b</div>\n\nc"),
			'br fermant' => array('a</br>b', "a\n_ b"),
			'svg et attribut à préfixe' => array('<p><svg viewBox="0 0 1 1"><use xlink:href="#i"></use></svg></p>', '<svg viewBox="0 0 1 1"><use xlink:href="#i"></use></svg>'),
			'iframe et attribut booléen' => array('<iframe src="https://e.test/?a=1&amp;b=2" allowfullscreen></iframe>', '<iframe src="https://e.test/?a=1&amp;b=2" allowfullscreen=""></iframe>'),
			'attribut à espace insécable et chevrons' => array('<img alt="a&nbsp;&lt;b&gt;">', '<img alt="a&nbsp;<b>">'),
			'commentaire dans un média' => array('<audio><!-- c --></audio>', '<audio><!-- c --></audio>'),
			'attribut à préfixe gardé dans un bloc' => array('<div xml:lang="fr"><p>a</p></div>', '<div xml:lang="fr">a</div>'),
			'vingt espaces' => array('a' . str_repeat(' ', 30) . 'b', 'a b'),
			'texte vide' => array(" \n ", ''),
		);
	}

	#[DataProvider('conversions')]
	public function testConversion(string $html, string $attendu, ?string $attendu_masterminds = null): void
	{
		$this->assertSame(class_exists('Dom\HTMLDocument') ? $attendu : ($attendu_masterminds ?? $attendu), Ecrivain::spip($html));
	}

	#[DataProvider('conversions')]
	public function testConversionMasterminds(string $html, string $attendu, ?string $attendu_masterminds = null): void
	{
		$this->assertSame($attendu_masterminds ?? $attendu, trim($html) === '' ? '' : Ecrivain::convertir_body(Arbre::bodyMasterminds($html), array()));
	}

	public function testContenuABlocs(): void
	{
		// Sans wpautop : blancs réduits, retours à la ligne sans effet
		$this->assertSame("Un deux\n\nTrois", Ecrivain::spip("<p>Un\ndeux</p>\n\n<p>Trois</p>", array('autop' => false)));
	}

	/**
	 * Options de wp2spip : marqueurs de blocs protégés, raccourcis Wordpress convertis après l'écriture
	 */
	private const WORDPRESS = array(
		'marqueurs' => '/(wp2spipbloc\d+)/',
		'raccourcis_gardes' => '#(\[/?(?:caption|wp_caption|gallery|audio|video|embed|playlist)\b[^\[\]]*\])#i',
		'raccourcis_blocs' => array(
			'debut' => '#^\s*\[(?:caption|wp_caption|gallery|audio|video|embed|playlist)\b#i',
			'fin' => '#\[/(?:caption|wp_caption|audio|video|embed|playlist)\]\s*$#i',
		),
	);

	public function testMarqueursDeBlocs(): void
	{
		$this->assertSame("Avant\n\nwp2spipbloc0\n\nwp2spipbloc12\n\nAprès", Ecrivain::spip("<p>Avant</p>\n\nwp2spipbloc0\n\nwp2spipbloc12\n\n<p>Après</p>", array('autop' => false) + self::WORDPRESS));
		$this->assertSame("a\n\nwp2spipbloc3\n\nb", Ecrivain::spip('a wp2spipbloc3 b', array('autop' => false) + self::WORDPRESS));
		// Sans l'option : du texte
		$this->assertSame('a wp2spipbloc3 b', Ecrivain::spip('a wp2spipbloc3 b', array('autop' => false)));
	}

	public function testRaccourcisWordpress(): void
	{
		$this->assertSame('[caption id="attachment_5" align="alignleft"]<img src="a.jpg"> Légende[/caption]', Ecrivain::spip('[caption id="attachment_5" align="alignleft"]<img src="a.jpg"> Légende[/caption]', self::WORDPRESS));
		$this->assertSame("Avant\n[caption id=\"x\"]<img src=\"a.jpg\"> L[/caption]\nAprès", Ecrivain::spip("Avant\n[caption id=\"x\"]<img src=\"a.jpg\"> L[/caption]\nAprès", self::WORDPRESS));
		// Sans l'option : du texte, échappé
		$this->assertSame('&#91;caption&#93;x&#91;/caption&#93;', Ecrivain::spip('[caption]x[/caption]'));
	}

	public function testTexteLongSansPerte(): void
	{
		$html = str_repeat("<p>Paragraphe avec beaucoup d’espaces      \n\n\n\n   et <b>du gras</b>.</p>\n\n", 2000);
		$this->assertSame(2000, substr_count(Ecrivain::spip($html, array('autop' => false)), '{{du gras}}'));
	}

	public static function contratDesPasses(): array
	{
		return array(
			'raccourci en ligne' => array('<p>Voir <h2s-raccourci data-spip="[->12]" data-bloc="0"></h2s-raccourci> ici</p>', 'Voir [->12] ici'),
			'raccourci de bloc' => array("<p>a</p><h2s-raccourci data-spip=\"&lt;cadre&gt;&#10;x {{y}}&#10;&lt;/cadre&gt;\" data-bloc=\"1\"></h2s-raccourci><p>b</p>", "a\n\n<cadre>\nx {{y}}\n</cadre>\n\nb"),
			'raccourci tilde' => array('<p>100<h2s-raccourci data-spip="~" data-bloc="0"></h2s-raccourci>km</p>', '100~km'),
			'bulle' => array('<p><a href="40" data-h2s-bulle="Une bulle">avec bulle</a></p>', '[avec bulle|Une bulle->40]'),
			'bulle et langue' => array('<p><a href="1" data-h2s-bulle="bulle|en">x</a></p>', '[x|bulle|en->1]'),
			'résumé de tableau' => array('<table data-h2s-resume="Résumé"><caption>Légende</caption><tr><td>a</td></tr></table>', "|| Légende | Résumé ||\n| a |"),
			'retour simple de SPIP' => array("<p>l1<br class=\"autobr\">\nl2<br class=\"manualbr\">l3</p>", "l1\nl2\n_ l3"),
			'retour simple puis tiret' => array("<p>l1<br class=\"autobr\">\n- l2</p>", "l1\n&#45; l2"),
		);
	}

	#[DataProvider('contratDesPasses')]
	public function testContratDesPasses(string $html, string $attendu): void
	{
		$this->assertSame($attendu, Ecrivain::spip($html, array('autop' => false)));
		$this->assertSame($attendu, Ecrivain::convertir_body(Arbre::bodyMasterminds($html), array('autop' => false)));
	}

	public function testNote(): void
	{
		foreach (array(Arbre::body(...), Arbre::bodyMasterminds(...)) as $analyser) {
			$body = $analyser('<p>Un deux</p><div id="n"><p>Note <b>x</b></p><p>suite</p></div>');
			$texte = $body->firstChild->firstChild;
			$note = Noeuds::creer($body, Noeuds::NOTE);
			$note->setAttribute('data-numero', 'xx');
			$div = $body->lastChild;
			foreach (iterator_to_array($div->childNodes) as $enfant) {
				$note->appendChild($enfant);
			}
			$div->remove();
			$texte->splitText(2)->before($note);
			$this->assertSame("Un[[<xx>Note {{x}}\n\nsuite]] deux", Ecrivain::convertir_body($body, array('autop' => false)));
		}
	}
}
