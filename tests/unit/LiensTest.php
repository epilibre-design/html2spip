<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Convertisseur;
use Html2Spip\Options;
use Html2Spip\Reconnaissance\Liens;
use Html2Spip\ResolveurTableau;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Liens rendus par SPIP 4.4 (annexe B de la spec)
 */
final class LiensTest extends TestCase
{
	private static function resolveur(): ResolveurTableau
	{
		return new ResolveurTableau(
			array(
				'spip.php?article40' => array('type' => 'article', 'id' => 40),
				'L-article-cible-premier' => array('type' => 'article', 'id' => 40),
				'spip.php?rubrique1' => array('type' => 'rubrique', 'id' => 1),
				'IMG/png/image-1.png' => array('type' => 'document', 'id' => 1),
			),
			array(
				'article/40' => 'L&#8217;article cible&nbsp;: premier',
				'rubrique/1' => 'Rubrique du corpus',
				'document/1' => 'Image un',
			),
		);
	}

	public static function liens(): array
	{
		return array(
			'texte égal au titre' => array('<p>Voir <a href="spip.php?article40" class="spip_in">L’article cible&nbsp;: premier</a>.</p>', 'Voir [->40].'),
			'URL propre' => array('<p><a href="L-article-cible-premier" class="spip_in">L’article cible&nbsp;: premier</a></p>', '[->40]'),
			'autre texte' => array('<p><a href="spip.php?article40" class="spip_in">le <b>texte</b></a></p>', '[le {{texte}}->40]'),
			'ancre' => array('<p><a href="spip.php?article40#suite" class="spip_in">L’article cible&nbsp;: premier</a></p>', '[->40#suite]'),
			'bulle' => array('<p><a href="spip.php?article40" class="spip_in" title="Une bulle">avec bulle</a></p>', '[avec bulle|Une bulle->40]'),
			'bulle et titre' => array('<p><a href="spip.php?article40" class="spip_in" title="Une bulle">L’article cible&nbsp;: premier</a></p>', "[L’article cible\u{a0}: premier|Une bulle->40]"),
			'rubrique' => array('<p><a href="spip.php?rubrique1" class="spip_in">Rubrique du corpus</a></p>', '[->rub1]'),
			'document' => array("<p><a href=\"IMG/png/image-1.png\" class=\"spip_in\" type='image/png'>Image un</a></p>", '[->doc1]'),
			'objet absent' => array('<p><a href="spip.php?article999">article 999</a></p>', '[->999]'),
			'objet absent, autre texte' => array('<p><a href="spip.php?rubrique999">x</a></p>', '[x->rub999]'),
			'objet absent, URL propres' => array('<p><a href="spip.php?page=article&amp;id_article=999">article 999</a></p>', '[->999]'),
			'objet absent, URL html' => array('<p><a href="breve12.html">breve 12</a></p>', '[->br12]'),
			'externe' => array('<p><a href="https://www.spip.net" class="spip_out" rel="external">spip</a></p>', '[spip->https://www.spip.net]'),
			'URL nue' => array('<p>voir <a href="https://www.spip.net/x" class="spip_url spip_out auto" rel="nofollow external">https://www.spip.net/x</a> ici</p>', 'voir https://www.spip.net/x ici'),
			'courriel' => array('<p><a href="mailto:a@b.fr" class="spip_mail">a@b.fr</a></p>', '[->a@b.fr]'),
			'courriel, autre texte' => array('<p><a href="mailto:a@b.fr" class="spip_mail">mail</a></p>', '[mail->mailto:a@b.fr]'),
			'glossaire' => array('<p><a href="https://fr.wikipedia.org/wiki/SPIP" class="spip_glossaire" rel="external">SPIP</a></p>', '[?SPIP]'),
			'glossaire, terme à espace' => array('<p><a href="https://fr.wikipedia.org/wiki/Logiciel_libre" class="spip_glossaire" rel="external">Logiciel libre</a></p>', '[?Logiciel libre]'),
			'lien interne inconnu' => array('<p><a href="Une-page" class="spip_in">x</a></p>', '[x->Une-page]'),
			'lien quelconque' => array('<p><a href="?SPIP">texte</a></p>', '[texte->?SPIP]'),
		);
	}

	#[DataProvider('liens')]
	public function testLien(string $html, string $attendu): void
	{
		$options = new Options(resolveur: self::resolveur(), passes: array('liens'));
		$this->assertSame($attendu, (new Convertisseur($options))->convertir($html));
	}

	public function testAvertissements(): void
	{
		$convertisseur = new Convertisseur(new Options(resolveur: self::resolveur(), passes: array('liens')));
		$convertisseur->convertir('<p><a href="spip.php?article999">article 999</a> <a href="Une-page" class="spip_in">x</a> <a href="https://fr.wikipedia.org/wiki/SPIP" class="spip_glossaire">autre</a></p>');
		$this->assertSame(array(
			'Lien vers un objet absent ou non résolu : spip.php?article999',
			'Lien interne non résolu : Une-page',
			'Lien de glossaire dont le texte n’est pas le terme : https://fr.wikipedia.org/wiki/SPIP',
		), $convertisseur->avertissements());
	}

	public function testObjetDansUrl(): void
	{
		$this->assertSame(array('type' => 'article', 'id' => 12), Liens::objetDansUrl('spip.php?article12'));
		$this->assertSame(array('type' => 'site', 'id' => 3), Liens::objetDansUrl('spip.php?page=site&id_syndic=3'));
		$this->assertSame(array('type' => 'auteur', 'id' => 1), Liens::objetDansUrl('auteur1.html'));
		$this->assertNull(Liens::objetDansUrl('Une-page'));
		$this->assertNull(Liens::objetDansUrl('https://e.test/spip.php?article12'));
	}
}
