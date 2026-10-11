# html2spip

Conversion d'un fragment HTML en raccourcis SPIP : un `sale()` amélioré. Le HTML est analysé en arbre HTML5, et non
par expressions régulières. Le HTML produit par SPIP 4.4 est reconnu : liens internes (`[->123]`), notes, ancres,
tableaux, `<cadre>`, `<code>`, `<poesie>`, modèles `doc`, `img`, `emb`, typographie française et anglaise.

## Bibliothèque

PHP 8.1 ou plus, extension DOM. `Dom\HTMLDocument` est utilisé à partir de PHP 8.4, Masterminds HTML5-PHP avant.

    composer require technova69/html2spip

    use Html2Spip\Convertisseur;
    use Html2Spip\Options;
    use Html2Spip\ResolveurTableau;

    $texte = (new Convertisseur(new Options(langue: 'fr')))->convertir($html);

Hors SPIP, un lien interne n'est retrouvé que si son URL contient le type et l'identifiant de l'objet
(`spip.php?article12`). `ResolveurTableau` permet de fournir les URL et les titres.

## Plugin SPIP

Le dépôt est aussi un plugin SPIP 4.4. Il fournit `html2spip($html, $langue)` (`include_spip('inc/html2spip')`), qui
résout les liens par les URL et la base du site, et la commande `spip html2spip:convertir` (HTML sur l'entrée
standard ; `-v` pour les avertissements).

## Limites

- `<multi>` : seul le texte de la langue rendue est gardé.
- Notes : recousues seulement si l'appel et la note sont dans le fragment.
- `<html>…</html>` : converti comme du HTML quelconque.
- Modèles : seule la forme HTML des modèles `doc`, `img`, `emb` est reconnue, avec leur alignement. Les autres
  modèles sont convertis comme du HTML quelconque ; un modèle qui imite cette forme devient `<docN>`.
- Une forme canonique est choisie parmi des variantes équivalentes (`[->123]`, `a :`, `—`).

## Tests

    composer test                  # unitaires, sans SPIP
    composer test-integration      # installe un SPIP 4.4 jetable (SQLite), puis tout le corpus

Spec : `docs/superpowers/specs/2026-10-11-html2spip-design.md`.
