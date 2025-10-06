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
- **Piloter des exports de thème robustes** : une file d’attente asynchrone lance la copie du thème actif, affiche la progression en temps réel, permet d’annuler la tâche et sécurise le téléchargement final. Un testeur de motifs d’exclusion vérifie les fichiers ignorés avant départ et les exports peuvent être planifiés (de l’horaire quotidien à l’hebdomadaire) avec conservation automatique et purge des archives historiques.【F:theme-export-jlg/templates/admin/export.php†L29-L156】【F:theme-export-jlg/assets/js/admin-scripts.js†L140-L356】【F:theme-export-jlg/includes/class-tejlg-export.php†L7-L138】【F:theme-export-jlg/includes/class-tejlg-export.php†L1601-L1756】【F:theme-export-jlg/includes/class-tejlg-export.php†L1301-L1355】【F:theme-export-jlg/includes/class-tejlg-export-history.php†L6-L78】
- **Exporter les compositions sur mesure** grâce à un sélecteur paginé qui affiche titres, métadonnées et aperçus interactifs en iframe. Recherche, filtres par catégories/périodes, tri personnalisé et compteur d’accessibilité facilitent la sélection avant de générer un fichier JSON, y compris en mode « portable » pour neutraliser les références spécifiques au site.【F:theme-export-jlg/templates/admin/export-pattern-selection.php†L17-L159】【F:theme-export-jlg/assets/js/admin-scripts.js†L3000-L3223】【F:theme-export-jlg/includes/class-tejlg-export.php†L1960-L2099】
- **Importer en toute sécurité** : les zones de dépôt gèrent glisser-déposer et clavier, l’étape 1 valide les fichiers (thème, compositions, styles globaux) et l’étape 2 propose une interface de tri/recherche avec réglage de largeur d’aperçu, compteur de sélections et affichage optionnel du code.【F:theme-export-jlg/templates/admin/import.php†L15-L68】【F:theme-export-jlg/assets/js/admin-scripts.js†L20-L138】【F:theme-export-jlg/templates/admin/import-preview.php†L30-L220】 Les slugs sont recalculés pour éviter les doublons et les métadonnées, catégories et styles globaux sont restaurés proprement.【F:theme-export-jlg/includes/class-tejlg-import.php†L700-L939】【F:theme-export-jlg/includes/class-tejlg-import.php†L1382-L1448】
- **Générer un thème enfant prêt à l’emploi** (fichiers `style.css` et `functions.php`) tout en effectuant les contrôles de sécurité nécessaires (droits d’écriture, unicité du dossier, prévention du cas « enfant d’un enfant »).【F:theme-export-jlg/includes/class-tejlg-admin.php†L118-L135】【F:theme-export-jlg/includes/class-tejlg-theme-tools.php†L4-L79】
- **Suivre un guide pas-à-pas de migration** entre deux thèmes blocs, incluant des rappels de bonnes pratiques et des étapes pour réappliquer ses personnalisations.【F:theme-export-jlg/includes/class-tejlg-admin.php†L312-L352】
- **Diagnostiquer son environnement** via un onglet Débogage qui liste les versions de WordPress/PHP, la présence des extensions critiques, la mémoire disponible et les compositions déjà enregistrées. Un bouton « Télécharger le rapport » exporte ces informations dans un fichier JSON compressé pour un partage rapide avec un support technique.【F:theme-export-jlg/includes/class-tejlg-admin.php†L214-L257】【F:theme-export-jlg/includes/class-tejlg-admin-debug-page.php†L18-L210】【F:theme-export-jlg/templates/admin/debug.php†L1-L40】
- **Améliorer l’ergonomie** grâce aux scripts dédiés qui motorisent les dropzones accessibles, la sélection/désélection en masse, le filtrage instantané, les métriques de performance du navigateur et la bascule d’affichage du code des compositions.【F:theme-export-jlg/assets/js/admin-scripts.js†L20-L138】【F:theme-export-jlg/assets/js/admin-scripts.js†L3000-L3320】
- **Nettoyer les données temporaires** créées pendant les imports (transients) lors de la désinstallation du plugin.【F:theme-export-jlg/uninstall.php†L1-L35】

## Cohérence visuelle dans l’administration

- Les vues d’export, d’import et de débogage s’appuient désormais sur les composants de l’interface WordPress (`.components-card`, classes `wp-ui-*`) et sur les variables CSS de l’admin (`--wp-admin-theme-color`, `--wp-components-color-*`). Toute évolution doit conserver ces classes afin de rester alignée avec les palettes officielles et le mode sombre.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L214】【F:theme-export-jlg/templates/admin/export.php†L18-L118】【F:theme-export-jlg/templates/admin/import.php†L13-L71】【F:theme-export-jlg/templates/admin/debug.php†L9-L118】
- Lorsqu’un nouveau bloc d’interface est ajouté, réutilisez les cartes existantes (`tejlg-card components-card is-elevated`) plutôt que de créer un style personnalisé. Les boutons doivent combiner les classes historiques (`button button-primary|secondary`) et la variante `wp-ui-*` adaptée pour bénéficier de la coloration dynamique.【F:theme-export-jlg/templates/admin/export.php†L36-L113】【F:theme-export-jlg/templates/admin/import.php†L22-L63】【F:theme-export-jlg/templates/admin/debug.php†L12-L74】
- Testez systématiquement les écrans dans les différents schémas de couleurs de l’administration (préférences utilisateur) **et** dans l’éditeur de site en modes clair et sombre afin de valider les contrastes. En local, utilisez la commande `wp-admin/options-general.php?page=global-settings` ou la palette rapide (`Options → Administration color scheme`).
- Pensez à vérifier le rendu des cartes dans l’éditeur du site (`/wp-admin/site-editor.php`) où les styles admin sont partagés. Les variables CSS adoptées ici garantissent un contraste suffisant quelles que soient les combinaisons activées.

## Utilisation en ligne de commande (WP-CLI)

Le plugin enregistre la commande `wp theme-export-jlg` dès que WP-CLI est disponible.【F:theme-export-jlg/includes/class-tejlg-cli.php†L7-L168】 Elle propose deux sous-commandes :

- `wp theme-export-jlg theme [--exclusions=<motifs>] [--output=<chemin>]` exporte le thème actif au format ZIP. Utilisez l’option `--exclusions` pour ignorer des fichiers ou dossiers (séparateur virgule ou retour à la ligne) et `--output` pour définir le chemin du fichier généré (par défaut dans le dossier courant, avec le slug du thème).【F:theme-export-jlg/includes/class-tejlg-cli.php†L17-L98】
- `wp theme-export-jlg patterns [--portable] [--output=<chemin>]` crée un export JSON des compositions (`wp_block`). L’option `--portable` active le nettoyage portable déjà proposé dans l’interface graphique et `--output` contrôle l’emplacement du fichier généré.【F:theme-export-jlg/includes/class-tejlg-cli.php†L100-L160】

Chaque commande renvoie un message de réussite structuré ou un message d’erreur explicite en cas de problème (dossier non accessible, erreur `wp_die`, etc.), pour s’intégrer facilement dans des scripts d’automatisation.【F:theme-export-jlg/includes/class-tejlg-cli.php†L40-L160】

## Support & ressources

### FAQ
**Pourquoi l’export du thème échoue-t-il immédiatement ?**
L’extension vérifie la présence de `ZipArchive` avant de créer l’archive ZIP. Activez ou installez l’extension côté serveur, puis contrôlez son statut dans l’onglet Débogage.【F:theme-export-jlg/includes/class-tejlg-export.php†L7-L37】【F:theme-export-jlg/includes/class-tejlg-admin.php†L214-L236】

**Et si WP-Cron est désactivé par mon hébergeur ?**
Le plugin détecte automatiquement l’absence d’évènements planifiés ou la constante `DISABLE_WP_CRON` pour traiter immédiatement l’export, sans dépendre de WP-Cron. Vous obtenez ainsi le même comportement que sur un environnement professionnel où un cron serveur prendrait le relais.【F:theme-export-jlg/includes/class-tejlg-export.php†L103-L133】

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

## Comparaison avec des solutions professionnelles

Les agences spécialisées s’appuient souvent sur des plateformes comme **ManageWP**, **BlogVault** ou **WP Migrate** pour orchestrer leurs exports, migrations et plans de secours WordPress. Ces outils misent sur une infrastructure SaaS qui centralise la gestion de dizaines de sites et automatise la maintenance (monitoring, sauvegardes incrémentales, restauration en un clic). Comparé à ces solutions, **Theme Export - JLG** se distingue par :

- **Une intégration native dans l’administration** qui évite les connexions externes et permet de travailler sans dépendance à un service cloud, un atout pour les organisations qui privilégient la maîtrise des données ou les environnements sans accès sortant strict.【F:theme-export-jlg/includes/class-tejlg-admin.php†L10-L138】
- **Des exports ciblés sur les thèmes blocs et les compositions** là où les plateformes professionnelles couvrent souvent un spectre plus large (base de données complète, médias, monitoring de disponibilité). Cette spécialisation offre un workflow adapté aux projets FSE tout en réduisant la surface d’outils à maîtriser.【F:theme-export-jlg/templates/admin/export.php†L29-L156】【F:theme-export-jlg/templates/admin/import.php†L15-L68】
- **Une transparence sur l’état de l’environnement** (extensions PHP critiques, mémoire disponible, historique des compositions) quand les services SaaS se limitent parfois à un statut global. Le panneau Débogage permet d’anticiper les points de friction avant une migration.【F:theme-export-jlg/templates/admin/debug.php†L1-L40】

En revanche, il reste des écarts avec les plateformes professionnelles :

- **Orchestration multi‑sites** : la plupart des services premium pilotent plusieurs environnements depuis un tableau de bord unique, ce que le plugin ne couvre pas aujourd’hui (chaque site doit être géré indépendamment).
- **Gestion fine des sauvegardes** : les outils SaaS proposent des exports incrémentaux, la rétention longue durée ou le stockage externalisé (S3, FTP, Google Drive) que le plugin ne gère pas encore.
- **Alertes et reporting** : les solutions professionnelles envoient des rapports planifiés, des alertes de sécurité ou de performances. Theme Export - JLG s’appuie surtout sur la consultation manuelle des onglets Export et Débogage.

### Analyse détaillée face à une application professionnelle

#### Options et périmètre fonctionnel

Theme Export - JLG couvre les exports de thèmes blocs, la sauvegarde sélective des compositions et une planification basique (fréquence, heure, motifs d’exclusion, durée de rétention).【F:theme-export-jlg/templates/admin/export.php†L37-L221】【F:theme-export-jlg/includes/class-tejlg-export.php†L11-L88】 À l’inverse, les suites professionnelles ajoutent généralement :

- des **snapshots complets** (base de données, médias, fichiers) avec restauration granulaire ;
- des **exports différentiels** et la conservation longue durée sur des stockages externes (S3, SFTP, Google Drive) ;
- des **profils d’environnements** permettant d’appliquer des réglages pré‑définis (dev/préprod/prod) à plusieurs sites en un clic ;
- une **gestion multi‑clients** (synchronisation de configurations, duplication des filtres d’exclusion, etc.).

Pour se rapprocher de ces usages, le plugin pourrait intégrer un connecteur vers des stockages distants, exposer une API/CLI pour automatiser la duplication des réglages, proposer une option d’export étendu (fichiers médias, extraits de la base) et stocker des métadonnées détaillées (taille, durée, auteur) dans l’historique afin d’alimenter un reporting comparable à celui de BlogVault ou ManageWP.【F:theme-export-jlg/includes/class-tejlg-export.php†L328-L447】【F:theme-export-jlg/includes/class-tejlg-export-history.php†L6-L78】

#### UX/UI et guidage

Les vues administratives réutilisent les composants WordPress (`components-card`, boutons `wp-ui-*`) et des variables CSS communes pour garder la cohérence avec le tableau de bord.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L41】【F:theme-export-jlg/templates/admin/export.php†L37-L156】 Les cartes présentent des explications contextualisées et la plupart des actions critiques sont accompagnées d’un retour d’état (`notice`, `aria-live`).【F:theme-export-jlg/templates/admin/export.php†L57-L132】【F:theme-export-jlg/templates/admin/import-preview.php†L30-L220】 Pour rivaliser avec un outil pro, plusieurs améliorations UX peuvent être envisagées :

- ajouter un **assistant pas-à-pas** pour les tâches longues (planification, export complet + import) avec résumé final et liens directs vers la documentation ;
- intégrer des **infobulles et états vides illustrés** pour mieux guider les utilisateurs novices sur chaque carte ;
- proposer un **mode compact** ou des regroupements de sections pour éviter le défilement sur les écrans denses, en particulier lorsque l’historique contient de nombreuses entrées.

#### Navigation mobile et petits écrans

Le CSS adapte déjà les grilles de cartes et la barre d’onglets en dessous de 782 px (colonne unique, onglets défilables horizontalement).【F:theme-export-jlg/assets/css/admin-styles.css†L15-L33】 Cependant, l’expérience mobile reste en retrait face aux tableaux de bord responsive des solutions SaaS. Les pistes suivantes réduiraient l’écart :

- transformer les onglets secondaires en **navigation verticale ou accordéons** sur mobile afin d’éviter la friction du scroll horizontal ;
- ajouter un **récapitulatif flottant** (bouton d’action principal ou carte « Actions rapides ») toujours visible pendant les listes longues ;
- optimiser les tableaux (`form-table`, historique) pour qu’ils basculent en cartes empilées ou listes à colonnes réduites lorsque l’espace horizontal manque.

#### Accessibilité

Les formulaires s’appuient sur des libellés `<label>` explicites, des zones `aria-live` pour annoncer la progression et des contrôles clavier pour les dropzones (input masqué, gestion des états `focus-visible`).【F:theme-export-jlg/templates/admin/export.php†L49-L132】【F:theme-export-jlg/assets/css/admin-styles.css†L37-L83】 Pour se rapprocher des standards AA/AAA ciblés par les produits pro, il reste à :

- enrichir les **messages d’erreur** avec des suggestions d’actions et des liens d’ancrage pour repositionner le focus en cas d’échec ;
- ajouter des **tests automatiques de contraste** et des classes `has-high-contrast` pour les utilisateurs qui désactivent les styles natifs ;
- proposer des **raccourcis clavier** (ex. : `Shift+S` pour sélectionner toutes les compositions) et des résumés vocalisables des opérations, utiles aux opérateurs qui traitent de gros volumes.

#### Apparence dans WordPress et éditeur visuel

Le thème administratif du plugin puise dans les variables WP (`--wp-admin-theme-color`, `--wp-components-color-*`) pour rester aligné sur les schémas clair/sombre du back-office et du site editor.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L41】 Pour offrir un rendu digne d’un outil pro, deux axes sont à considérer :

- garantir la **parité visuelle dans l’éditeur de site** (`/wp-admin/site-editor.php`) en isolant les styles du plugin via des namespaces (`.edit-site-page .tejlg-card`), afin d’éviter les collisions avec les styles du thème actif ;
- préparer des **variantes de présentation** (compacte, haute densité, mode distraction‑free) activables via un toggle, ce que les solutions professionnelles exploitent pour travailler confortablement depuis Gutenberg ou un constructeur tiers.

## Pistes d’amélioration inspirées des apps pro

Pour combler ces écarts tout en conservant l’esprit « in‑dashboard », plusieurs évolutions peuvent être envisagées :

1. **Historique enrichi et notifications** : étendre la classe d’exports pour conserver des métadonnées (taille, durée, auteur) et déclencher un e‑mail de confirmation ou d’alerte en cas d’échec. On pourrait réutiliser la file d’attente existante et stocker le journal dans une table personnalisée.
2. **Exports déportés** : ajouter un connecteur facultatif vers un stockage externe (S3, SFTP). La configuration resterait locale mais offrirait une redondance comparable aux offres cloud professionnelles.
3. **Profils d’environnements** : proposer des préréglages (développement, préproduction, production) qui sélectionnent automatiquement les compositions, exclusions et scripts d’après un profil, à la manière des « blueprints » de BlogVault.
4. **Automatisation multi‑sites légère** : permettre l’import/export d’un fichier de configuration du plugin (planning, exclusions, mapping des catégories) pour dupliquer rapidement la configuration sur plusieurs installations sans passer par un service externe.
5. **Contrôles de cohérence supplémentaires** : intégrer des vérifications avant import (versions minimales de thème parent, compatibilité de schéma `theme.json`) et afficher des recommandations similaires à celles de WP Migrate.

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
