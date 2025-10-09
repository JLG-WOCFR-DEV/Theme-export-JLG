# Revue ergonomie, UX/UI et fiabilité

## Synthèse
- L'écran d'export concentre de nombreuses cartes, sections `<details>` et filtres dans une seule page, ce qui augmente la charge cognitive par rapport aux assistants en plusieurs étapes proposés par des solutions professionnelles (ManageWP, WP Migrate).【F:theme-export-jlg/templates/admin/export.php†L339-L703】
- Les styles personnalisés introduisent un fond dégradé, des ombres profondes et des mélanges de couleurs qui s'éloignent des palettes sobres de l'admin WordPress et peuvent créer des contrastes insuffisants dans certains schémas de couleurs, contrairement aux interfaces pro qui privilégient des surfaces neutres et modulaires.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L120】【F:theme-export-jlg/assets/css/admin-styles.css†L320-L372】
- Le script `admin-scripts.js` centralise l'ensemble des comportements (file d'export asynchrone, sélecteur de compositions, calculs de performances, formulaires multi-étapes) dans un fichier monolithique chargé systématiquement, ce qui dégrade le Time-To-Interactive par rapport aux apps pro qui chargent des bundles différenciés et des modules paresseux selon l'onglet ouvert.【F:theme-export-jlg/assets/js/admin-scripts.js†L1-L3920】
- Les métriques de performance du navigateur sont calculées en continu dès que les éléments ciblés existent, alors que la plupart des produits équivalents déclenchent ces mesures à la demande pour éviter l'overhead côté CPU.【F:theme-export-jlg/assets/js/admin-scripts.js†L3469-L3740】
- La persistance des archives repose exclusivement sur le dossier uploads local sans circuit de reprise ni stockage externe, ce qui limite la résilience par rapport aux services managés qui poussent vers S3/SFTP et notent les échecs de transfert de manière exploitable.【F:theme-export-jlg/includes/class-tejlg-export.php†L1990-L2149】

## Ergonomie & présentation des options
### Constats
- Le tableau de bord affiche simultanément les résumés, la file d'export asynchrone, le testeur de motifs, la planification, la sauvegarde des compositions et l'historique complet dans une seule vue scrollable.【F:theme-export-jlg/templates/admin/export.php†L339-L984】
- Les formulaires annexes sont encapsulés dans des `<details>` ouverts conditionnellement, mais leur état n'est pas mémorisé entre deux visites, ce qui oblige à re-déplier les sections fréquemment utilisées.【F:theme-export-jlg/templates/admin/export.php†L595-L712】
- La sélection des compositions dans `export-pattern-selection.php` expose filtres, tris et aperçus dans une grille dense sans mode compact ni résumé rapide pour les utilisateurs experts.【F:theme-export-jlg/templates/admin/export-pattern-selection.php†L17-L160】

### Pistes par rapport aux outils pro
- Les solutions professionnelles segmentent les étapes critiques (sélection > exclusions > confirmation) et affichent un récapitulatif téléchargeable avant action.
- Les offres SaaS proposent des vues « compactes » ou des listes condensées pour parcourir rapidement des dizaines de compositions.

### Recommandations
1. Transformer le formulaire d'export principal en wizard persistant (stockage dans `user_meta`) : enregistrer l'étape courante et les valeurs saisies pour retrouver l'état d'avancement lors d'une prochaine ouverture.【F:theme-export-jlg/assets/js/admin-scripts.js†L3759-L3920】
2. Ajouter un mode compact dans la sélection de compositions (affichage en liste avec cases à cocher et statistiques condensées) pour se rapprocher des workflows des migrations pro.【F:theme-export-jlg/templates/admin/export-pattern-selection.php†L45-L160】
3. Déporter l'historique détaillé dans un panneau latéral ou une page dédiée, et conserver sur le tableau de bord uniquement un widget synthétique avec tendances 7 jours et CTA primaires.【F:theme-export-jlg/templates/admin/export.php†L801-L984】

## UX / UI
### Constats
- Le fond en dégradé, les ombres multiples et les `color-mix` imposent une identité forte mais peuvent réduire la lisibilité lorsqu'un thème admin sombre est actif ou sur des écrans moins calibrés.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L118】
- Les cartes utilisent une hiérarchie typographique unique (Inter 14-16px) qui n'offre pas toujours une distinction suffisante entre titres, labels et aides contextuelles.【F:theme-export-jlg/assets/css/admin-styles.css†L320-L372】

### Comparaison
- Les produits professionnels privilégient des surfaces neutres, des variations typographiques plus marquées et un contraste vérifié automatiquement.

### Recommandations
1. Introduire des classes utilitaires basées sur `wp-components` (`Card`, `Panel`, `Notice`) pour bénéficier du theming natif clair/sombre sans multiplier les dégradés personnalisés.【F:theme-export-jlg/templates/admin/export.php†L339-L703】
2. Ajouter un test automatique de contraste (hook JS utilisant `wp.a11y.speak` ou `@wordpress/color`), déclenché lors du basculement de thème, pour avertir l'utilisateur en cas de ratio insuffisant.<br>Implémentation possible via un module léger chargé au clic sur le toggle de contraste.【F:theme-export-jlg/assets/js/admin-scripts.js†L160-L242】
3. Définir une échelle typographique (titres 18px, sous-titres 16px, aides 13px) et harmoniser les marges pour se rapprocher des patterns d'interface observés dans BlogVault ou WP Migrate.【F:theme-export-jlg/assets/css/admin-styles.css†L320-L372】

## Performance
### Constats
- `admin-scripts.js` dépasse 3 900 lignes et est chargé sur chaque onglet, même lorsque seule une fraction des fonctionnalités est nécessaire (ex. onglet Débogage).【F:theme-export-jlg/assets/js/admin-scripts.js†L1-L3920】
- Les métriques FPS/latence démarrent immédiatement et maintiennent un `requestAnimationFrame` permanent dès que les éléments sont présents, consommant CPU/GPU sur les postes moins puissants.【F:theme-export-jlg/assets/js/admin-scripts.js†L3469-L3740】

### Recommandations
1. Scinder le script en modules par onglet (`export.js`, `import.js`, `debug.js`) et charger dynamiquement via `wp_enqueue_script` conditionnel pour réduire le bundle initial.【F:theme-export-jlg/includes/class-tejlg-admin.php†L141-L210】【F:theme-export-jlg/assets/js/admin-scripts.js†L1-L3920】
2. Démarrer les mesures de performance à la demande (bouton « Mesurer » avec `AbortController`) et arrêter la boucle après inactivité, calqué sur l'approche de Lighthouse CI UI.【F:theme-export-jlg/assets/js/admin-scripts.js†L3480-L3740】
3. Mettre en cache côté serveur les données volumineuses (liste des compositions, historique paginé) via `transient` ou requêtes REST avec pagination pour éviter de générer et d'injecter de longues listes à chaque chargement.【F:theme-export-jlg/templates/admin/export.php†L801-L984】【F:theme-export-jlg/templates/admin/export-pattern-selection.php†L45-L160】

## Accessibilité
### Constats
- Les dropzones sont accessibles au clavier et gèrent `role="button"` mais ne communiquent pas l'état de chargement en cas d'erreur de type MIME directement via `aria-live` (les messages sont injectés plus loin dans le DOM).【F:theme-export-jlg/templates/admin/import.php†L15-L68】【F:theme-export-jlg/assets/js/admin-scripts.js†L20-L158】
- Les étapes d'export appliquent correctement `aria-current` et le focus sur le titre, mais la progression n'est pas annoncée par un lecteur d'écran lors du changement d'étape.【F:theme-export-jlg/assets/js/admin-scripts.js†L3759-L3920】

### Recommandations
1. Ajouter un `aria-live="assertive"` localisé au sein du formulaire d'import/export pour annoncer immédiatement les erreurs de validation et les résultats du testeur d'exclusion.【F:theme-export-jlg/templates/admin/export.php†L500-L535】【F:theme-export-jlg/assets/js/admin-scripts.js†L240-L320】
2. Déclencher `wp.a11y.speak()` avec un message succinct (« Étape 2 sur 3 : Filtres ») lors de `setStep()` pour s'aligner sur les pratiques de Gutenberg et des assistants SaaS.【F:theme-export-jlg/assets/js/admin-scripts.js†L3800-L3888】
3. Prévoir un raccourci clavier global (ex. `Shift+?`) qui ouvre une modale de raccourcis et liste les actions principales, comme le proposent les solutions pro orientées productivité.

## Fiabilité & observabilité
### Constats
- La persistance des archives s'effectue par simple `copy` vers `wp-content/uploads/theme-export-jlg/` sans vérification d'espace disque restant ni reprise en cas d'échec.【F:theme-export-jlg/includes/class-tejlg-export.php†L2060-L2149】
- Les erreurs critiques sont remontées via `error_log` et hooks personnalisés, mais il n'existe pas de liaison native avec le journal d'événements WordPress (`WP_Site_Health`, `wp_get_logger`).【F:theme-export-jlg/includes/class-tejlg-export.php†L2137-L2149】
- Les exports planifiés utilisent `wp_schedule_event`, mais aucun mécanisme n'est prévu pour relancer une tâche bloquée ou notifier lorsqu'une exécution manque son créneau (cron raté).【F:theme-export-jlg/includes/class-tejlg-export.php†L88-L160】【F:theme-export-jlg/includes/class-tejlg-export.php†L502-L556】

### Recommandations
1. Intégrer un stockage optionnel vers S3/SFTP via une couche d'abstraction (filtre `tejlg_export_persist_archive_destination`) pour offrir la même résilience que les offres premium.【F:theme-export-jlg/includes/class-tejlg-export.php†L1990-L2149】
2. Remplacer `error_log` par l'API `wp_get_logger()` ou un logger PSR-3 injectable, et ajouter une entrée dédiée dans Site Health lorsque la persistance échoue pour aligner la visibilité sur celle des concurrents.【F:theme-export-jlg/includes/class-tejlg-export.php†L2137-L2149】
3. Suivre les exécutions planifiées via un compteur stocké (`last_run`, `missed_runs`) et déclencher une notification si WP-Cron manque plus de N occurrences, à l'image des systèmes de monitoring intégrés des services managés.【F:theme-export-jlg/includes/class-tejlg-export.php†L500-L556】
