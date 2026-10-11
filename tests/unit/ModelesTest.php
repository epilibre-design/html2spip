<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Convertisseur;
use Html2Spip\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModelesTest extends TestCase
{
	/**
	 * Forme de l'annexe B de la spec (SPIP 4.4.28) : <doc1|left>, <img1>, <emb1|right> donnent le même modèle
	 */
	private static function modele(string $align): string {
		return "<div class='spip_document_1 spip_document spip_documents spip_document_image spip_documents_$align spip_document_$align spip_document_avec_legende'>"
			. '<figure class="spip_doc_inner"><img src="IMG/png/image-1.png" width="1" height="1" alt="" />'
			. "<figcaption class='spip_doc_legende'><div class='spip_doc_titre'><strong>Image un</strong></div></figcaption>"
			. '</figure></div>';
	}

	public static function cas(): array {
		return array(
			'gauche' => array(self::modele('left'), '<doc1|left>'),
			'droite' => array(self::modele('right'), '<doc1|right>'),
			'centre, alignement par défaut' => array(self::modele('center'), '<doc1>'),
			'paragraphe coupé' => array('<p>Avant</p>' . self::modele('left') . '<p>Après</p>', "Avant\n\n<doc1|left>\n\nAprès"),
			'sans alignement' => array("<div class='spip_document_1 spip_document'><img src='IMG/png/image-1.png' alt='' /></div>", '<doc1>'),
		);
	}

	#[DataProvider('cas')]
	public function testModele(string $html, string $attendu): void
	{
		$this->assertSame($attendu, (new Convertisseur(new Options(passes: array('modeles'))))->convertir($html));
	}

	public function testAutreModeleNonReconnu(): void
	{
		// Forme qui n'est pas celle de doc, img, emb : conversion générique, contenu gardé
		$html = '<figure class="album album_model" id="modele_album_1"><figcaption>Un album</figcaption></figure>';
		$texte = (new Convertisseur(new Options(passes: array('modeles'))))->convertir($html);
		$this->assertStringContainsString('Un album', $texte);
		$this->assertStringNotContainsString('<doc', $texte);
	}

	public function testClasseSansNumero(): void
	{
		// spip_document_image, spip_document_left… ne sont pas des numéros de document
		$html = "<div class='spip_document spip_document_image'><p>x</p></div>";
		$texte = (new Convertisseur(new Options(passes: array('modeles'))))->convertir($html);
		$this->assertStringContainsString('x', $texte);
		$this->assertStringNotContainsString('<doc', $texte);
	}
}
