# html2spip — conversion de HTML en raccourcis SPIP, et retour du HTML produit par SPIP

Date : 2026-10-11
Origine : convertisseur de wp2spip (`inc/wp2spip_html.php`, spec `2026-10-09-wp2spip-convertisseur-html-design.md` du dépôt wp2spip).
Statut : design validé par le mainteneur le 2026-10-11, à planifier.

## 1. Objectif

html2spip convertit du HTML en texte SPIP (raccourcis typographiques). Il reprend le convertisseur générique de wp2spip, et sait en plus **inverser le HTML produit par SPIP** : liens internes, notes, modèles, typographie, etc.

Exemple. Source : `Voir [->123] pour plus d'infos.`, où l'article 123 a pour titre « Titre de l'article ». Rendu par SPIP :

```html
<p>Voir <a href="Titre-de-l-article" class="spip_in">Titre de l’article</a> pour plus d’infos.</p>
```

html2spip doit rendre `Voir [->123] pour plus d'infos.` : texte du lien égal au titre, donc lien sans texte, `’` rendu en `'`, insécables ajoutées par SPIP retirées.

## 2. Décisions

| Sujet | Décision |
|---|---|
| Forme | Bibliothèque PHP pure (Composer, namespace `Html2Spip\`, sans SPIP) **et** plugin SPIP `html2spip` dans le même dépôt, qui fournit un résolveur appuyé sur la base et les URL du site |
| Entrées | Sortie de `propre()` (avec `#NOTES`) **et** page publique complète (extraction du contenu, squelette dist d'abord) |
| Critère de réussite | Principal : rendu identique, `propre(html2spip(propre(T))) == propre(T)`, notes comprises. En plus : forme canonique documentée (§ 5), comparée à des sorties attendues |
| `<multi>` | Rendu d'une seule langue : texte de cette langue. En option, plusieurs rendus (un par langue) alignés nœud par nœud : `<multi>[fr]…[en]…</multi>` reconstruit là où les textes diffèrent |
| Modèles | Modèles du core SPIP 4.4 reconnus (`doc`, `img`, `emb` et ceux qu'ils appellent) ; registre extensible pour ceux des plugins ; modèle non reconnu gardé en HTML |
| wp2spip | Le cœur générique est extrait de wp2spip. Ce qui est propre à WordPress (`autop`, marqueurs `wp2spipbloc<N>`, raccourcis `[caption]`…) reste dans wp2spip, par options. La bascule de wp2spip est un second sous-projet |
| Architecture | Deux étapes : reconnaissance des structures SPIP sur l'arbre DOM, puis conversion générique (approche A). Écartées : un seul parcours enrichi (logiques mêlées, notes à déplacer), inversion des règles TextWheel par expressions régulières (défauts de sale) |
| PHP | 8.1 minimum, comme wp2spip : `Dom\HTMLDocument` à partir de 8.4, Masterminds HTML5-PHP avant |
| Formes HTML | Jamais écrites de mémoire : chaque règle du § 4 est validée par une capture du SPIP 4.4 jetable |

## 3. Architecture

```
src/                      Cœur PHP pur, namespace Html2Spip\
  Convertisseur.php       Point d'entrée : convertir(string|array $html, Options $options): string
  Options.php
  Contexte.php            État d'une conversion, journal d'avertissements
  Arbre/                  Analyse HTML5 (Dom\HTMLDocument ou Masterminds), sérialisation
  Multi/                  Étape 0 : alignement de N rendus, nœuds <multi>
  Reconnaissance/         Étape 1 : interface Passe::appliquer(Arbre, Contexte), une classe par passe
    Code, Notes, Modeles, Liens, Ancres, Tableaux, Intertitres, Typographie
  Ecriture/               Étape 2 : parcours générique extrait de wp2spip
  Extraction/             Contenu d'une page publique (dist)
  Resolveur.php           Interface
  ResolveurNul.php        Aucun lien interne retrouvé
  ResolveurTableau.php    Données passées en tableau (tests, usage hors SPIP)
spip/                     Plugin SPIP : paquet.xml, ResolveurSpip, commande spip-cli html2spip:convertir
tests/unit/               Sans SPIP
tests/integration/        Avec le SPIP jetable
tests/corpus/             Sources, captures, sorties attendues (§ 6)
scripts/installer-spip-jetable.sh
```

### 3.1 Flux

HTML, ou N HTML pour `<multi>` → analyse en arbre HTML5 → étape 0 (Multi, si plusieurs rendus) → étape 1, passes dans cet ordre : **Code** (protège le texte brut), **Notes**, **Modèles**, **Liens**, **Ancres**, **Tableaux**, **Intertitres**, **Typographie** (en dernier, sur les nœuds texte restants) → étape 2 (écriture) → texte SPIP.

Une passe remplace une structure reconnue par un **nœud raccourci** : un élément privé portant le texte SPIP déjà produit, que l'étape 2 recopie sans échappement. Une structure non reconnue, ou reconnue en partie, est laissée intacte et passe à la conversion générique. Le contenu n'est jamais perdu.

### 3.2 Résolveur

```php
interface Resolveur {
    /** Objet désigné par une URL du site : ['type' => 'article', 'id' => 123] ou null */
    public function objet(string $url): ?array;
    /** Titre brut de l'objet (avant supprimer_numero et typo), ou null s'il n'existe pas */
    public function titre(string $type, int $id): ?string;
    /** Titre tel que SPIP l'affiche comme texte de lien (supprimer_numero + typo), ou null */
    public function titreAffiche(string $type, int $id): ?string;
}
```

`ResolveurSpip` (plugin) s'appuie sur `urls_decoder_url()`, `sql_*` et les fonctions de typo de SPIP. `ResolveurNul` est utilisé par défaut. Sans résolveur, un lien interne reste `[texte->url]`, à moins que l'URL ne contienne l'id (§ 4.1).

### 3.3 Options

- `resolveur` : `ResolveurNul` par défaut.
- `langue` : choisit la table de typographie à inverser (`fr` par défaut).
- `passes` : passes actives. Liste vide : conversion générique seule (usage de wp2spip).
- `modeles` : registre des modèles reconnus (core par défaut, extensible).
- `autobr` : vrai si le site rend les retours simples en `<br class="autobr">`.
- `autop`, `marqueurs` : options de l'écriture reprises de wp2spip, pour son seul usage.

### 3.4 Erreurs

Aucune exception sur du HTML mal formé : l'analyse HTML5 le répare. `Contexte::avertir()` journalise les abandons, par exemple lien interne non résolu, note sans appel ou appel sans note, modèle inconnu. `Convertisseur::avertissements()` les rend après conversion. La commande spip-cli les affiche avec `-v`.

## 4. Règles d'inversion

Chaque règle est validée par une capture (§ 6.3). En cas d'écart, la capture fait foi et cette section est corrigée.

### 4.1 Liens

| HTML produit par SPIP | Sortie |
|---|---|
| `a.spip_in` dont le résolveur trouve l'objet | Raccourci d'objet `123` (article), `rub5`, `br3`, `aut2`, `mot4`, `site7`, `doc8`, avec `#ancre` gardée. Texte du lien égal à `titreAffiche()` : `[->123]`, sinon `[texte->123]` |
| `title`, `hreflang` | `[texte\|bulle->123]`, `[texte\|bulle\|lang->123]` |
| Lien interne non résolu, objet absent ou non publié | Si l'URL contient type et id (`spip.php?article999`, `article999.html`, `?page=…&id_article=…`) : raccourci `[texte->999]` et avertissement. Sinon : `[texte->url]` et avertissement |
| `a.spip_out` | `[texte->url]` |
| `mailto:` dont le texte est l'adresse | `[->adresse]` |
| `a.spip_url.auto` (URL nue rendue en lien) | URL nue |
| `a.spip_glossaire` | `[?terme]`, ou `[texte->?terme]` si le texte diffère du terme ; forme exacte selon la capture |
| Ancre vide (`a` avec `id`/`name`, sans `href` ni texte) | `[nom<-]` |

### 4.2 Notes

Les appels `a.spip_note` (dans `span.spip_note_ref`) sont mis en correspondance avec les notes de `div.notes`, par l'`id` cible. L'appel et l'espace qui le précède sont remplacés par `[[contenu]]`, où le contenu de la note est converti récursivement (paragraphes joints par `_ `). Un numéro qui n'est pas celui qu'attribuerait SPIP (note numérotée à la main) donne `[[<x>contenu]]`. Une note sans appel reste en paragraphe à la fin du texte, avec un avertissement. Le bloc `div.notes` est ensuite retiré.

### 4.3 Structures

| HTML | Sortie |
|---|---|
| `h2.spip` | `{{{…}}}` |
| `strong`, `em` | `{{…}}`, `{…}` |
| `br.autobr` (si `autobr`) | retour simple |
| autre `br` | `_ ` |
| `hr.spip` | `----` |
| `ul.spip`, `ol.spip` | `-*`, `-#`, avec profondeur et listes mixtes (règles de wp2spip) |
| `table.spip` | `\|\|légende\|résumé\|\|` puis `\| … \|` ; en-têtes `{{…}}` ; cellules fusionnées selon la capture ; classes `row_odd`/`row_even` ignorées |
| `blockquote.spip` | `<quote>…</quote>` |
| `div.spip_poesie` | `<poesie>…</poesie>` |
| bloc de code SPIP (cadre) | `<cadre>…</cadre>`, texte brut |
| `code.spip_code` en ligne | `<code>…</code>`, texte brut |
| HTML d'un modèle du registre (`spip_document_N`, `spip_documents_left`…) | `<docN\|left>`, `<imgN>`, `<embN>`… |
| paragraphes `p` | séparés par une ligne vide |

### 4.4 Typographie

Règle : un caractère introduit par `typo()` n'est retiré que si `typo()` le remettra au rendu. Pour le français :

- `’` → `'` ;
- espace insécable (U+00A0, ou fine U+202F) devant `:` `;` `!` `?` et à l'intérieur de `« »` : retirée ;
- toute autre espace insécable : `~` ;
- `« »` restent des caractères UTF-8 : rendu identique à `<<`/`>>`, et le texte se lit mieux.

La liste complète des transformations de `ecrire/typographie/fr.php` (et des autres langues gérées par SPIP 4.4) est relevée dans le code au début de la réalisation et ajoutée en annexe A. Une langue sans table n'a aucune inversion.

### 4.5 Échappement

L'échappement de wp2spip est repris tel quel. Un texte qui ressemble à un raccourci SPIP est écrit en entités numériques (`{` `}` `[` `]` `|` `~`, `-` ou `_` en début de ligne), et `&` `<` `>` en entités. Le rendu est ainsi le même.

### 4.6 `<multi>`

Les N rendus sont analysés, puis alignés sur leur structure : même suite d'éléments, en comparant nom, classes et attributs autres que le texte. Là où l'alignement réussit, chaque nœud texte qui diffère d'une langue à l'autre devient `<multi>[fr]…[en]…</multi>`. Des paragraphes entiers qui diffèrent donnent un `<multi>` en bloc. Si les structures divergent (nombre de paragraphes différent, par exemple), l'ensemble du texte est mis dans un `<multi>` unique et un avertissement est émis.

### 4.7 Extraction d'une page publique

Pour le squelette dist, le contenu est lu dans les blocs `chapo`, `texte`, `ps` et `notes` de la page d'article. Les autres squelettes s'ajoutent par un registre de sélecteurs. Si aucun bloc n'est trouvé, un avertissement est émis et le `body` entier est converti.

### 4.8 Limites

Ne sont pas retrouvés : le contenu de `<html>…</html>` et de `<echappe>`, qui reste en HTML ; la forme exacte parmi des variantes équivalentes (`[->art123]` ou `[->123]`, `<<` ou `«`) ; les modèles non déclarés, qui restent en HTML ; les traitements de squelette autres que `propre()`.

## 5. Forme canonique

- Liens d'objet : `[->123]` si le texte est le titre affiché, sinon `[texte->123]` ; raccourcis courts (`123`, `rub5`…).
- Typographie : apostrophes droites, `« »` en caractères, `~` seulement pour les insécables voulues.
- Paragraphes séparés par une ligne vide, aucun blanc en fin de ligne, aucune ligne vide en tête ni en fin.
- Notes à leur place d'appel.

## 6. Tests

### 6.1 SPIP jetable

`scripts/installer-spip-jetable.sh` est idempotent, sur le modèle de `scripts/install-spip-test.sh` de wp2spip :

1. SPIP-Cli autonome dans `vendor/`, puis `core:telecharger spip -R 4.4`, qui donne la dernière 4.4.x. La version est notée dans `tests/spip-jetable/VERSION` et affichée par les tests ;
2. installation en SQLite dans `tests/spip-jetable/` (ignoré par git), administrateur avec un mot de passe aléatoire ;
3. plugin html2spip lié depuis `spip/`, puis activé ;
4. configuration : site multilingue (fr, en), `autobr` actif ;
5. chargement du corpus (§ 6.2) avec des **id fixes**, insérés explicitement, pour que les liens du corpus restent stables ;
6. `--reinitialiser` : suppression complète et réinstallation.

### 6.2 Corpus

`tests/corpus/sources/` contient un fichier par objet : un en-tête (type, id, titre, rubrique, langue, statut) suivi du texte SPIP. On y trouve aussi des documents versionnés (petites images, un PDF). Cas couverts :

- liens vers article, rubrique, brève, auteur, mot, site et document ; avec et sans texte ; texte égal ou non au titre ; titres numérotés (`10. Titre`) ; titres contenant `'` ou `:` ; bulle, langue, `#ancre` ;
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
- `<multi>` en ligne et en blocs ;
- typographie : `'`, `« »`, `<<>>`, `: ; ! ?`, `~` voulus ;
- textes qui ressemblent à des raccourcis ;
- `_ ` et retours simples.

### 6.3 Captures

`tests/corpus/captures/<type_url>/` est produit par `spip php:eval` pour chaque article. Contenu de chaque capture :
- `propre()` du texte (et du chapo, du ps) avec `#NOTES` ;
- pour les articles contenant `<multi>`, un rendu par langue ;
- la page publique dist (`recuperer_fond`).

Les types d'URL couverts sont `page`, `html`, `propres` et `arbo`. Les captures sont **versionnées**, ce qui permet aux tests unitaires de tourner sans SPIP.

### 6.4 Suites

- **Unitaires** (sans SPIP) : une suite par passe, par l'écriture et par Multi, sur des extraits de captures, avec `ResolveurTableau`. On y ajoute les tests du convertisseur de wp2spip, repris pour l'étape 2.
- **Intégration** (SPIP jetable), pour chaque article et chaque type d'URL :
  - rendu identique : `propre(html2spip(capture)) == propre(source)`, notes comprises ;
  - forme canonique : sortie égale à `tests/corpus/attendus/<objet>.spip` ;
  - captures régénérées égales aux captures versionnées ; un écart signale un changement de SPIP 4.4.x, à revoir avant de mettre à jour les captures ;
  - `<multi>` : la fusion des rendus fr et en restitue les `<multi>` de la source ;
  - page publique : l'extraction donne la même sortie que la capture `propre()`.
- `composer test` lance les tests unitaires. `composer test-integration` installe le SPIP jetable si besoin, puis lance tout.

## 7. Sous-projets

1. **html2spip** (ce document) : cœur, plugin, SPIP jetable, corpus, tests.
2. **Bascule de wp2spip** : wp2spip dépend de html2spip par Composer, passes de reconnaissance SPIP désactivées, options `autop` et marqueurs. Ses tests unitaires, d'intégration et `composer tests-import` servent de non-régression. `inc/wp2spip_html.php` est retiré.

## Annexe A — Typographie de SPIP 4.4

À relever dans `ecrire/typographie/*.php` au début de la réalisation : chaque transformation, la règle d'inversion retenue et le test qui la couvre.
