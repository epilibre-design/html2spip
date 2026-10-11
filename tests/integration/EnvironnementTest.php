<?php

declare(strict_types=1);

namespace Html2Spip\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Le SPIP jetable est celui dont les règles de html2spip sont tirées : SPIP 4.4.x, html2spip seul, corpus chargé
 */
final class EnvironnementTest extends TestCase
{
	public function testVersions(): void
	{
		$this->assertStringStartsWith('4.4.', $GLOBALS['spip_version_branche']);
		$versions = parse_ini_file(dirname(__DIR__) . '/spip-jetable/VERSION');
		$this->assertSame($GLOBALS['spip_version_branche'], $versions['spip']);
	}

	public function testPluginActif(): void
	{
		$this->assertTrue(test_plugin_actif('html2spip'), 'plugin html2spip inactif');
	}

	public function testCorpusCharge(): void
	{
		foreach (html2spip_test_sources() as $nom => $source) {
			[$table, $cle] = HTML2SPIP_TEST_OBJETS_TABLES[$source['type']];
			$this->assertSame(1, (int) sql_countsel($table, "$cle=" . intval($source['id'])), "objet $nom absent");
		}
	}
}
