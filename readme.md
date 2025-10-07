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

Le plugin enregistre la commande `wp theme-export-jlg` dès que WP-CLI est disponible.【F:theme-export-jlg/includes/class-tejlg-cli.php†L7-L205】 Elle propose plusieurs sous-commandes complémentaires :

- `wp theme-export-jlg theme [--exclusions=<motifs>] [--output=<chemin>]` exporte le thème actif au format ZIP. Utilisez l’option `--exclusions` pour ignorer des fichiers ou dossiers (séparateur virgule ou retour à la ligne) et `--output` pour définir le chemin du fichier généré (par défaut dans le dossier courant, avec le slug du thème).【F:theme-export-jlg/includes/class-tejlg-cli.php†L17-L101】
- `wp theme-export-jlg patterns [--portable] [--output=<chemin>]` crée un export JSON des compositions (`wp_block`). L’option `--portable` active le nettoyage portable déjà proposé dans l’interface graphique et `--output` contrôle l’emplacement du fichier généré.【F:theme-export-jlg/includes/class-tejlg-cli.php†L103-L223】
- `wp theme-export-jlg import theme <chemin_zip> [--overwrite]` et `wp theme-export-jlg import patterns <chemin_json>` réalisent les imports côté CLI, avec les mêmes validations MIME/tailles que l’interface graphique.【F:theme-export-jlg/includes/class-tejlg-cli.php†L135-L340】
- `wp theme-export-jlg settings export [--output=<chemin>]` génère un profil JSON signé contenant la planification, les exclusions et les préférences d’interface. Le message de succès affiche l’empreinte SHA-256 pour vérifier rapidement l’intégrité du fichier.【F:theme-export-jlg/includes/class-tejlg-cli.php†L207-L276】
- `wp theme-export-jlg settings import <chemin_json>` recharge un profil existant. Si la signature ne correspond plus au contenu, la commande l’indique explicitement tout en appliquant les réglages (utile lorsqu’un fichier a été modifié manuellement).【F:theme-export-jlg/includes/class-tejlg-cli.php†L278-L336】

Les exports de réglages s’appuient sur `TEJLG_Settings` qui sérialise un instantané normalisé et génère automatiquement une signature horodatée.【F:theme-export-jlg/includes/class-tejlg-settings.php†L7-L229】 Des filtres (`tejlg_settings_export_snapshot`, `tejlg_settings_export_package`, `tejlg_settings_import_snapshot`) permettent d’étendre le schéma ou d’injecter des métadonnées propres à votre environnement CI/CD.【F:theme-export-jlg/includes/class-tejlg-settings.php†L27-L224】 Un crochet dédié contrôle également la taille maximale autorisée lors des imports (`tejlg_settings_import_max_filesize`).【F:theme-export-jlg/includes/class-tejlg-cli.php†L320-L333】

Chaque commande renvoie un message de réussite structuré ou un message d’erreur explicite en cas de problème (dossier non accessible, erreur `wp_die`, signature altérée…), ce qui facilite l’intégration dans les scripts d’automatisation.【F:theme-export-jlg/includes/class-tejlg-cli.php†L40-L334】

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

#### Synthèse comparative

| Axes | Theme Export - JLG | Suites professionnelles (ManageWP, BlogVault, WP Migrate…) | Opportunités d’évolution |
| --- | --- | --- | --- |
| **Périmètre fonctionnel** | Export ZIP du thème actif, sauvegarde sélective des compositions, planification basique, rétention définie dans l’interface.【F:theme-export-jlg/templates/admin/export.php†L37-L221】【F:theme-export-jlg/includes/class-tejlg-export.php†L11-L88】 | Snapshots complets (fichiers + base + médias), différentiel, orchestrations multi-environnements, stockage externalisé et duplication de profils. | Ajouter un mode d’export étendu (médiathèque, tables ciblées), un connecteur distant (S3/SFTP) et un format de configuration partageable pour répliquer les réglages.【F:theme-export-jlg/includes/class-tejlg-export.php†L328-L447】【F:theme-export-jlg/includes/class-tejlg-export-history.php†L6-L78】 |
| **Expérience utilisateur** | UI cohérente avec l’admin WP (`components-card`, `wp-ui-*`), feedbacks dynamiques (`aria-live`).【F:theme-export-jlg/assets/css/admin-styles.css†L1-L41】【F:theme-export-jlg/templates/admin/export.php†L57-L132】 | Assistants guidés, infobulles contextuelles, dashboards condensés avec vues compactes et rapports exportables. | Intégrer un wizard multi-étapes, des infobulles illustrées, un mode compact et un export PDF/CSV de synthèse. |
| **Accessibilité & supervision** | Dropzones accessibles, focus visibles, onglet Débogage pour vérifications manuelles.【F:theme-export-jlg/templates/admin/import.php†L15-L68】【F:theme-export-jlg/templates/admin/debug.php†L1-L40】 | Audit automatique (contraste, performances), alertes e-mail/webhook, raccourcis clavier personnalisés. | Ajouter des raccourcis dédiés, des tests automatiques de contraste et des notifications configurables (e-mail, Slack, webhook). |
| **Mobilité & multi-sites** | Mise en page responsive (grilles uniques <782 px) mais navigation horizontale sur mobile, gestion site par site.【F:theme-export-jlg/assets/css/admin-styles.css†L15-L33】 | Tableaux réactifs, navigation verticale adaptée aux terminaux tactiles, pilotage centralisé multi-sites. | Transformer les onglets en accordéons sur mobile, proposer un panneau d’actions rapides et un export/import de configuration pour plusieurs sites. |

#### Zoom sur les axes d’amélioration

1. **Fonctions avancées d’export**  : tirer parti de la file d’attente existante pour enregistrer des métadonnées détaillées (taille de l’archive, durée, auteur, horodatage) et exposer un reporting comparable aux services cloud.【F:theme-export-jlg/includes/class-tejlg-export.php†L328-L447】【F:theme-export-jlg/includes/class-tejlg-export-history.php†L6-L78】 Ces informations pourraient alimenter des alertes (succès/échec) envoyées par e-mail ou webhook.
2. **Automatisation & mutualisation**  : compléter l’API WP-CLI actuelle avec une sous-commande d’export/import de configuration (`wp theme-export-jlg settings`) afin de répliquer rapidement les profils d’exclusion ou de planification sur un parc de sites.【F:theme-export-jlg/includes/class-tejlg-cli.php†L17-L160】 Un format JSON signé garantirait l’intégrité et faciliterait l’orchestration via GitOps.
3. **Expérience guidée**  : créer un assistant multi-écrans pour l’export complet (choix du périmètre, exclusions, confirmation) et pour l’import (validation, prévisualisation, application). Chaque étape afficherait des conseils contextualisés et un résumé final téléchargeable en PDF/CSV afin d’aligner la solution sur les checklists proposées par les outils pro.【F:theme-export-jlg/templates/admin/export.php†L37-L221】【F:theme-export-jlg/templates/admin/import-preview.php†L30-L220】
4. **Mode compact et accessibilité renforcée**  : offrir une bascule « Vue compacte » qui réduit les marges, regroupe les cartes et ajoute des ancres de navigation clavier. Compléter les notices d’erreur avec des liens d’action et intégrer un test automatique de contraste pour les combinaisons de couleurs personnalisées.【F:theme-export-jlg/assets/css/admin-styles.css†L37-L83】
5. **Optimisation mobile**  : convertir les onglets secondaires en accordéons verticaux sous 600 px, ajouter un bouton flottant « Actions rapides » (export immédiat, téléchargement du dernier ZIP) et transformer l’historique en listes empilées pour limiter le défilement horizontal sur tablette/smartphone.【F:theme-export-jlg/assets/css/admin-styles.css†L15-L33】【F:theme-export-jlg/templates/admin/export.php†L141-L199】


#### Plan d’implémentation priorisé

Les axes précédents peuvent être regroupés en lots successifs pour sécuriser l’exécution et faciliter le suivi des dépendances. Le détail du backlog est consigné dans `docs/roadmap.md`.

| Priorité | Lot | Objectif | Actions clés |
| --- | --- | --- | --- |
| 1 | Journal d’export enrichi | Étendre la persistance des exports pour stocker durée, taille, auteur et surface des exclusions afin d’alimenter des notifications et des tableaux de bord détaillés.【F:theme-export-jlg/includes/class-tejlg-export-history.php†L6-L195】【F:theme-export-jlg/includes/class-tejlg-export.php†L300-L378】 | Ajouter des colonnes calculées lors de `record_job`, exposer ces métadonnées dans l’écran d’historique et préparer un hook de notification (e-mail/webhook). |
| 2 | Orchestration & profils | Offrir une duplication rapide de la configuration via WP-CLI et préparer la synchronisation multi-sites légère.【F:theme-export-jlg/includes/class-tejlg-cli.php†L16-L195】 | Implémenter `wp theme-export-jlg settings` (export/import JSON signé), documenter les options et exposer des filtres pour enrichir le schéma. |
| 3 | Assistants guidés | Transformer les formulaires Export/Import en parcours multi-étapes avec aides contextuelles pour aligner l’expérience sur les outils professionnels.【F:theme-export-jlg/templates/admin/export.php†L130-L220】【F:theme-export-jlg/templates/admin/import-preview.php†L30-L200】 | Décomposer les étapes dans des composants réutilisables, injecter des conseils dynamiques et générer un résumé téléchargeable (PDF/JSON) en fin de processus. |
| 4 | Vue compacte & mobile | Optimiser la grille responsive et introduire une bascule compacte pour accélérer les workflows sur petits écrans.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L160】 | Ajouter un `ToggleControl` mémorisé par utilisateur, convertir les onglets en accordéons <600 px et préparer le bouton d’actions flottant partagé entre les onglets. |
## Pistes d’amélioration inspirées des apps pro

Pour combler ces écarts tout en conservant l’esprit « in‑dashboard », plusieurs évolutions peuvent être envisagées :

1. **Historique enrichi et notifications** : étendre la classe d’exports pour conserver des métadonnées (taille, durée, auteur) et déclencher un e‑mail de confirmation ou d’alerte en cas d’échec. On pourrait réutiliser la file d’attente existante et stocker le journal dans une table personnalisée.
2. **Exports déportés** : ajouter un connecteur facultatif vers un stockage externe (S3, SFTP). La configuration resterait locale mais offrirait une redondance comparable aux offres cloud professionnelles.
3. **Profils d’environnements** : proposer des préréglages (développement, préproduction, production) qui sélectionnent automatiquement les compositions, exclusions et scripts d’après un profil, à la manière des « blueprints » de BlogVault.
4. **Automatisation multi‑sites légère** : permettre l’import/export d’un fichier de configuration du plugin (planning, exclusions, mapping des catégories) pour dupliquer rapidement la configuration sur plusieurs installations sans passer par un service externe.
5. **Contrôles de cohérence supplémentaires** : intégrer des vérifications avant import (versions minimales de thème parent, compatibilité de schéma `theme.json`) et afficher des recommandations similaires à celles de WP Migrate.

### Améliorations structurantes supplémentaires

- **Capacités dédiées et filtres d’autorisation** : le plugin expose désormais des capacités spécifiques (`tejlg_manage_plugin`, `tejlg_manage_exports`, `tejlg_manage_imports`, `tejlg_manage_debug`, `tejlg_manage_settings`) mappées par défaut sur `manage_options`. Des filtres (`tejlg_required_capability_*`, `tejlg_capability_fallbacks`) permettent d’ajuster finement chaque section sans forker le code, tout en conservant la compatibilité des rôles existants.【F:theme-export-jlg/includes/class-tejlg-capabilities.php†L1-L99】【F:theme-export-jlg/includes/class-tejlg-admin.php†L36-L210】
- **Intégration aux rapports Site Health** : les métriques collectées dans l’onglet Débogage sont publiées dans le rapport Site Health et un test direct vérifie la présence des extensions PHP critiques (ZipArchive et mbstring). L’équipe support retrouve ainsi un instantané des versions, de WP-Cron et du nombre de compositions sans quitter la page Santé du site.【F:theme-export-jlg/includes/class-tejlg-site-health.php†L1-L143】【F:theme-export-jlg/includes/class-tejlg-admin-debug-page.php†L15-L210】
- **WP-CLI orienté orchestration** : la commande `wp theme-export-jlg` couvre l’export ponctuel, les imports et la consultation de l’historique mais ne pilote pas la planification. Ajouter des sous-commandes pour configurer les fréquences, lancer un export programmé à la demande ou envoyer un rapport renforcerait l’automatisation dans les pipelines CI/CD.【F:theme-export-jlg/includes/class-tejlg-cli.php†L16-L195】【F:theme-export-jlg/includes/class-tejlg-export.php†L7-L159】【F:theme-export-jlg/includes/class-tejlg-export.php†L328-L447】

### Optimisations UX / UI supplémentaires inspirées des suites professionnelles

1. **Tableau de bord « état de santé »** : regrouper les cartes du tableau de bord dans une vue synthétique qui affiche des badges de statut (succès, avertissement, échec) et des jauges de progression, comme le proposent ManageWP ou BlogVault. Les composants actuels (`tejlg-dashboard__card`) pourraient afficher des indicateurs secondaires (taux d’échec, délai moyen) et un bouton « Voir le rapport » qui ouvre un panneau latéral récapitulatif.【F:theme-export-jlg/templates/admin/export.php†L130-L195】
   - **Regroupement thématique** : trois sections (« Exports planifiés », « Exports ponctuels », « Imports récents ») avec un KPI clé chacune (nombre d’actions réussies sur 7 jours, prochaines exécutions, avertissements actifs).
   - **Codes couleur harmonisés** : reprendre la palette `wp-ui-success|warning|error` pour générer des badges lisibles en mode sombre et clair, avec des pictogrammes Dashicons pour une compréhension immédiate.
   - **Panneau coulissant** : le bouton « Voir le rapport » ouvre un panneau latéral (`wp-components` `Panel`) qui liste les derniers événements avec leurs métadonnées détaillées et un lien direct vers l’historique complet.
   - **Export rapide** : un bouton secondaire « Exporter maintenant » reste visible dans la carte principale pour lancer la file d’attente sans quitter le tableau de bord.
2. **Timeline interactive des exports** : transformer le tableau historique en frise chronologique filtrable (par statut, auteur, période) avec un empilement compact sur mobile. Chaque événement offrirait des actions rapides (re-télécharger, signaler un échec), à l’image des dashboards de migrations pro. La structure actuelle des colonnes (`wp-list-table`) sert de base pour générer cette timeline et centraliser les métadonnées (durée, taille, exclusions).【F:theme-export-jlg/templates/admin/export.php†L487-L520】
   - **Construction** : remplacer les lignes du `wp-list-table` par des cartes horizontales (Flex) regroupées par jour, avec une ligne temporelle verticale pour faciliter la lecture.
   - **Filtres persistants** : conserver les préférences de filtre dans l’option utilisateur pour retrouver la même vue entre deux sessions, comme le font les outils de monitoring.
   - **Actions contextuelles** : afficher des boutons secondaires (« Télécharger », « Réexécuter », « Consulter le log ») directement sur chaque événement et basculer en vue détaillée via un accordéon.
   - **Mobile-first** : empiler les cartes en colonnes uniques sur mobile, avec un résumé minimal (statut + durée + CTA principal) et un bouton « Détails » qui déploie les métadonnées.
3. **Assistant contextuel d’import** : enrichir l’étape 2 avec un panneau d’aide contextualisé qui affiche des « astuces » en fonction des filtres actifs (par exemple suggérer le tri par date après une recherche). On peut tirer parti de la barre d’outils existante (recherche, filtres, largeur d’aperçu) pour afficher des conseils dynamiques similaires aux wizards professionnels, voire proposer un mode « tour guidé » activable via un bouton d’assistance.【F:theme-export-jlg/templates/admin/import-preview.php†L30-L160】
   - **Panneau dynamique** : une colonne latérale se met à jour selon les états (aucune sélection, conflits détectés, mode portable activé) et affiche des recommandations, liens vers la documentation ou rappels d’accessibilité.
   - **Micro-guidage** : intégrer des tooltips riches (`Tooltip` + `Popover`) sur les filtres pour expliquer leur impact et proposer des raccourcis clavier (tabulation, sélection multiple).
   - **Tour guidé** : un bouton « Découvrir l’assistant » déclenche une séquence de surcouches (type `wp-pointer`) présentant les étapes principales pour les nouveaux utilisateurs.
   - **Résumé exportable** : à la confirmation finale, générer un résumé JSON/PDF listant les éléments importés, les slugs recalculés et les éventuels avertissements pour documenter l’opération.
4. **Mode compact & ancrages clavier** : ajouter un commutateur « Vue compacte » qui réduit les marges, regroupe les cartes et affiche un sommaire collant en haut de l’écran. Sur mobile, on pourrait remplacer les onglets horizontaux par des accordéons verticaux pour éviter le défilement latéral, en s’appuyant sur la grille responsive définie dans la feuille de styles du plugin.【F:theme-export-jlg/assets/css/admin-styles.css†L15-L37】
   - **Commutateur accessible** : bouton `ToggleControl` placé dans la barre d’outils principale, mémorisé par utilisateur (`user_meta`) pour conserver la préférence.
   - **Grille fluide** : les cartes passent de 3 à 1 colonne avec des `gap` réduits en mode compact, et les titres utilisent des tailles de police réduites (`clamp`) pour maintenir la hiérarchie.
   - **Sommaire collant** : un mini sommaire (liens ancrés vers les sections Export/Import/Debug) reste fixe pour la navigation clavier, avec un indicateur de focus visible.
   - **Accordéons mobiles** : sous 600 px, les onglets deviennent des accordéons accordés sur `aria-controls`/`aria-expanded` pour garantir une navigation assistée.
5. **Panneau d’actions flottant** : proposer un bouton flottant « Actions rapides » (export immédiat, téléchargement du dernier ZIP, accès au rapport de débogage) disponible sur toutes les pages de l’extension. Ce composant reprendrait la logique des FAB (floating action buttons) visibles dans les outils de monitoring SaaS et pourrait se caler sur les classes `button`/`wp-ui-primary` déjà employées pour rester cohérent avec l’admin.【F:theme-export-jlg/templates/admin/export.php†L197-L485】【F:theme-export-jlg/assets/css/admin-styles.css†L41-L120】
   - **Positionnement fixe** : bouton circulaire (`position: fixed`, coin inférieur droit) respectant les marges de l’admin et compatible avec les écrans RTL grâce à un calcul dynamique.
   - **Menu radial** : au clic ou via `Enter`, le bouton déploie un mini-menu (`SpeedDial`) listant trois actions principales, chacune avec un raccourci clavier (`Alt+Shift+…`).
   - **Retour visuel** : animations légères (`transform`, `opacity`) pour signifier l’ouverture/fermeture, avec un état `aria-expanded` synchronisé.
   - **Extensibilité** : prévoir un filtre PHP (`apply_filters`) pour permettre aux développeurs d’ajouter leurs propres actions personnalisées sans forker l’UI.

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
