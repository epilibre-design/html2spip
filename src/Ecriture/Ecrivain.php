<?php
/**
 * Écriture des raccourcis SPIP : parcours générique de l'arbre HTML5
 *
 * Reprise du convertisseur de wp2spip (inc/wp2spip_html.php) : le HTML est parcouru nœud par nœud, aucune
 * expression régulière ne lui est appliquée. Les passes de reconnaissance (src/Reconnaissance/) ont déjà remplacé
 * les structures produites par SPIP par des nœuds privés (Html2Spip\Arbre\Noeuds), lus ici.
 */

namespace Html2Spip\Ecriture;

use Html2Spip\Arbre\Arbre;
use Html2Spip\Arbre\Noeuds;

final class Ecrivain
{
	/**
	 * Éléments de bloc gardés en HTML : leur contenu est converti en paragraphes
	 */
	private const BLOCS = array(
		'address', 'article', 'aside', 'center', 'details', 'dialog', 'div', 'fieldset', 'figcaption',
		'figure', 'footer', 'form', 'header', 'hgroup', 'main', 'nav', 'section', 'summary',
	);

	/**
	 * Éléments gardés tels quels, contenu compris (médias, contenus embarqués)
	 */
	private const TELS_QUELS = array('img', 'video', 'audio', 'iframe', 'object', 'embed', 'source', 'track', 'svg', 'math', 'canvas', 'picture', 'map', 'input', 'select', 'textarea', 'button');

	/**
	 * Éléments retirés avec leur contenu
	 */
	private const RETIRES = array('script', 'style', 'template', 'noscript', 'head', 'title', 'meta', 'link', 'base');

	/**
	 * Éléments vides (sans balise fermante)
	 */
	private const VIDES = array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr');

	/**
	 * HTML de Wordpress en raccourcis SPIP
	 *
	 * @param string $html
	 * @param array $options
	 *     - autop : true pour un texte auquel Wordpress applique wpautop() (ligne vide = paragraphe, retour simple =
	 *       saut de ligne), false pour du HTML (blancs réduits comme par un navigateur) ; défaut true
	 *     - marqueurs : motif preg_split, avec capture, des marqueurs à garder seuls sur leur paragraphe, ou null
	 *     - raccourcis_gardes : motif preg_split, avec capture, des raccourcis à ne pas échapper, ou null
	 *     - raccourcis_blocs : array('debut' => motif, 'fin' => motif) des raccourcis de bloc qui ne sont pas des
	 *       sauts de ligne quand ils sont seuls sur leur ligne, ou null
	 * @return string
	 */
	public static function spip($html, $options = array()) {
		$html = str_replace(array("\r\n", "\r"), "\n", (string) $html);
		if (trim($html) === '') {
			return '';
		}
		return self::convertir_body(Arbre::body($html), $options);
	}

	/**
	 * Body d'un HTML déjà analysé en raccourcis SPIP
	 *
	 * @param Dom\Element|DOMElement $body
	 * @param array $options voir self::spip()
	 * @return string
	 */
	public static function convertir_body($body, $options) {
		$contexte = array(
			'autop' => $options['autop'] ?? true,
			'marqueurs' => $options['marqueurs'] ?? null,
			'raccourcis_gardes' => $options['raccourcis_gardes'] ?? null,
			'raccourcis_blocs' => $options['raccourcis_blocs'] ?? null,
			'pre' => false,
			'gras' => false,
			'italique' => false,
			'listes' => '',
		);
		return self::blocs($body, $contexte);
	}

	/**
	 * Contenu d'un élément converti en paragraphes séparés par une ligne vide
	 *
	 * @param Dom\Node|DOMNode $parent
	 * @param array $contexte
	 * @return string
	 */
	private static function blocs($parent, $contexte) {
		$sortie = self::sortie();
		self::enfants($parent, $sortie, $contexte);
		return join("\n\n", self::paragraphes($sortie));
	}

	/**
	 * Contenu d'un élément converti sur une ligne logique : ses paragraphes joints par des sauts de ligne
	 *
	 * @param Dom\Node|DOMNode $parent
	 * @param array $contexte
	 * @return string
	 */
	private static function en_ligne($parent, $contexte) {
		$sortie = self::sortie();
		self::enfants($parent, $sortie, $contexte);
		return join("\n_ ", self::paragraphes($sortie));
	}

	/**
	 * @param Dom\Node|DOMNode $parent
	 * @param array $sortie
	 * @param array $contexte
	 */
	private static function enfants($parent, &$sortie, $contexte) {
		foreach ($parent->childNodes as $noeud) {
			self::noeud($noeud, $sortie, $contexte);
		}
	}

	/**
	 * @param Dom\Node|DOMNode $noeud
	 * @param array $sortie
	 * @param array $contexte
	 */
	private static function noeud($noeud, &$sortie, $contexte) {
		if (Arbre::estTexte($noeud)) {
			self::texte($noeud->data, $sortie, $contexte);
			return;
		}
		if (!Arbre::estElement($noeud)) {
			// Commentaires, instructions : retirés
			return;
		}
		$nom = strtolower($noeud->localName);
		switch ($nom) {
			case Noeuds::RACCOURCI:
				// Structure reconnue par une passe : texte SPIP déjà écrit
				$spip = (string) $noeud->getAttribute('data-spip');
				if ($noeud->getAttribute('data-bloc') === '1') {
					self::bloc($sortie, $spip);
				}
				else {
					self::ecrire($sortie, $spip);
				}
				return;
			case Noeuds::NOTE:
				self::ecrire($sortie, self::note($noeud, $contexte));
				return;
			case 'p':
				self::fin_paragraphe($sortie);
				self::enfants($noeud, $sortie, $contexte);
				self::fin_paragraphe($sortie);
				return;
			case 'br':
				// Dans un texte préformaté, les lignes sont gardées telles quelles ; retour simple rendu par SPIP
				// (br.autobr) : retour simple, que SPIP rend de nouveau ainsi
				if ($contexte['pre']) {
					self::ecrire($sortie, "\n");
				}
				elseif (Noeuds::aClasse($noeud, 'autobr')) {
					$sortie['retours']++;
				}
				else {
					$sortie['sauts']++;
				}
				return;
			case 'strong':
			case 'b':
				self::ecrire($sortie, self::entourer($noeud, $contexte, 'gras', '{{', '}}'));
				return;
			case 'em':
			case 'i':
				self::ecrire($sortie, self::entourer($noeud, $contexte, 'italique', '{', '}'));
				return;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$titre = self::une_ligne(self::en_ligne($noeud, array('gras' => true) + $contexte));
				if ($titre !== '') {
					self::bloc($sortie, in_array($nom, array('h1', 'h2', 'h3')) ? self::marquer($titre, '{{{', '}}}') : self::marquer($titre, '{{', '}}'));
				}
				return;
			case 'a':
				self::ecrire($sortie, self::bords($noeud, self::lien($noeud, $contexte)));
				return;
			case 'ul':
			case 'ol':
				self::bloc($sortie, self::liste($noeud, $contexte));
				return;
			case 'li':
				// Élément de liste hors liste (HTML mal formé) : une liste d'un élément
				self::bloc($sortie, '-* ' . self::une_ligne(self::en_ligne($noeud, $contexte)));
				return;
			case 'table':
				if ($contexte['tableau_html'] ?? false) {
					// Tableau dans une cellule d'un tableau gardé en HTML : en HTML lui aussi
					self::ecrire($sortie, self::tableau_html($noeud, $contexte));
					return;
				}
				// Contenu égaré dans la structure du tableau : avant le tableau, où le place l'analyse HTML5
				foreach (self::hors_tableau($noeud) as $egare) {
					self::noeud($egare, $sortie, $contexte);
				}
				self::bloc($sortie, self::tableau($noeud, $contexte));
				return;
			case 'blockquote':
				$citation = self::blocs($noeud, $contexte);
				if ($citation !== '') {
					self::bloc($sortie, "<quote>\n$citation\n</quote>");
				}
				return;
			case 'pre':
				self::bloc($sortie, self::pre($noeud, $contexte));
				return;
			case 'code':
				self::ecrire($sortie, '<code>' . $noeud->textContent . '</code>');
				return;
			case 'hr':
				self::bloc($sortie, '----');
				return;
			case 'dl':
				// Liste de définitions gardée en HTML, un terme ou une définition par ligne
				$lignes = array();
				foreach (self::elements($noeud) as $element) {
					$lignes[] = self::ouvrante($element) . self::une_ligne(self::en_ligne($element, $contexte)) . '</' . strtolower($element->localName) . '>';
				}
				self::bloc($sortie, self::ouvrante($noeud) . "\n" . join("\n", $lignes) . "\n</dl>");
				return;
		}
		if (in_array($nom, self::RETIRES)) {
			return;
		}
		if (in_array($nom, self::TELS_QUELS)) {
			self::ecrire($sortie, Arbre::serialiser($noeud));
			return;
		}
		if (in_array($nom, self::BLOCS)) {
			// Gardé en HTML : sur une ligne si son contenu tient en un paragraphe, sinon balises sur leurs propres lignes
			// et contenu en paragraphes
			$contenu = self::blocs($noeud, $contexte);
			if (!str_contains($contenu, "\n\n")) {
				self::bloc($sortie, self::ouvrante($noeud) . $contenu . "</$nom>");
				return;
			}
			self::bloc($sortie, self::ouvrante($noeud) . "\n\n$contenu\n\n</$nom>");
			return;
		}
		// Élément en ligne inconnu (span, sup, sub, u, del, abbr…) : gardé en HTML, contenu converti
		if (in_array($nom, self::VIDES)) {
			self::ecrire($sortie, self::ouvrante($noeud));
			return;
		}
		self::ecrire($sortie, self::bords($noeud, self::ouvrante($noeud) . trim(self::en_ligne($noeud, $contexte)) . "</$nom>"));
	}

	/**
	 * Texte d'un nœud : blancs selon le contexte, caractères spéciaux du HTML réécrits en entités, marqueurs de blocs
	 * protégés de wp2spip isolés sur leur paragraphe
	 *
	 * @param string $texte
	 * @param array $sortie
	 * @param array $contexte
	 */
	private static function texte($texte, &$sortie, $contexte) {
		if ($contexte['pre']) {
			self::ecrire($sortie, self::echapper_debut(self::echapper_texte($texte, $contexte)));
			return;
		}
		$morceaux = ($contexte['marqueurs'] === null) ? array($texte) : preg_split($contexte['marqueurs'], $texte, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($morceaux as $i => $morceau) {
			if ($i % 2) {
				self::bloc($sortie, $morceau);
				continue;
			}
			if (!$contexte['autop']) {
				self::ecrire($sortie, self::echapper_texte(preg_replace('/[ \t\n\f]+/', ' ', $morceau), $contexte));
				continue;
			}
			// wpautop() : ligne vide = paragraphe, retour simple = saut de ligne
			foreach (preg_split('/\n[ \t\f]*\n\s*/', $morceau) as $j => $paragraphe) {
				if ($j) {
					self::fin_paragraphe($sortie);
				}
				$lignes = explode("\n", $paragraphe);
				foreach ($lignes as $k => $ligne) {
					$ligne = self::echapper_texte(preg_replace('/[ \t\f]+/', ' ', $ligne), $contexte);
					if ($k and (self::raccourci_bloc($ligne, true, $contexte) or self::raccourci_bloc($lignes[$k - 1], false, $contexte))) {
						// Raccourci Wordpress de bloc (légende, lecteur…) sur sa ligne : pas un saut de ligne, il deviendra un document
						$ligne = "\n" . $ligne;
					}
					elseif ($k) {
						$sortie['sauts']++;
					}
					self::ecrire($sortie, $ligne);
				}
			}
		}
	}

	/**
	 * Gras ou italique : marques autour du contenu, blancs de bord sortis ; pas de marque dans une marque de même nature
	 *
	 * @param Dom\Element|DOMElement $element
	 * @param array $contexte
	 * @param string $style gras, italique
	 * @param string $ouvrant
	 * @param string $fermant
	 * @return string
	 */
	private static function entourer($element, $contexte, $style, $ouvrant, $fermant) {
		$contenu = trim(self::en_ligne($element, array($style => true) + $contexte));
		if ($contenu === '') {
			return preg_match('/\s/u', $element->textContent) ? ' ' : '';
		}
		return self::bords($element, $contexte[$style] ? $contenu : self::marquer($contenu, $ouvrant, $fermant));
	}

	/**
	 * Blancs de bord du texte d'origine d'un élément, gardés hors de sa conversion
	 *
	 * @param Dom\Element|DOMElement $element
	 * @param string $conversion
	 * @return string
	 */
	private static function bords($element, $conversion) {
		return (preg_match('/^\s/u', $element->textContent) ? ' ' : '') . $conversion . (preg_match('/\s$/u', $element->textContent) ? ' ' : '');
	}

	/**
	 * Marques autour d'un texte, séparées par un blanc d'une marque qu'il commence ou finit : {{{Titre {x}}}}
	 * serait lu {{{Titre {x}}} suivi d'une accolade
	 *
	 * @param string $texte
	 * @param string $ouvrant
	 * @param string $fermant
	 * @return string
	 */
	private static function marquer($texte, $ouvrant, $fermant) {
		return $ouvrant . (str_starts_with($texte, '{') ? ' ' : '') . $texte . (str_ends_with($texte, '}') ? ' ' : '') . $fermant;
	}

	/**
	 * Lien : [texte->url], ancre nommée : [nom<-]
	 *
	 * @param Dom\Element|DOMElement $lien
	 * @param array $contexte
	 * @return string
	 */
	private static function lien($lien, $contexte) {
		$texte = self::une_ligne(self::en_ligne($lien, $contexte));
		$url = trim((string) $lien->getAttribute('href'));
		if ($url === '') {
			$ancre = trim((string) ($lien->getAttribute('id') ?: $lien->getAttribute('name')));
			return ($ancre !== '' ? "[$ancre<-]" : '') . $texte;
		}
		// Bulle d'un lien rendu par SPIP (passe Liens) : [texte|bulle->url] ; une langue de bulle reste dans la bulle
		$bulle = trim((string) $lien->getAttribute('data-h2s-bulle'));
		if ($bulle !== '') {
			$texte .= '|' . strtr(self::echapper($bulle), array('[' => '&#91;', ']' => '&#93;'));
		}
		return '[' . $texte . '->' . str_replace(array('[', ']', ' '), array('%5B', '%5D', '%20'), $url) . ']';
	}

	/**
	 * Note reconstituée par la passe Notes : [[contenu]], ou [[<numero>contenu]] pour une note numérotée à la main ;
	 * paragraphes séparés par une ligne vide, que SPIP rend en paragraphes de la note
	 *
	 * @param \Dom\Element|\DOMElement $note h2s-note
	 * @param array $contexte
	 * @return string
	 */
	private static function note($note, $contexte) {
		$numero = (string) $note->getAttribute('data-numero');
		return '[[' . ($numero !== '' ? "<$numero>" : '') . self::blocs($note, array('listes' => '') + $contexte) . ']]';
	}

	/**
	 * Liste SPIP : -* (puces), -# (numéros) ; à la profondeur N, le caractère de la liste répété N fois (-**, -##),
	 * quel que soit le type des listes englobantes
	 *
	 * Contenu hors des éléments (liste non fermée : l'analyseur HTML5 y range la suite du texte) : gardé, en
	 * paragraphes après la liste au premier niveau, à la suite de l'élément précédent dans une sous-liste
	 *
	 * @param Dom\Element|DOMElement $liste ul ou ol
	 * @param array $contexte
	 * @return string
	 */
	private static function liste($liste, $contexte) {
		$premier_niveau = ($contexte['listes'] === '');
		$contexte_hors = $contexte;
		$contexte['listes'] .= '*';
		$marque = str_repeat((strtolower($liste->localName) === 'ol') ? '#' : '*', strlen($contexte['listes']));
		$blocs = array();
		$lignes = array();
		$hors = self::sortie();
		foreach ($liste->childNodes as $enfant) {
			if (Arbre::estTexte($enfant) ? trim($enfant->data) === '' : !Arbre::estElement($enfant)) {
				continue;
			}
			$nom = Arbre::estElement($enfant) ? strtolower($enfant->localName) : '';
			$en_cours_hors = (trim($hors['courant']) !== '' or $hors['paragraphes']);
			if ($nom === 'li' or (in_array($nom, array('ul', 'ol')) and !$en_cours_hors)) {
				if ($en_cours_hors) {
					array_push($blocs, ...self::paragraphes($hors));
					$hors = self::sortie();
				}
				$lignes[] = ($nom === 'li') ? self::element_liste($enfant, $marque, $contexte) : self::liste($enfant, $contexte);
				continue;
			}
			if ($premier_niveau) {
				if ($lignes) {
					$blocs[] = join("\n", array_filter($lignes, fn($ligne) => $ligne !== ''));
					$lignes = array();
				}
				self::noeud($enfant, $hors, $contexte_hors);
				continue;
			}
			$texte = self::sortie();
			self::noeud($enfant, $texte, array('listes' => '') + $contexte);
			$suite = join("\n_ ", self::paragraphes($texte));
			if ($suite !== '') {
				$lignes ? $lignes[array_key_last($lignes)] .= "\n_ $suite" : $lignes[] = "-$marque $suite";
			}
		}
		array_push($blocs, ...self::paragraphes($hors));
		if ($lignes) {
			$blocs[] = join("\n", array_filter($lignes, fn($ligne) => $ligne !== ''));
		}
		return join($premier_niveau ? "\n\n" : "\n", array_filter($blocs, fn($bloc) => $bloc !== ''));
	}

	/**
	 * Élément de liste : son texte sur une ligne (paragraphes joints par des sauts de ligne), ses sous-listes à la suite
	 *
	 * @param Dom\Element|DOMElement $element li
	 * @param string $marque
	 * @param array $contexte
	 * @return string
	 */
	private static function element_liste($element, $marque, $contexte) {
		$texte = self::sortie();
		$sous_listes = array();
		foreach ($element->childNodes as $noeud) {
			if (Arbre::estElement($noeud) and in_array(strtolower($noeud->localName), array('ul', 'ol'))) {
				$sous_listes[] = self::liste($noeud, $contexte);
			}
			else {
				self::noeud($noeud, $texte, array('listes' => '') + $contexte);
			}
		}
		return join("\n", array_filter(array(rtrim("-$marque " . join("\n_ ", self::paragraphes($texte))), ...$sous_listes), fn($ligne) => $ligne !== ''));
	}

	/**
	 * Tableau SPIP ; gardé en HTML s'il ne s'écrit pas en raccourcis (tableau imbriqué, cellule sur plusieurs lignes,
	 * fusion de lignes)
	 *
	 * @param Dom\Element|DOMElement $tableau
	 * @param array $contexte
	 * @return string
	 */
	private static function tableau($tableau, $contexte) {
		$lignes = array();
		$legende = '';
		$simple = !self::tableau_complexe($tableau);
		foreach ($tableau->childNodes as $partie) {
			if (!Arbre::estElement($partie)) {
				continue;
			}
			$nom = strtolower($partie->localName);
			if ($nom === 'caption') {
				$legende = self::une_ligne(self::en_ligne($partie, $contexte));
				continue;
			}
			$rangees = ($nom === 'tr') ? array($partie) : self::elements($partie);
			foreach ($rangees as $rangee) {
				if (strtolower($rangee->localName) !== 'tr') {
					continue;
				}
				$cellules = array();
				foreach (self::elements($rangee) as $cellule) {
					$nom_cellule = strtolower($cellule->localName);
					if (!in_array($nom_cellule, array('td', 'th'))) {
						continue;
					}
					$contenu = trim(self::en_ligne($cellule, $contexte));
					if (str_contains($contenu, "\n")) {
						$simple = false;
					}
					$contenu = str_replace('|', '&#124;', $contenu);
					$cellules[] = ($nom_cellule === 'th' and $contenu !== '') ? '{{' . $contenu . '}}' : $contenu;
					// Cellule fusionnée avec les suivantes : < dans chacune
					for ($n = intval($cellule->getAttribute('colspan')); $n > 1; $n--) {
						$cellules[] = '<';
					}
				}
				if ($cellules) {
					$lignes[] = '| ' . join(' | ', $cellules) . ' |';
				}
			}
		}
		if (!$simple) {
			return self::tableau_html($tableau, $contexte);
		}
		// Résumé d'un tableau rendu par SPIP (passe Tableaux) : || légende | résumé ||
		$resume = trim((string) $tableau->getAttribute('data-h2s-resume'));
		if ($legende !== '' or $resume !== '') {
			array_unshift($lignes, '|| ' . str_replace('|', '&#124;', $legende) . ($resume !== '' ? ' | ' . str_replace('|', '&#124;', $resume) : '') . ' ||');
		}
		return join("\n", $lignes);
	}

	/**
	 * Tableau gardé en HTML : structure d'origine, une rangée par ligne, contenu des cellules converti
	 *
	 * @param Dom\Element|DOMElement $element table, ou une de ses parties
	 * @param array $contexte
	 * @return string
	 */
	private static function tableau_html($element, $contexte) {
		$nom = strtolower($element->localName);
		if (in_array($nom, array('td', 'th', 'caption'))) {
			return self::ouvrante($element) . trim(self::en_ligne($element, array('tableau_html' => true, 'autop' => false) + $contexte)) . "</$nom>";
		}
		if (in_array($nom, self::VIDES)) {
			return self::ouvrante($element);
		}
		$parties = array_map(fn($partie) => self::tableau_html($partie, $contexte), self::parties_tableau($element));
		return self::ouvrante($element) . ($nom === 'tr' ? join('', $parties) : "\n" . join("\n", $parties) . "\n") . "</$nom>";
	}

	/**
	 * Enfants attendus des éléments de structure d'un tableau
	 */
	private const PARTIES_TABLEAU = array(
		'table' => array('caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr'),
		'thead' => array('tr'),
		'tbody' => array('tr'),
		'tfoot' => array('tr'),
		'tr' => array('td', 'th'),
		'colgroup' => array('col'),
	);

	/**
	 * Tableau qui ne s'écrit pas en raccourcis : tableau imbriqué, ou cellule fusionnée sur plusieurs lignes
	 *
	 * @param Dom\Element|DOMElement $element
	 * @return bool
	 */
	private static function tableau_complexe($element) {
		foreach (self::elements($element) as $enfant) {
			if (
				strtolower($enfant->localName) === 'table'
				or ($enfant->hasAttribute('rowspan') and $enfant->getAttribute('rowspan') !== '1')
				or self::tableau_complexe($enfant)
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Éléments enfants d'une partie de tableau, sans le contenu égaré (rendu avant le tableau)
	 *
	 * @param Dom\Element|DOMElement $element
	 * @return array
	 */
	private static function parties_tableau($element) {
		$attendus = self::PARTIES_TABLEAU[strtolower($element->localName)] ?? null;
		$elements = self::elements($element);
		return ($attendus === null) ? $elements : array_values(array_filter($elements, fn($enfant) => in_array(strtolower($enfant->localName), $attendus)));
	}

	/**
	 * Contenu égaré dans la structure d'un tableau (texte ou élément hors d'une cellule) : l'analyse HTML5 de PHP 8.4
	 * le place avant le tableau, Masterminds le laisse dedans
	 *
	 * @param Dom\Element|DOMElement $element
	 * @return array nœuds, dans l'ordre du document
	 */
	private static function hors_tableau($element) {
		$attendus = self::PARTIES_TABLEAU[strtolower($element->localName)] ?? null;
		if ($attendus === null) {
			return array();
		}
		$egares = array();
		foreach ($element->childNodes as $enfant) {
			if (Arbre::estElement($enfant) and in_array(strtolower($enfant->localName), $attendus)) {
				array_push($egares, ...self::hors_tableau($enfant));
			}
			elseif (Arbre::estElement($enfant) or (Arbre::estTexte($enfant) and trim($enfant->data) !== '')) {
				$egares[] = $enfant;
			}
		}
		return $egares;
	}

	/**
	 * Texte préformaté : <cadre> pour du code, sinon <poesie> (lignes gardées)
	 *
	 * @param Dom\Element|DOMElement $pre
	 * @param array $contexte
	 * @return string
	 */
	private static function pre($pre, $contexte) {
		$elements = self::elements($pre);
		$code = (count($elements) === 1 and strtolower($elements[0]->localName) === 'code' and trim(str_replace($elements[0]->textContent, '', $pre->textContent)) === '');
		if ($code) {
			return "<cadre>\n" . trim($elements[0]->textContent, "\n") . "\n</cadre>";
		}
		$sortie = self::sortie();
		self::enfants($pre, $sortie, array('pre' => true) + $contexte);
		$texte = trim(join("\n\n", self::paragraphes($sortie)), "\n");
		return ($texte === '') ? '' : "<poesie>\n$texte\n</poesie>";
	}

	/**
	 * Éléments enfants
	 *
	 * @param Dom\Node|DOMNode $parent
	 * @return Dom\Element[]|DOMElement[]
	 */
	private static function elements($parent) {
		return array_values(array_filter(iterator_to_array($parent->childNodes), fn($noeud) => Arbre::estElement($noeud)));
	}

	/**
	 * Balise ouvrante d'un élément, avec ses attributs
	 *
	 * @param Dom\Element|DOMElement $element
	 * @return string
	 */
	private static function ouvrante($element) {
		$balise = '<' . strtolower($element->localName);
		foreach ($element->attributes as $attribut) {
			$balise .= ' ' . $attribut->nodeName . '="' . htmlspecialchars($attribut->value, ENT_COMPAT | ENT_HTML5, 'UTF-8') . '"';
		}
		return $balise . '>';
	}

	/**
	 * Ligne qui commence (ou finit) par un raccourci de bloc de l'option raccourcis_blocs (légende, lecteur… de
	 * Wordpress pour wp2spip)
	 *
	 * @param string $ligne
	 * @param bool $debut true : commence par le raccourci ouvrant ; false : finit par le raccourci fermant
	 * @param array $contexte
	 * @return bool
	 */
	private static function raccourci_bloc($ligne, $debut, $contexte) {
		$motifs = $contexte['raccourcis_blocs'];
		return $motifs !== null && (bool) preg_match($debut ? $motifs['debut'] : $motifs['fin'], $ligne);
	}

	/**
	 * Texte échappé pour SPIP : caractères du HTML et des raccourcis SPIP en entités, sauf les raccourcis de l'option
	 * raccourcis_gardes (le tiret et le souligné de début de ligne : echapper_debut())
	 *
	 * Un texte affiché tel quel ({{a}}, [lien->x], | a |…) est ainsi affiché tel quel par SPIP.
	 *
	 * @param string $texte
	 * @param array $contexte
	 * @return string
	 */
	private static function echapper_texte($texte, $contexte) {
		$morceaux = ($contexte['raccourcis_gardes'] === null) ? array($texte) : preg_split($contexte['raccourcis_gardes'], $texte, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($morceaux as $i => $morceau) {
			if (!($i % 2)) {
				$morceaux[$i] = strtr(self::echapper($morceau), array('{' => '&#123;', '}' => '&#125;', '[' => '&#91;', ']' => '&#93;', '|' => '&#124;', '~' => '&#126;'));
			}
		}
		return join('', $morceaux);
	}

	/**
	 * Tiret ou souligné en début de ligne (liste, filet, saut de ligne pour SPIP) en entité
	 *
	 * @param string $texte
	 * @param bool $toutes_les_lignes false : première ligne seulement (texte déjà converti, dont les lignes suivantes
	 *     commencent par un saut de ligne SPIP)
	 * @return string
	 */
	private static function echapper_debut($texte, $toutes_les_lignes = true) {
		return preg_replace_callback($toutes_les_lignes ? '/^(\s*)([-_])/m' : '/^(\s*)([-_])/', fn($debut) => $debut[1] . '&#' . ord($debut[2]) . ';', $texte);
	}

	/**
	 * Caractères spéciaux du HTML d'un texte réécrits en entités : le texte reste du texte dans SPIP
	 *
	 * @param string $texte
	 * @return string
	 */
	private static function echapper($texte) {
		return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $texte);
	}

	/**
	 * Texte sur une seule ligne (titres, liens, légendes)
	 *
	 * @param string $texte
	 * @return string
	 */
	private static function une_ligne($texte) {
		return trim(preg_replace('/\s*\n(_ )?\s*/', ' ', $texte));
	}

	/**
	 * Sortie en cours : paragraphes terminés, paragraphe courant, sauts de ligne SPIP (_ ) et retours simples en attente
	 *
	 * @return array
	 */
	private static function sortie() {
		return array('paragraphes' => array(), 'courant' => '', 'sauts' => 0, 'retours' => 0);
	}

	/**
	 * Texte ajouté au paragraphe courant, après les sauts de ligne en attente (deux sauts ou plus : nouveau paragraphe)
	 *
	 * @param array $sortie
	 * @param string $texte
	 */
	private static function ecrire(&$sortie, $texte) {
		if ($texte === '') {
			return;
		}
		if (($sortie['sauts'] or $sortie['retours']) and trim($texte) === '') {
			// Blanc après un saut de ligne : sans effet
			return;
		}
		if ($sortie['sauts'] >= 2) {
			self::fin_paragraphe($sortie);
		}
		elseif ($sortie['sauts'] and trim($sortie['courant']) !== '') {
			$sortie['courant'] = rtrim($sortie['courant']) . "\n_ ";
			$texte = ltrim($texte);
		}
		elseif ($sortie['retours'] and trim($sortie['courant']) !== '') {
			$sortie['courant'] = rtrim($sortie['courant']) . "\n";
			$texte = ltrim($texte);
		}
		$sortie['sauts'] = 0;
		$sortie['retours'] = 0;
		if (trim($sortie['courant']) === '' or str_ends_with($sortie['courant'], ' ')) {
			// Pas de blanc en début de paragraphe, ni de blanc doublé
			$texte = ltrim($texte, ' ');
		}
		// Texte en début de ligne : un tiret ou un souligné y serait lu comme un raccourci (marques et liens générés
		// commencent par { [ ou <, jamais par ces caractères)
		if (trim($sortie['courant']) === '' or str_ends_with($sortie['courant'], "\n")) {
			$texte = self::echapper_debut($texte, false);
		}
		$sortie['courant'] .= $texte;
	}

	/**
	 * Fin du paragraphe courant (les sauts de ligne en attente, en fin de paragraphe, sont sans effet)
	 *
	 * @param array $sortie
	 */
	private static function fin_paragraphe(&$sortie) {
		$paragraphe = trim($sortie['courant']);
		if ($paragraphe !== '') {
			$sortie['paragraphes'][] = $paragraphe;
		}
		$sortie['courant'] = '';
		$sortie['sauts'] = 0;
		$sortie['retours'] = 0;
	}

	/**
	 * Bloc déjà formé (liste, tableau, intertitre…), sur son propre paragraphe
	 *
	 * @param array $sortie
	 * @param string $bloc
	 */
	private static function bloc(&$sortie, $bloc) {
		self::fin_paragraphe($sortie);
		if (trim($bloc) !== '') {
			$sortie['paragraphes'][] = $bloc;
		}
	}

	/**
	 * Paragraphes de la sortie, paragraphe courant compris, sans blanc en fin de ligne
	 *
	 * @param array $sortie
	 * @return array
	 */
	private static function paragraphes($sortie) {
		self::fin_paragraphe($sortie);
		return array_map(fn($paragraphe) => preg_replace('/[ \t]+\n/', "\n", $paragraphe), $sortie['paragraphes']);
	}
}
