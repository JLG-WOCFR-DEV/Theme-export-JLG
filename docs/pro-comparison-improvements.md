# Comparaison avec les suites professionnelles et pistes d'amélioration

## 1. Synthèse comparative

| Axe | Theme Export JLG aujourd'hui | Observé dans ManageWP / BlogVault / WP Migrate | Opportunités |
| --- | --- | --- | --- |
| Prise en main | Page unique avec stepper, cartes d'état et panneaux `<details>` pour l'historique et la planification.【F:theme-export-jlg/templates/admin/export.php†L434-L589】【F:theme-export-jlg/templates/admin/export.php†L744-L1040】 | Accueil condensé avec bandeau d'action fixe (Run, Restore) et indicateurs clé visibles en permanence. | Ajouter un header collant « Dernier export / Prochain export / Exporter maintenant » et des raccourcis latéraux.
| Configuration | Options riches (planification, exclusions, notifications) mais dissimulées derrière des panneaux expansibles sans résumé chiffré.【F:theme-export-jlg/templates/admin/export.php†L595-L704】 | Wizards mode « basique vs avancé », badges résumant les exclusions/alertes actives. | Introduire une bascule Simple/Expert avec badges (« Quotidien · 3 destinataires »), inspirée des presets UI internes.【F:docs/ui-presets.md†L1-L44】
| Feedback temps réel | File d'attente asynchrone avec relance, reprise de session et bannière de reprise.【F:theme-export-jlg/assets/js/admin-export.js†L255-L475】【F:theme-export-jlg/assets/js/admin-export.js†L520-L780】 | Progression full-screen, logs téléchargeables, relance automatique après erreur avec codes explicites. | Étendre le panneau de métadonnées (ID, code erreur, téléchargement JSON) et proposer un bouton « Réessayer » contextuel.【F:theme-export-jlg/assets/js/admin-export.js†L1183-L1491】
| Fiabilité perçue | Backoff exponentiel, persistance LocalStorage/SessionStorage, gestion cancel/retry.【F:theme-export-jlg/assets/js/admin-export.js†L1125-L1491】【F:theme-export-jlg/assets/js/admin-export.js†L1503-L1865】 | Persistance serveur du job, rapports santé automatiques, collecte de logs. | Sauvegarder l'ID de job côté session + banner de reprise par défaut, proposer export JSON des logs depuis l'UI.
| Design | Palette riche, gradients multiples, ombres marquées, badges qualité dynamiques.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L200】【F:theme-export-jlg/templates/admin/export.php†L551-L618】 | Interfaces plus sobres, focus sur contrastes simples et icônes statutaires. | Décliner un preset « Pavillon » léger et limiter les ombres/gradients aux éléments interactifs.【F:docs/ui-presets.md†L5-L19】

## 2. Recommandations UX / UI

1. **Bandeau d'action persistant** : fixer en haut de page un trio « Dernier export / Prochain export / CTA Export immédiat » pour réduire le scroll nécessaire avant d'agir.【F:theme-export-jlg/templates/admin/export.php†L434-L589】
2. **Mode Simple / Expert** : activer par défaut un mode épuré (fréquence, heure, notifications) avec badges résumés et laisser les options granulaires dans un mode Expert mémorisé par utilisateur.【F:theme-export-jlg/templates/admin/export.php†L595-L704】【F:docs/ui-presets.md†L1-L44】
3. **Guidage contextuel** : enrichir les cartes avec tooltips / liens docs (ex. « Comment structurer vos exclusions ») et afficher des exemples cliquables dans le pattern tester.【F:theme-export-jlg/assets/js/admin-export.js†L782-L960】
4. **Dashboard synthétique** : regrouper monitoring, planification et historique dans des cartes alignées avec icônes statutaires (success/warning/error) pour imiter les quick stats des apps pro.【F:theme-export-jlg/templates/admin/export.php†L551-L618】

## 3. Ergonomie & accessibilité

- **Résumé visible sans interaction** : remplacer les `<details>` critiques par des panneaux repliables dotés de badges chiffrés pour clarifier l'état des options avant ouverture.【F:theme-export-jlg/templates/admin/export.php†L595-L704】
- **Navigation clavier** : mémoriser l'état des accordéons et refléter `aria-expanded`/`aria-controls` pour suivre les bonnes pratiques Radix UI.【F:theme-export-jlg/assets/js/admin-export.js†L255-L475】
- **Mode contraste par défaut** : proposer un preset visuel à contraste élevé (issu de « Pavillon ») activable sans passer par le toggle avancé afin de se rapprocher de la lisibilité des consoles pro.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L200】【F:docs/ui-presets.md†L5-L19】

## 4. Fiabilité & résilience

- **Persistance systématique des jobs** : écrire l'ID dans `sessionStorage` dès le lancement et afficher la bannière de reprise tant que le job n'est pas terminé, même après rafraîchissement.【F:theme-export-jlg/assets/js/admin-export.js†L255-L475】【F:theme-export-jlg/assets/js/admin-export.js†L1503-L1584】
- **Feedback exploitable** : enrichir `handleError` et `updateJobMetaDisplay` avec un lien « Télécharger les logs » et un code erreur humanisé pour s'aligner sur BlogVault/ManageWP.【F:theme-export-jlg/assets/js/admin-export.js†L604-L780】【F:theme-export-jlg/assets/js/admin-export.js†L1183-L1491】
- **Plan de reprise** : automatiser une relance unique après annulation ou erreur puis proposer un bouton manuel avec minuterie indiquant la prochaine tentative.【F:theme-export-jlg/assets/js/admin-export.js†L1377-L1491】

## 5. Design & cohérence visuelle

- **Alléger les surfaces** : réduire les ombres globales (`--tejlg-shadow-lg`) et gradients pour recentrer l'attention sur les CTA, en cohérence avec les presets sobres listés dans la documentation.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L200】【F:docs/ui-presets.md†L5-L19】
- **Icônes de statut** : compléter les badges qualité (`ui_ux`, `accessibility`, `reliability`, `visual`) avec des pictogrammes cohérents et des couleurs WP (`wp-ui-success`, etc.).【F:theme-export-jlg/templates/admin/export.php†L551-L618】
- **Thèmes prêts à l'emploi** : exposer 2–3 presets (`Pavillon`, `Spectrum`, `Orbit`) directement dans l'interface pour offrir un niveau de finition comparable aux suites pro sans développement personnalisé.【F:docs/ui-presets.md†L5-L44】

Ces ajustements rapprocheraient Theme Export JLG des standards d'applications professionnelles tout en capitalisant sur le socle existant (file d'attente robuste, monitoring intégré) et en améliorant la clarté des actions clés.
