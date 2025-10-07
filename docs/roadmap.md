# Feuille de route d’amélioration

Cette feuille de route décline les axes identifiés dans le `readme.md` en lots actionnables. Chaque lot précise les objectifs, les jalons techniques et les métriques de réussite pour faciliter le pilotage continu.

## Lot 1 – Journal d’export enrichi
- **Objectif produit** : transformer l’historique actuel en source de vérité pour les exports afin de supporter les futures notifications et tableaux de bord.【F:theme-export-jlg/includes/class-tejlg-export-history.php†L6-L195】【F:theme-export-jlg/includes/class-tejlg-export.php†L300-L378】
- **Actions techniques** :
  - Étendre `TEJLG_Export_History::record_job()` pour calculer la durée réelle, la taille finale de l’archive et l’identité de l’auteur à chaque exécution.【F:theme-export-jlg/includes/class-tejlg-export-history.php†L9-L195】
  - Persister un statut détaillé (succès, avertissement, échec) en s’appuyant sur les statuts retournés par `TEJLG_Export::get_export_job_status()` et prévoir un champ `context` pour tracer l’origine (UI, WP-CLI, CRON).【F:theme-export-jlg/includes/class-tejlg-export.php†L300-L378】
  - Ajouter des hooks (`do_action`) permettant de brancher des notifications e-mail/webhook lorsque de nouvelles entrées sont enregistrées.
- **Livrables** :
  - Colonnes supplémentaires visibles dans le tableau d’historique (durée, taille, auteur, origine) avec tri et filtres basiques.【F:theme-export-jlg/templates/admin/export.php†L130-L220】
  - Documentation interne décrivant le format des entrées et les points d’extension.
- **Indicateurs de succès** :
  - 100 % des exports enregistrent une durée et une taille non nulles.
  - Couverture de tests automatisés sur la persistance portée à >80 %.

## Lot 2 – Orchestration & profils
- **Objectif produit** : permettre aux équipes d’initialiser plusieurs sites avec la même configuration d’exports/imports sans passer par un service tiers.【F:theme-export-jlg/includes/class-tejlg-cli.php†L16-L195】
- **Actions techniques** :
  - Introduire `wp theme-export-jlg settings export|import` pour sérialiser les réglages (exclusions, planification, préférences UI) et les réinjecter via WP-CLI.【F:theme-export-jlg/includes/class-tejlg-cli.php†L16-L195】
  - Signer les fichiers générés (hash + timestamp) afin de détecter les modifications manuelles et avertir l’utilisateur en ligne de commande.
  - Ajouter des filtres PHP (`apply_filters`) permettant aux développeurs d’étendre le schéma de configuration avant export/import.
- **Livrables** :
  - Commandes WP-CLI documentées dans `readme.md` et exemples d’utilisation (export puis import sur un second site).
  - Tests automatisés simulant un aller-retour de configuration dans `tests/`.
- **Indicateurs de succès** :
  - Exports/imports de configuration utilisables en CI/CD sans intervention manuelle.
  - Zéro régression sur les commandes existantes (`theme`, `patterns`, `history`).

## Lot 3 – Assistants guidés
- **Objectif produit** : fluidifier les parcours d’export et d’import grâce à des assistants multi-étapes et des conseils contextuels, inspirés des solutions professionnelles.【F:theme-export-jlg/templates/admin/export.php†L130-L220】【F:theme-export-jlg/templates/admin/import-preview.php†L30-L200】
- **Actions techniques** :
  - Décomposer les écrans d’export en composants réutilisables (étapes, boutons, alertes) et introduire une logique d’état centralisée dans `admin-scripts.js` pour piloter la progression.【F:theme-export-jlg/assets/js/admin-scripts.js†L20-L138】
  - Ajouter une colonne latérale dynamique dans l’étape d’import pour afficher des recommandations et liens contextuels en fonction des filtres sélectionnés.【F:theme-export-jlg/templates/admin/import-preview.php†L30-L200】
  - Générer un résumé téléchargeable (JSON/PDF) listant les éléments exportés/importés, les slugs recalculés et les éventuels avertissements.
- **Livrables** :
  - Nouveau composant « Assistant » documenté avec exemples dans le dossier `templates/admin/`.
  - Tests Playwright couvrant la navigation multi-étapes et la génération du résumé.
- **Indicateurs de succès** :
  - Temps moyen pour configurer un export complet réduit de 30 % lors des tests utilisateurs.
  - Score Lighthouse Accessibility ≥ 95 sur les nouveaux parcours.

## Lot 4 – Vue compacte & mobile
- **Objectif produit** : garantir une expérience optimale sur petits écrans et pour les utilisateurs experts qui privilégient une densité d’informations élevée.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L160】
- **Actions techniques** :
  - Introduire un `ToggleControl` mémorisé dans les métadonnées utilisateur pour activer la vue compacte et appliquer des classes spécifiques côté CSS.【F:theme-export-jlg/assets/css/admin-styles.css†L15-L120】
  - Convertir les onglets secondaires en accordéons accessibles sous 600 px et ajuster les grilles (`tejlg-cards-container`) pour éviter le scroll horizontal.【F:theme-export-jlg/assets/css/admin-styles.css†L134-L160】
  - Déployer un bouton d’actions flottant commun (export rapide, dernier ZIP, rapport de débogage) avec gestion de la navigation clavier et des environnements RTL.【F:theme-export-jlg/templates/admin/export.php†L197-L485】
- **Livrables** :
  - Nouvelle préférence utilisateur visible dans l’interface et stockée côté serveur.
  - Documentation d’ergonomie listant les points de contrôle (contrastes, navigation clavier, tests sur mobile).
- **Indicateurs de succès** :
  - Score Web Vitals « CLS » ≤ 0,1 après activation de la vue compacte.
  - Retour positif (>80 %) lors des tests internes sur la lisibilité mobile.

---

Chaque lot peut être traité indépendamment mais s’intègre dans une vision commune : un plugin orienté production, extensible et aligné sur les standards d’accessibilité et de pilotage multi-sites.
