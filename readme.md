# Theme Export - JLG

## Description
Theme Export - JLG est un plugin WordPress pour administrateurs de sites blocs qui réunit, dans un même écran, l’export du thème actif, la sauvegarde sélective des compositions personnalisées et des outils pour préparer une migration ou un environnement de test. Les actions sont réparties dans quatre onglets (Exporter & Outils, Importer, Guide de migration et Débogage) afin de couvrir l’ensemble du cycle de vie d’un thème bloc.【F:theme-export-jlg/theme-export-jlg.php†L3-L38】【F:theme-export-jlg/includes/class-tejlg-admin.php†L67-L138】

## Prérequis
- Disposer d’un compte administrateur (capacité `manage_options`) : toutes les actions critiques sont protégées par cette vérification.【F:theme-export-jlg/includes/class-tejlg-admin.php†L22-L64】
- Utiliser un site WordPress reposant sur l’éditeur de blocs et les compositions (`wp_block`), que le plugin parcourt pour les exports et les imports sélectifs.【F:theme-export-jlg/includes/class-tejlg-admin.php†L141-L177】【F:theme-export-jlg/includes/class-tejlg-import.php†L41-L70】
- Activer l’extension PHP **ZipArchive** pour générer les archives du thème et vérifier sa disponibilité dans l’onglet Débogage.【F:theme-export-jlg/includes/class-tejlg-export.php†L7-L37】【F:theme-export-jlg/includes/class-tejlg-admin.php†L214-L236】
- Activer l’extension PHP **mbstring** pour garantir l’encodage UTF‑8 des compositions exportées et suivre l’avertissement fourni dans l’onglet Débogage.【F:theme-export-jlg/includes/class-tejlg-export.php†L105-L125】【F:theme-export-jlg/includes/class-tejlg-admin.php†L227-L233】
- Autoriser le serveur à écrire dans `wp-content/themes/` afin de générer automatiquement un thème enfant.【F:theme-export-jlg/includes/class-tejlg-theme-tools.php†L16-L79】

## Installation & activation
1. Téléversez le dossier `theme-export-jlg` (qui contient le fichier principal `theme-export-jlg.php`) dans `wp-content/plugins/`, ou installez l’archive ZIP du plugin via l’interface d’administration de WordPress.【F:theme-export-jlg/theme-export-jlg.php†L3-L38】
2. Activez l’extension **Theme Export - JLG** depuis le menu **Extensions** de WordPress.【F:theme-export-jlg/theme-export-jlg.php†L3-L13】
3. Accédez à la nouvelle entrée de menu **Theme Export** dans la barre latérale d’administration pour lancer les assistants d’export, d’import et de migration.【F:theme-export-jlg/includes/class-tejlg-admin.php†L10-L138】

## Fonctionnalités principales
- **Exporter le thème actif au format ZIP** pour cloner un environnement ou préparer un audit. Le plugin construit l’archive à partir des fichiers du thème actif et la propose en téléchargement direct.【F:theme-export-jlg/includes/class-tejlg-admin.php†L102-L111】【F:theme-export-jlg/includes/class-tejlg-export.php†L7-L37】
- **Sauvegarder les compositions personnalisées en JSON**, avec un mode « export portable » qui neutralise les IDs médias et les métadonnées pour limiter les dépendances à un site spécifique.【F:theme-export-jlg/includes/class-tejlg-admin.php†L112-L177】【F:theme-export-jlg/includes/class-tejlg-export.php†L43-L125】
- **Importer des thèmes et des compositions en deux étapes**, incluant une analyse du fichier JSON, un aperçu dans un iframe stylé avec le CSS global du thème actif et une sélection fine des blocs à créer sur le site de destination.【F:theme-export-jlg/includes/class-tejlg-admin.php†L189-L308】【F:theme-export-jlg/includes/class-tejlg-import.php†L4-L70】
- **Générer un thème enfant prêt à l’emploi** (fichiers `style.css` et `functions.php`) tout en effectuant les contrôles de sécurité nécessaires (droits d’écriture, unicité du dossier, prévention du cas « enfant d’un enfant »).【F:theme-export-jlg/includes/class-tejlg-admin.php†L118-L135】【F:theme-export-jlg/includes/class-tejlg-theme-tools.php†L4-L79】
- **Suivre un guide pas-à-pas de migration** entre deux thèmes blocs, incluant des rappels de bonnes pratiques et des étapes pour réappliquer ses personnalisations.【F:theme-export-jlg/includes/class-tejlg-admin.php†L312-L352】
- **Diagnostiquer son environnement** via un onglet Débogage qui liste les versions de WordPress/PHP, la présence des extensions critiques, la mémoire disponible et les compositions déjà enregistrées.【F:theme-export-jlg/includes/class-tejlg-admin.php†L214-L257】
- **Améliorer l’ergonomie** grâce à des scripts dédiés : sélection/désélection en masse, accordéons de débogage, confirmation de remplacement pour l’import de thèmes et bascule d’affichage du code des compositions.【F:theme-export-jlg/assets/js/admin-scripts.js†L1-L69】
- **Nettoyer les données temporaires** créées pendant les imports (transients) lors de la désinstallation du plugin.【F:theme-export-jlg/uninstall.php†L1-L35】

## Utilisation en ligne de commande (WP-CLI)

Le plugin enregistre la commande `wp theme-export-jlg` dès que WP-CLI est disponible.【F:theme-export-jlg/includes/class-tejlg-cli.php†L7-L168】 Elle propose deux sous-commandes :

- `wp theme-export-jlg theme [--exclusions=<motifs>] [--output=<chemin>]` exporte le thème actif au format ZIP. Utilisez l’option `--exclusions` pour ignorer des fichiers ou dossiers (séparateur virgule ou retour à la ligne) et `--output` pour définir le chemin du fichier généré (par défaut dans le dossier courant, avec le slug du thème).【F:theme-export-jlg/includes/class-tejlg-cli.php†L17-L98】
- `wp theme-export-jlg patterns [--portable] [--output=<chemin>]` crée un export JSON des compositions (`wp_block`). L’option `--portable` active le nettoyage portable déjà proposé dans l’interface graphique et `--output` contrôle l’emplacement du fichier généré.【F:theme-export-jlg/includes/class-tejlg-cli.php†L100-L160】

Chaque commande renvoie un objet JSON contenant le statut (`success`/`error`), le message et les métadonnées utiles (chemin du fichier, taille, drapeau `portable`, etc.), ce qui facilite l’analyse dans un script d’automatisation ou un pipeline CI.【F:theme-export-jlg/includes/class-tejlg-cli.php†L60-L133】【F:theme-export-jlg/includes/class-tejlg-cli.php†L205-L307】

## Support & ressources

### FAQ
**Pourquoi l’export du thème échoue-t-il immédiatement ?**  
L’extension vérifie la présence de `ZipArchive` avant de créer l’archive ZIP. Activez ou installez l’extension côté serveur, puis contrôlez son statut dans l’onglet Débogage.【F:theme-export-jlg/includes/class-tejlg-export.php†L7-L37】【F:theme-export-jlg/includes/class-tejlg-admin.php†L214-L236】

**Puis-je réimporter un fichier de compositions sans créer de doublon ?**  
Oui. Lors de la seconde étape d’import, le plugin ignore les compositions dont le slug est déjà enregistré afin d’éviter les duplications inutiles.【F:theme-export-jlg/includes/class-tejlg-import.php†L41-L70】

**Que faire si la prévisualisation d’import affiche “session expirée” ?**  
Les fichiers analysés sont stockés temporairement dans un transient de 15 minutes. Relancez l’upload si le message apparaît ou poursuivez immédiatement l’étape d’import pour éviter l’expiration.【F:theme-export-jlg/includes/class-tejlg-import.php†L18-L39】【F:theme-export-jlg/includes/class-tejlg-admin.php†L261-L307】

### Journal des modifications
- **3.0** – Ajout du guide de migration, des aperçus interactifs pour l’import des compositions et des outils de diagnostic en onglet Débogage.【F:theme-export-jlg/includes/class-tejlg-admin.php†L214-L352】
- **2.2.0** – Amélioration des exports JSON grâce au mode « portable » (nettoyage des URLs, IDs médias et métadonnées) et contrôle de l’encodage UTF‑8.【F:theme-export-jlg/theme-export-jlg.php†L20-L29】【F:theme-export-jlg/includes/class-tejlg-export.php†L43-L125】
- **2.0** – Première version complète : export du thème, import/export des compositions et générateur de thème enfant accessibles via le tableau de bord dédié.【F:theme-export-jlg/includes/class-tejlg-admin.php†L102-L207】【F:theme-export-jlg/includes/class-tejlg-theme-tools.php†L4-L79】

### Liens utiles
- [Documentation officielle de l’Éditeur de blocs (WordPress.org)](https://wordpress.org/documentation/article/site-editor/) – Pour comprendre la logique des thèmes blocs et des compositions.
- [Guide des thèmes enfants (WordPress.org)](https://developer.wordpress.org/themes/advanced-topics/child-themes/) – Pour approfondir les personnalisations apportées par le générateur de thème enfant.

Pour toute autre question, consultez le **Guide de migration** intégré ou utilisez l’onglet **Débogage** pour réunir les informations nécessaires avant de contacter votre hébergeur ou votre agence WordPress.【F:theme-export-jlg/includes/class-tejlg-admin.php†L214-L352】

## Tests

Préparez l’environnement de test WordPress (par exemple avec [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)) puis installez les dépendances JavaScript :

```
npm install
npx playwright install
```

Ensuite, lancez votre instance WordPress locale (par exemple `npx wp-env start`) avant d’exécuter la commande qui enchaîne les tests unitaires PHP existants et le test d’interface Playwright qui vérifie le filtrage et la case « Tout sélectionner » de l’écran d’export sélectif :

```
npm test
```

Pour exécuter uniquement le test d’interface, utilisez :

```
npm run test:e2e
```
