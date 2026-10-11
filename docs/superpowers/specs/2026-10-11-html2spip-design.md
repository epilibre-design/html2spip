# html2spip — un sale() amélioré : HTML en raccourcis SPIP, y compris le HTML produit par SPIP

Date : 2026-10-11
Origine : convertisseur de wp2spip (`inc/wp2spip_html.php`, spec `2026-10-09-wp2spip-convertisseur-html-design.md` du dépôt wp2spip) et plugin [sale](https://git.spip.net/spip-contrib-extensions/sale).
Statut : design validé par le mainteneur le 2026-10-11 ; formes HTML relevées sur SPIP 4.4.28 avant le plan (annexe B) ; puis **simplifié** le même jour à sa demande : pas d'extraction de page publique, pas de reconstruction de `<multi>`. Reconnaissance des modèles de plugins ajoutée le même jour, avec Albums en premier (§ 4.4). Règles de typographie relevées dans le code de SPIP 4.4.21 le même jour (§ 4.5, annexe A) ; plus aucun volet ouvert.

## 1. Objectif

html2spip est un **`sale()` amélioré**. Il convertit un fragment HTML en texte SPIP (raccourcis typographiques) en analysant un arbre HTML5, et non par une suite d'expressions régulières. Il reprend le convertisseur générique de wp2spip. Il reconnaît aussi ce que SPIP produit, comme sale le fait déjà en partie (modèles de documents, `cadre`, `code`, `poesie`, liens `spip.php?id_article=N`, `&nbsp;:`). Il ajoute les liens internes résolus, les notes, les ancres et l'inversion de la typographie.

Il reste simple : il reçoit un fragment HTML et rend un texte. Ce qui ne peut pas être retrouvé (§ 4.8) est une limite documentée, pas un problème à résoudre.

Exemple. Source : `Voir [->123] pour plus d'infos.`, où l'article 123 a pour titre « Titre de l'article ». Rendu par SPIP :

```html
<p>Voir <a href="Titre-de-l-article" class="spip_in">Titre de l’article</a> pour plus d’infos.</p>
```

html2spip, avec le résolveur du site, doit rendre `Voir [->123] pour plus d'infos.` :
- le texte du lien est égal au titre, donc le lien s'écrit sans texte ;
- `’` est rendu en `'` ;
- les insécables ajoutées par SPIP sont retirées.

## 2. Décisions

| Sujet | Décision |
|---|---|
| Forme | Bibliothèque PHP pure (Composer, namespace `Html2Spip\`, sans SPIP) **et** plugin SPIP `html2spip` dans le même dépôt. Le plugin fournit un résolveur appuyé sur la base et les URL du site, ainsi qu'une fonction `html2spip()` qui remplace `sale()` |
| Entrée | Un fragment HTML : sortie de `propre()`, contenu d'un éditeur, HTML quelconque. Pas d'extraction de page publique ni de reconstitution des champs d'un objet (chapo, texte, ps) : ce serait un autre projet |
| Critère de réussite | Pour les cas gérés : rendu équivalent, `propre(html2spip(propre(T)))` ≡ `propre(T)`, notes comprises, l'équivalence étant comparée sur l'arbre HTML et non sur la chaîne (§ 6.4). Sortie égale à une forme canonique attendue (§ 5). Pour les cas perdus (§ 4.8) : sortie attendue documentée, sans exigence de rendu équivalent |
| `<multi>` | Non reconstruit : SPIP n'en rend qu'une langue, et html2spip convertit ce texte |
| Notes | Recousues en `[[…]]` si l'appel et la note sont dans le fragment reçu. Sinon : appel retiré et avertissement émis, note orpheline gardée en paragraphe (§ 4.2) |
| Modèles | Interface `ReconnaisseurModele` (§ 4.4). Reconnaisseurs fournis : modèles du core SPIP 4.4 (`doc`, `img`, `emb` et ceux qu'ils appellent) et, en option, `<album>` du plugin Albums. Ils dépendent du HTML produit, pas du code des plugins. Un modèle non reconnu est gardé en HTML. Un paquet séparé par plugin (`html2spip-albums`…) ne se justifierait que si ces reconnaisseurs alourdissaient le cœur |
| wp2spip | Le cœur générique est extrait de wp2spip. Ce qui est propre à WordPress (`autop`, marqueurs `wp2spipbloc<N>`, raccourcis `[caption]`…) reste dans wp2spip, par options. La bascule de wp2spip est un second sous-projet |
| Architecture | Deux étapes : reconnaissance des structures SPIP sur l'arbre DOM, puis conversion générique. Écartées : un seul parcours enrichi (logiques mêlées), expressions régulières sur le HTML (défauts de sale : retours arrière, imbrications mal appariées, texte perdu) |
| PHP | 8.1 minimum, comme wp2spip : `Dom\HTMLDocument` à partir de 8.4, Masterminds HTML5-PHP avant |
| Formes HTML | Jamais écrites de mémoire : chaque règle du § 4 est validée par une capture du SPIP 4.4 jetable |

## 3. Architecture

```
src/                      Cœur PHP pur, namespace Html2Spip\
  Convertisseur.php       Point d'entrée : convertir(string $html, ?Options $options = null): string
  Options.php
  Contexte.php            État d'une conversion, journal d'avertissements
  Arbre/                  Analyse HTML5 (Dom\HTMLDocument ou Masterminds), sérialisation
  Reconnaissance/         Étape 1 : interface Passe::appliquer(Element $body, Contexte), une classe par passe
    Code, Notes, Modeles, Liens, Tableaux, Typographie
  Ecriture/               Étape 2 : parcours générique extrait de wp2spip
  Modeles/                Interface ReconnaisseurModele, registre
    Core/                 doc, img, emb (SPIP 4.4)
    Plugins/              Albums (album), en option
  Resolveur.php           Interface
  ResolveurNul.php        Aucun lien interne retrouvé
  ResolveurTableau.php    Données passées en tableau (tests, usage hors SPIP)
  Spip/                   ResolveurSpip, OptionsSpip : utilisables seulement dans SPIP
paquet.xml, html2spip_options.php, inc/html2spip.php
                          Plugin SPIP à la racine du dépôt, comme wp2spip : autoload de src/ et de lib/, fonction html2spip()
spip-cli/                 Commande html2spip:convertir
lib/masterminds-html5/    Copie de Masterminds pour PHP 8.1 à 8.3 hors Composer (comme wp2spip)
tests/unit/               Sans SPIP
tests/integration/        Avec le SPIP jetable
tests/corpus/             Sources, captures, sorties attendues (§ 6)
scripts/installer-spip-jetable.sh
```

### 3.1 Flux

Le HTML est analysé en arbre HTML5. Viennent ensuite deux étapes :

1. **Reconnaissance**, passes dans cet ordre :
   - **Code**, d'abord, pour protéger le texte brut ;
   - **Notes**, **Modèles**, **Liens**, **Tableaux** ;
   - **Typographie**, en dernier, sur les nœuds texte restants.
2. **Écriture** : conversion générique, qui produit le texte SPIP.

Une passe remplace une structure reconnue par un **nœud raccourci**. C'est un élément privé qui porte le texte SPIP déjà produit, que l'étape 2 recopie sans échappement. Une structure non reconnue, ou reconnue en partie, est laissée intacte et passe à la conversion générique : le contenu n'est jamais perdu.

### 3.2 Résolveur

```php
interface Resolveur {
    /** Objet désigné par une URL du site : ['type' => 'article', 'id' => 123] ou null */
    public function objet(string $url): ?array;
    /** Titre tel que SPIP l'affiche comme texte de lien (supprimer_numero + typo), ou null s'il n'existe pas */
    public function titreAffiche(string $type, int $id): ?string;
    /** Champ d'un objet tel qu'un modèle l'affiche : titre (supprimer_numero + typo), descriptif (propre)…, ou null */
    public function champAffiche(string $type, int $id, string $champ): ?string;
}
```

`ResolveurSpip` (plugin) s'appuie sur `urls_decoder_url()`, `sql_*` et les fonctions de typo de SPIP. Le résolveur par défaut est `ResolveurNul`. Sans résolveur, un lien interne reste `[texte->url]`, sauf si l'URL contient le type et l'id (§ 4.1).

### 3.3 Options

- `resolveur` : `ResolveurNul` par défaut.
- `langue` : choisit la table de typographie à inverser (`fr` par défaut).
- `passes` : passes actives. Une liste vide donne la conversion générique seule (usage de wp2spip).
- `modeles` : reconnaisseurs actifs (ceux du core par défaut ; `Albums` à ajouter ; extensible par l'appelant). Le plugin SPIP active `Albums` si le plugin Albums est actif.
- `autop`, `marqueurs` : options de l'écriture reprises de wp2spip, pour son seul usage.

### 3.4 Erreurs

Aucune exception sur du HTML mal formé : l'analyse HTML5 le répare. `Contexte::avertir()` journalise les abandons : lien interne non résolu, appel sans note, note sans appel, modèle inconnu. `Convertisseur::avertissements()` les rend après conversion. La commande spip-cli les affiche avec `-v`.

## 4. Règles

Chaque règle est validée par une capture (§ 6.3). En cas d'écart, la capture fait foi et cette section est corrigée.

### 4.1 Liens et ancres

| HTML produit par SPIP | Sortie |
|---|---|
| lien interne (`a.spip_in`, ou sans classe pour un objet absent) dont le résolveur trouve l'objet | Raccourci d'objet : `123` (article), `rub5`, `br3`, `aut2`, `mot4`, `site7`, `doc8`, avec `#ancre` gardée. Si le texte du lien est égal à `titreAffiche()` : `[->123]`, sinon `[texte->123]` |
| `title` | `[texte\|bulle->123]` ; une langue de bulle n'est rendue que dans `title` (`title="bulle\|en"` pour `[x\|bulle\|en->1]`), elle reste donc dans la bulle |
| Lien interne non résolu (objet absent, non publié, pas de résolveur) | Si l'URL contient le type et l'id (`spip.php?article999`, `article999.html`, `?page=…&id_article=…`) : raccourci et avertissement. SPIP rend un objet absent sans classe, avec pour texte « article 999 » : ce texte donne `[->999]`, un autre `[texte->999]`. Sinon : `[texte->url]` et avertissement |
| `a.spip_out` | `[texte->url]` |
| `a.spip_mail` (`mailto:`) | `[->adresse]` si le texte est l'adresse, sinon `[texte->mailto:adresse]` |
| `a.spip_url.auto` (URL nue rendue en lien) | URL nue |
| `a.spip_glossaire` | `[?terme]` si le texte est le terme de l'URL du glossaire ; sinon `[texte->url]` et avertissement |
| `a.spip_in` vers un fichier de document (`IMG/…`, `[->doc8]`) | le résolveur retrouve le document par son fichier : `[->doc8]` |
| Ancre vide (`a` avec `id` ou `name`, sans `href` ni texte) | `[nom<-]` |

### 4.2 Notes

Formes relevées (annexe B) : appel `<span class="spip_note_ref">&nbsp;[<a href="#nbN" class="spip_note" … id="nhN">N</a>]</span>` ; note `<div id="nbN"><p><span class="spip_note_ref">[<a href="#nhN" …>N</a>]&nbsp;</span>contenu</p><p>…</p></div>`, rendue par `#NOTES`.

Les appels sont associés aux notes `div[id=nbN]` **présentes dans le même fragment** (dans un `div.notes` ou non). Le `span.spip_note_ref` de l'appel est remplacé par `[[contenu]]`. Le contenu de la note, sans son renvoi, est converti récursivement, ses paragraphes séparés par une ligne vide (SPIP les rend en `<p>`). Les numéros automatiques se suivent (1, 2…) en ignorant les notes numérotées à la main : un numéro différent du suivant attendu donne `[[<x>contenu]]`. Les `div` des notes, puis le `div.notes` vide, sont ensuite retirés.

Cas incomplets :
- appel sans note dans le fragment : l'appel est retiré, avertissement ;
- note sans appel : elle est gardée en paragraphe à la fin du texte, avertissement.

### 4.3 Structures

| HTML | Sortie |
|---|---|
| `h2.spip` | `{{{…}}}` (règle de l'écriture) |
| `strong`, `em` | `{{…}}`, `{…}` |
| `br.autobr` | retour simple |
| autre `br` (dont `br.manualbr`) | `_ ` |
| `hr.spip` | `----` |
| `ul.spip`, `ol.spip` | `-*`, `-#`, avec profondeur et listes mixtes (règles de wp2spip) |
| `table.spip` | `\|\|légende\|résumé\|\|` (résumé lu dans `caption small.summary`) puis `\| … \|` ; en-têtes `{{…}}` ; `colspan` en `<` ; attributs `id`, `headers`, classes de rangées ignorés |
| `blockquote.spip` | `<quote>…</quote>` |
| `blockquote.spip_poesie` (une ligne par `div`) | `<poesie>…</poesie>` |
| `textarea.spip_cadre` | `<cadre>…</cadre>`, texte brut |
| `code.spip_code` en ligne | `<code>…</code>`, texte brut |
| HTML d'un modèle reconnu (§ 4.4) | `<docN\|left>`, `<albumN\|left>`… |
| paragraphes `p` | séparés par une ligne vide |
| autres éléments | règles du convertisseur de wp2spip |

### 4.4 Modèles

```php
interface ReconnaisseurModele {
    /** Raccourci du modèle dont l'élément est la racine (ex. "<album12|left>"), ou null s'il n'est pas reconnu */
    public function reconnaitre(Element $element, Contexte $contexte): ?string;
}
```

La passe Modèles parcourt l'arbre et propose chaque élément aux reconnaisseurs actifs. Le premier qui rend un raccourci remplace l'élément par un nœud raccourci. Un reconnaisseur ne peut pas savoir si un paramètre sans trace dans le HTML figurait dans le raccourci d'origine. Il signale donc par `Contexte::avertir()` que le modèle a des paramètres **non déductibles**, et les nomme, sans affirmer qu'ils ont été perdus.

**Core** (`doc`, `img`, `emb`) : dans SPIP 4.4, `<docN>`, `<imgN>` et `<embN>` donnent le **même** HTML (annexe B) : `div.spip_document_N.spip_document`, alignement dans `spip_documents_<align>`. La forme canonique est donc `<docN|align>`, ou `<docN>` si l'alignement est celui que SPIP donne sans paramètre (relevé par la capture de `<docN>`).

**Albums** (plugin Albums **4.4.1** : `modeles/album.html`, `modeles/album_fonctions.php`, `albums_fonctions.php`). Le modèle rend un `<figure class="album album_model …" id="modele_album_<id>">`. Les règles se fondent sur le code, pas sur le commentaire en tête de `album.html`, qui n'est plus à jour : il cite `ligne` et `mosaique`.

Les valeurs par défaut du site comptent :
- la disposition : paramètre, puis constante `_ALBUMS_DISPOSITION`, puis configuration `albums/disposition`, puis la **première** disposition de `albums_decrire_dispositions()` **après** le pipeline du même nom (`inline` sans autre plugin) ;
- la position de la légende : paramètre, puis `_ALBUMS_POSITION_LEGENDE`.

Le reconnaisseur reçoit ces défauts calculés en option, ainsi que la liste des dispositions et de leur `modele_doc`. Le plugin SPIP les calcule sur le site par les mêmes fonctions qu'Albums (constantes, `lire_config()`, `albums_decrire_dispositions()`), pipeline compris. Hors SPIP, sans option, les valeurs d'Albums 4.4.1 sont prises (`inline`, pas de position).

| Dans le HTML | Paramètre retrouvé |
|---|---|
| `id="modele_album_12"` (`calculer_id_modele_album()`) | `<album12>` (`id_album`) |
| `id="modele_album_article_5"` | `<album\|objet=article\|id_objet=5>` |
| `id="modele_album_documents_<md5>"` | `id_document=3,4,7`, lu dans les classes `spip_document_N` des `li.album__item` (`#ALBUM_DOC`), dans l'ordre affiché |
| classe `album_<disposition>`, **toujours présente** : `#ALBUM_PARAMS` calcule toujours une disposition | valeurs de `albums_decrire_dispositions()` : `inline`, `masonry`, `mosaic`, `line`, `list`, `browse`, `simple`, et celles qu'ajoute le pipeline `albums_decrire_dispositions`. `disposition=…` est écrit sauf si la valeur est la disposition par défaut du site |
| classe `left`, `right` ou `center` | alignement `\|left`… |
| classe `album_caption-<position>`, écrite seulement si `position_legende` ou `_ALBUMS_POSITION_LEGENDE` est défini | `position_legende=…` sauf si la valeur est celle de `_ALBUMS_POSITION_LEGENDE` sur le site |
| classe `album__doc_<modele_doc>` des documents (`#ALBUM_DOC`) | `modele_doc=…` si elle diffère du modèle de la disposition (`doc` ou `file_album`) |
| autres classes du `figure` : hors `album`, `album_model`, `album_<disposition>`, `album_images`/`album_mixed` (calculées), `album_caption-*`, alignement, `vignettes` | `classe=…` |
| `figcaption` absent alors que l'album a un titre, un descriptif **ou des crédits** (`champAffiche`) : le modèle affiche la légende dès que l'un des trois est renseigné | `masquer_legende=oui` |
| `.album__heading`, `.album__text`, `.album__credits` | rien s'ils égalent `champAffiche('album', id, …)` ; sinon `titre=…`, `descriptif=…`, `credits=…` |

Paramètres **non déductibles** : `largeur`, `hauteur`, `recadrer_ratio`, `recadrer_sens`, `par`, `sens`, `pagination`, `pagination_type`, `media`, `extension`, `mode`, `vu`. Leurs effets (images retaillées, ordre, filtrage) ne se lisent pas de façon fiable dans le HTML. L'avertissement les nomme comme non déductibles pour chaque album reconnu. Il ne dit pas qu'ils étaient présents.

### 4.5 Typographie

`typo()` (et `propre()`, par `corriger_typo()`) applique la typographie de la langue du texte. `lang_typo()` (`ecrire/inc/lang.php`) choisit la table **fr** pour `fr`, `fr_*`, `eo` et `cpf`, et la table **en** pour toutes les autres langues. L'option `langue` choisit donc l'une des deux inversions. Les règles exactes, relevées dans le code de SPIP 4.4, forment l'annexe A.

Principe : un caractère introduit par la typographie n'est retiré que si elle le remettra au rendu.

- `’` → `'`. Un `’` tapé par l'auteur s'affiche de la même façon.
- Espace insécable (U+00A0) à un endroit où SPIP en ajoute une (annexe A, règles fr 3 à 7 et en 2) → espace normale, ou rien si aucune espace ne la touchait dans le HTML (`a&nbsp;:` → `a :`). SPIP la remet, que la source ait été `a:`, `a :` ou `a~:`.
- Toute autre espace insécable → `~` : l'auteur l'avait voulue.
- `—` produit par `--` : gardé en caractère. Le rendu est le même, et le texte se lit mieux.
- Les guillemets `« »` `“ ”` et `°` restent des caractères UTF-8. SPIP ne fait que les écrire en entités numériques, et ne convertit pas `<<` ni `>>`.
- Les espaces fines (U+202F) ne sont jamais produites par SPIP 4.4 : elles sont gardées telles quelles.
- La passe ne touche que les nœuds texte hors code (`<code>`, `<cadre>`) et hors attributs, comme `corriger_typo()`, qui protège les balises HTML.

### 4.6 Échappement

L'échappement de wp2spip est repris tel quel. Un texte qui ressemble à un raccourci SPIP est écrit en entités numériques : `{` `}` `[` `]` `|` `~`, ainsi qu'un `-` ou un `_` en début de ligne. `&`, `<` et `>` sont écrits en entités. Le rendu reste ainsi le même.

### 4.7 Ce que html2spip ne fait pas

- extraire le contenu d'une page publique, ou reconstituer les champs d'un objet (chapo, texte, ps) ;
- reconstruire `<multi>` à partir de plusieurs rendus.

### 4.8 Limites (cas perdus)

Ces cas sont documentés et figurent dans le corpus avec leur sortie attendue :

- `<multi>` : seul le texte de la langue rendue est gardé ;
- notes dont l'appel ou le contenu n'est pas dans le fragment (§ 4.2) ;
- contenu de `<html>…</html>` : converti comme du HTML quelconque (`<b>` y devient `{{…}}`) ;
- forme exacte parmi des variantes équivalentes : `[->art123]` ou `[->123]`, `a:` ou `a :`, `--` ou `—` ;
- modèles non reconnus : restent en HTML ;
- paramètres de modèles sans trace dans le HTML (§ 4.4).

## 5. Forme canonique

- Liens d'objet : `[->123]` si le texte est le titre affiché, sinon `[texte->123]`, avec les raccourcis courts (`123`, `rub5`…).
- Typographie : apostrophes droites, `« »` `“ ”` `—` en caractères, espace normale là où SPIP ajoute une insécable, `~` seulement pour les insécables voulues.
- Paragraphes séparés par une ligne vide, aucun blanc en fin de ligne, aucune ligne vide en tête ni en fin.
- Notes à leur place d'appel.

## 6. Tests

### 6.1 SPIP jetable

`scripts/installer-spip-jetable.sh` est idempotent, sur le modèle de `scripts/install-spip-test.sh` de wp2spip :

1. SPIP-Cli autonome dans `vendor/`, puis `core:telecharger spip -R 4.4`, qui donne la dernière 4.4.x. La version est notée dans `tests/spip-jetable/VERSION` et affichée par les tests ;
2. installation en SQLite dans `tests/spip-jetable/` (ignoré par git), administrateur avec un mot de passe aléatoire ;
3. plugin html2spip lié depuis `spip/`, puis activé ; plugin Albums en **version fixée 4.4.1**, celle dont les règles du § 4.4 sont tirées, téléchargée depuis l'étiquette du dépôt git.spip.net puis activée. Sa version, lue dans `paquet.xml`, est notée dans `tests/spip-jetable/VERSION` avec celle de SPIP. Les tests échouent si elle diffère. Passer à une autre version d'Albums demande de relire les règles, puis de régénérer et revoir les captures ;
4. configuration : celle d'une installation neuve (`autobr` actif, URL `page`) ;
5. chargement du corpus (§ 6.2) avec des **id fixes**, insérés explicitement, pour que les liens du corpus restent stables ;
6. `--reinitialiser` : suppression complète et réinstallation.

### 6.2 Corpus

`tests/corpus/sources/` contient un fichier par objet : un en-tête (type, id, titre, rubrique, langue, statut, `limite` si l'objet illustre un cas perdu) suivi du texte SPIP. On y trouve aussi des documents versionnés (petites images, un PDF). Cas couverts :

- liens vers article, rubrique, brève, auteur, mot, site et document :
  - avec et sans texte, texte égal ou non au titre ;
  - titres numérotés (`10. Titre`), titres contenant `'` ou `:` ;
  - bulle, langue, `#ancre` ;
- liens cassés : `[->999]`, `[->rub999]`, ancre absente, objet non publié ;
- liens externes, `mailto`, URL nues ;
- glossaire : `[?terme]`, `[texte->?terme]` ;
- ancres `[x<-]` ;
- notes simples, multiples, numérotées à la main, contenant liens, gras ou listes ;
- intertitres, gras et italique imbriqués ;
- listes mixtes et profondes ;
- tableaux : en-têtes, légende, résumé, cellules fusionnées ;
- `<quote>`, `<cadre>`, `<code>`, `<poesie>` ;
- `<docN|left>`, `<imgN>`, `<embN>` ;
- albums :
  - `<albumN>`, par objet (`objet`, `id_objet`), par liste de documents (`id_document`) ;
  - avec chaque disposition (`inline` par défaut, `line`, `mosaic`, `masonry`, `list`, `browse`, `simple`), alignement, `position_legende`, `classe`, `modele_doc`, `masquer_legende` (dont un album qui n'a que des crédits), titre personnalisé ;
  - défaut du site changé par `albums/disposition`, et par une disposition ajoutée en tête par le pipeline `albums_decrire_dispositions` (petit plugin de test) ;
  - albums dont un paramètre non déductible change le rendu (`largeur`, `recadrer_ratio`, `par`, `pagination`, `media`) : marqués `limite`, donc exclus du test de rendu équivalent ;
- typographie, en français et en anglais : chaque règle de l'annexe A ; `C’est` tapé avec `’` dans la source (rendu en caractère par SPIP, sortie `C'est` rendue en `&#8217;`) ; `~` voulus (entre deux mots, devant un signe où SPIP n'en met pas) ; `10:30`, `::`, `[?…]`, `(!)` sans insécable ; abréviations ; `--` et `---` ; `« »` et `“ ”` ;
- textes qui ressemblent à des raccourcis ;
- `_ ` et retours simples ;
- cas perdus (`limite`) : `<multi>`, `<html>`, modèle de plugin non reconnu (modèle du plugin de test `html2spip_test`).

### 6.3 Captures

`tests/corpus/captures/<type_url>/` est produit par `spip php:eval` pour chaque objet. Une capture contient `propre()` du texte, suivi du rendu de `#NOTES` dans le même fragment, comme le ferait un squelette qui les affiche ensemble. Les types d'URL couverts sont `page` et `propres` ; les autres passent par le même `urls_decoder_url()`. Les captures sont **versionnées**, ce qui permet aux tests unitaires de tourner sans SPIP.

### 6.4 Suites

- **Unitaires** (sans SPIP) : une suite par passe, par reconnaisseur de modèle et pour l'écriture, sur des extraits de captures, avec `ResolveurTableau`. Les tests du convertisseur de wp2spip y sont ajoutés, repris pour l'étape 2.
- **Intégration** (SPIP jetable), pour chaque objet et chaque type d'URL :
  - **forme canonique** : la sortie égale `tests/corpus/attendus/<objet>.spip`, pour tous les objets, cas perdus compris ;
  - **rendu équivalent**, sauf pour les objets `limite` :
    - SPIP garde les notes dans un état global (`inc/notes`) et `#NOTES` les rend à part ;
    - pour la source, puis pour la sortie : l'état des notes est remis à zéro, `propre()` est appelé, puis le rendu des notes est lu ;
    - on compare le couple (rendu principal, rendu des notes), si bien qu'une conversion qui perd une note échoue ;
    - la comparaison porte sur l'**arbre HTML normalisé**, pas sur la chaîne. Les deux fragments sont analysés par le même analyseur HTML5 (`Arbre/`), puis sérialisés de façon canonique : entités décodées en caractères UTF-8 (`&#8217;` et `’`, `&nbsp;` et U+00A0 deviennent identiques), attributs triés, blancs entre éléments de bloc retirés. Les blancs à l'intérieur du texte sont comparés tels quels. Exemple vérifié sur SPIP 4.4 : `propre("C’est")` donne `<p>C’est</p>` et `propre("C'est")` donne `<p>C&#8217;est</p>`, deux rendus équivalents ;
    - en cas d'écart, le test affiche le diff des deux sérialisations canoniques ;
  - **captures** : les captures régénérées égalent les captures versionnées. Un écart signale un changement de SPIP 4.4.x, à revoir avant de mettre à jour les captures.
- `composer test` lance les tests unitaires. `composer test-integration` installe le SPIP jetable si besoin, puis lance tout.

## 7. Sous-projets

1. **html2spip** (ce document) : cœur, plugin, SPIP jetable, corpus, tests.
2. **Bascule de wp2spip** :
   - wp2spip dépend de html2spip par Composer, avec les passes de reconnaissance SPIP désactivées et les options `autop` et marqueurs ;
   - ses tests unitaires, d'intégration et `composer tests-import` servent de non-régression ;
   - `inc/wp2spip_html.php` est retiré.

## Annexe A — Typographie de SPIP 4.4

Relevée le 2026-10-11 dans SPIP 4.4.21 : `ecrire/typographie/fr.php` (`typographie_fr_dist`), `ecrire/typographie/en.php` (`typographie_en_dist`), `ecrire/inc/texte.php` (`corriger_typo`), `ecrire/inc/lang.php` (`lang_typo`). La version 4.4.x installée par les tests est comparée à cette annexe : un changement de ces fichiers fait échouer les captures (§ 6.4) et demande de relire l'annexe.

### Fonctionnement commun

- `corriger_typo()` protège les caractères ``!':;?~%-`` à l'intérieur des balises HTML, puis traite à part les blocs `<multi>` et les idiomes, chacun dans sa langue.
- Dans les deux tables, toute insécable (`&nbsp;`, U+00A0) devient un `~` interne. Les espaces qui touchent un `~` sont fusionnées avec lui (`/ *~+ */` → `~`), puis chaque `~` devient `&nbsp;`. Une insécable de la sortie remplace donc aussi les espaces normales qui l'entouraient dans la source.

### Table fr (`fr`, `fr_*`, `eo`, `cpf`)

| N° | SPIP fait | Inversion | Cas du corpus |
|---|---|---|---|
| fr 1 | `'` → `&#8217;` (`’`) | `’` → `'` | apostrophes, titres de liens avec `'` |
| fr 2 | `&raquo;` `&laquo;` `&rdquo;` `&ldquo;` `&deg;` et les caractères `»` `«` `”` `“` `°` → entités numériques | rien : l'arbre DOM les décode en caractères, gardés tels quels | guillemets, `n°` |
| fr 3 | insécable ajoutée devant tout `;`, sauf celui qui termine une entité (`&amp;`, `&#123;`) | insécable → espace | `a; b`, `a ; b`, `é;`, entités échappées intactes |
| fr 4 | insécable ajoutée devant `»`, devant ` -,` et ` --,` (toujours), et devant `:` non suivi de `:` et ` %`, si ces deux-là sont suivis d'un non-mot ou de la fin du texte | insécable → espace | `a: b`, `a : b`, `10 %`, `« x »` ; `10:30`, `a::b`, `10 %x` sans insécable |
| fr 5 | insécable ajoutée devant le premier `!` ou `?` d'une suite, sauf après `[` `<` `(` `!` `?` `.` | insécable → espace | `Quoi ?!`, `(!)`, `[?terme]` sans insécable |
| fr 6 | insécable ajoutée après `«`, `M.`, `MM.`, `Mme`, `Mmes`, `Mr`, `Mr.`, `M°`, `n°`, `N°` suivis d'une espace | insécable → espace | `« x »`, `Mme Dupont`, `n° 5` |
| fr 7 | insécables retirées dans les URL (`http~://…~?`) | rien | URL nue suivie de `?` |
| fr 8 | `--` → `&mdash;` (pas dans `---`, pas en début de ligne après `-`) | `—` gardé en caractère | `a -- b`, `---` intact |
| fr 9 | autre insécable (tapée `~` ou `&nbsp;`) gardée | `~` | `100~km`, `a~b` |

### Table en (toutes les autres langues)

| N° | SPIP fait | Inversion | Cas du corpus |
|---|---|---|---|
| en 1 | `'` → `&#8217;` (`’`) | `’` → `'` | apostrophes |
| en 2 | insécable ajoutée devant ` -,` et ` --,` | insécable → espace | `a -, b` |
| en 3 | `--` → `&mdash;` (mêmes exceptions que fr 8) | `—` gardé en caractère | `a -- b` |
| en 4 | autre insécable gardée | `~` | `a~:`, `100~km` |

En anglais, une insécable devant `:` `;` `!` `?` n'est jamais ajoutée par SPIP : elle devient donc `~` (en 4).

### Limites

- Une insécable voulue par l'auteur à un endroit où SPIP en ajoute une (`a~:` en français) devient une espace normale. Le rendu est équivalent.
- Les espaces normales qui entouraient une insécable dans la source sont perdues (fusion). Le rendu est équivalent.
- Le pipeline `pre_typo`/`post_typo` d'un plugin peut ajouter d'autres transformations, qui ne sont pas inversées.

## Annexe B — Formes HTML relevées sur SPIP 4.4.28

Relevées le 2026-10-11 par `propre()` et `calculer_notes()` sur le SPIP de test de wp2spip (URL `page`, `autobr` actif). Les captures du corpus les remplacent dès qu'elles existent.

| Source | HTML |
|---|---|
| `[->999]` (objet absent) | `<a href="spip.php?article999">article 999</a>` (sans classe) |
| `[->aut1]` (objet présent) | `<a href="spip.php?auteur1" class="spip_in">Admin</a>` |
| `[x\|bulle\|en->1]` | `<a href="spip.php?article1" title="bulle\|en">x</a>` |
| `[spip->https://www.spip.net]` | `<a href="https://www.spip.net" class="spip_out" rel="external">spip</a>` |
| URL nue | `<a href="…" class="spip_url spip_out auto" rel="nofollow external">…</a>` |
| `[->a@b.fr]`, `[mail->mailto:a@b.fr]` | `<a href="mailto:a@b.fr" class="spip_mail">a@b.fr</a>`, `… class="spip_mail">mail</a>` |
| `[?SPIP]` | `<a href="https://fr.wikipedia.org/wiki/SPIP" class="spip_glossaire" rel="external">SPIP</a>` |
| `[texte->?SPIP]` | `<a href="?SPIP">texte</a>` : pas un lien de glossaire |
| `[ici<-]` | `<a id="ici"></a>` |
| `[->doc611]` | `<a href="IMG/jpg/canola2.jpg" class="spip_in" type='image/jpeg'>canola2</a>` |
| `Un[[Note]]` | `Un<span class="spip_note_ref">&nbsp;[<a href="#nb1" class="spip_note" rel="appendix" title="Note" id="nh1">1</a>]</span>` |
| `#NOTES` | `<div id="nb1">\n<p><span class="spip_note_ref">[<a href="#nh1" class="spip_note" title="Notes 1" rev="appendix">1</a>]&nbsp;</span>Note</p>\n</div>` ; `[[<xx>…]]` donne `nbxx`/`nhxx`, et la note automatique suivante garde le numéro 2 |
| `{{{Inter}}}` | `<h2 class="spip">Inter</h2>` |
| `-* a` / `-** b` | `<ul class="spip" role="list"><li> a\n<ul class="spip" role="list"><li> b</li></ul></li>…` |
| `----` | `<hr class="spip" />` |
| retour simple, `_ ` | `<br class='autobr' />`, `<br class='manualbr' />` |
| tableau | `<table class="table spip" aria-describedby="…"><caption>Légende<br /> <small id="…" class="summary offscreen">Résumé</small></caption><thead><tr class='row_first'><th id='…'> A </th>…`, `<td colspan='2' headers='…'>x</td>` |
| `<quote>` | `<blockquote class="spip">\n<p>cité</p>\n</blockquote>` |
| `<cadre>` | `<textarea readonly='readonly' cols='40' rows='2' class='spip_cadre spip_cadre_block' dir='ltr'>a {{b}}</textarea>` |
| `<code>` | `<code class='spip_code spip_code_inline' dir='ltr'>x&lt;y</code>` |
| `<poesie>` | `<blockquote class="spip_poesie"><div>v1</div>\n<div>v2</div>\n</blockquote>` |
| `<doc611\|left>`, `<img611>`, `<emb611\|right>`, `<img611\|center>` | même modèle : `<div class='spip_document_611 spip_document spip_documents spip_document_image spip_documents_left spip_document_left spip_document_avec_legende' …><figure class="spip_doc_inner"><img …/><figcaption class='spip_doc_legende'>…</figcaption></figure></div>` ; `<img611>` sans alignement donne `spip_documents_center` |
| `<multi>[fr]Bonjour[en]Hello</multi>` | `<p>Bonjour</p>` |
| typographie fr | `C&#8217;est&nbsp;: oui&nbsp;; non&nbsp;! quoi&nbsp;? &#171;&nbsp;a&nbsp;&#187; Mme&nbsp;X n&#176;&nbsp;5 10&nbsp;% a &mdash; b 10:30 a&nbsp;b` |

`urls_decoder_url()` décode `spip.php?article12` en `['article', ['id_article' => '12', …], '']`, mais rend `null` pour `spip.php?page=article&id_article=3` et pour une URL suivie de `#ancre` : le résolveur retire l'ancre avant, et lit lui-même `page` et `id_<type>`.
