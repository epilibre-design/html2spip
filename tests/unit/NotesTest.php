<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Unit;

use Html2Spip\Convertisseur;
use Html2Spip\Options;
use PHPUnit\Framework\TestCase;

/**
 * Notes rendues par SPIP 4.4.28 (annexe B de la spec) : propre() puis #NOTES dans div.notes
 */
final class NotesTest extends TestCase
{
	private const NOTES = <<<'HTML'
<p>Un<span class="spip_note_ref">&nbsp;[<a href="#nb1" class="spip_note" rel="appendix" title="Note grasse second para" id="nh1">1</a>]</span> deux<span class="spip_note_ref">&nbsp;[<a href="#nbxx" class="spip_note" rel="appendix" title="Perso" id="nhxx">xx</a>]</span> trois<span class="spip_note_ref">&nbsp;[<a href="#nb2" class="spip_note" rel="appendix" title="Encore lien" id="nh2">2</a>]</span>.</p>
<div class="notes">
<div id="nb1">
<p><span class="spip_note_ref">[<a href="#nh1" class="spip_note" title="Notes 1" rev="appendix">1</a>]&nbsp;</span>Note <strong>grasse</strong></p>
<p>second para</p>
</div><div id="nbxx">
<p><span class="spip_note_ref">[<a href="#nhxx" class="spip_note" title="Notes xx" rev="appendix">xx</a>]&nbsp;</span>Perso</p>
</div><div id="nb2">
<p><span class="spip_note_ref">[<a href="#nh2" class="spip_note" title="Notes 2" rev="appendix">2</a>]&nbsp;</span>Encore <a href="https://x.y" class="spip_out" rel="external">lien</a></p>
</div>
</div>
HTML;

	private static function convertisseur(): Convertisseur
	{
		return new Convertisseur(new Options(passes: array('notes', 'liens')));
	}

	public function testNotes(): void
	{
		$this->assertSame(
			"Un[[Note {{grasse}}\n\nsecond para]] deux[[<xx>Perso]] trois[[Encore [lien->https://x.y]]].",
			self::convertisseur()->convertir(self::NOTES)
		);
	}

	public function testAppelSansNote(): void
	{
		$convertisseur = self::convertisseur();
		$html = '<p>A<span class="spip_note_ref">&nbsp;[<a href="#nb1" class="spip_note" rel="appendix" id="nh1">1</a>]</span> b</p>';
		$this->assertSame('A b', $convertisseur->convertir($html));
		$this->assertSame(array('Appel de note sans note dans le fragment : 1'), $convertisseur->avertissements());
	}

	public function testNoteSansAppel(): void
	{
		$convertisseur = self::convertisseur();
		$html = '<p>A</p><div class="notes"><div id="nb1"><p><span class="spip_note_ref">[<a href="#nh1" class="spip_note" rev="appendix">1</a>]&nbsp;</span>Orpheline</p></div></div><p>B</p>';
		$this->assertSame("A\n\nB\n\nOrpheline", $convertisseur->convertir($html));
		$this->assertSame(array('Note sans appel dans le fragment : 1'), $convertisseur->avertissements());
	}
}
