# Feuille de route d’amélioration

Cette feuille de route décline les axes identifiés dans le `readme.md` en lots actionnables. Chaque lot précise les objectifs, les jalons techniques et les métriques de réussite pour faciliter le pilotage continu.

## Lot 1 – Journal d’export enrichi
- **Statut (nov. 2024)** : ✅ livré côté persistance et UI. Les entrées conservent désormais durée, taille, origine, initiateur et URL persistante, avec filtres CLI/UI. Le rapport agrégé (`wp theme-export-jlg history report`) expose les statistiques clés et le nouveau hook `tejlg_export_history_report_ready` fournit un payload normalisé pour alimenter webhooks et alertes personnalisées.【F:theme-export-jlg/includes/class-tejlg-export-history.php†L6-L340】【F:theme-export-jlg/includes/class-tejlg-cli.php†L44-L285】【F:theme-export-jlg/includes/class-tejlg-export-notifications.php†L7-L205】 Reste à partager des recettes clé-en-main (Slack, e-mail enrichi) bâties sur ces extensions.
- **Objectif produit** : transformer l’historique en source de vérité pour préparer les futures notifications et tableaux de bord.
- **Actions techniques à poursuivre** :
  - Publier des exemples d’intégration (Slack, e-mail enrichi, webhook générique) exploitant `tejlg_export_history_report_ready`.
  - Documenter un modèle de rapport hebdomadaire (JSON + CSV) à partir des données retournées par `generate_report()`.
- **Livrables** :
  - ✅ Guide de personnalisation des notifications (filtres, formats d’e-mail, webhooks) illustré par des snippets concrets — voir [`docs/notifications-guide.md`](./notifications-guide.md).
  - ✅ Export JSON/CSV du journal depuis l’interface avec documentation dédiée — voir [`docs/audit-log-export.md`](./audit-log-export.md).
  - Exemple de rapport hebdomadaire généré à partir des métadonnées existantes.
- **Indicateurs de succès** :
  - Les intégrateurs peuvent brancher un webhook en moins de 10 minutes grâce à la documentation.
  - Les exports en erreur déclenchent systématiquement une notification contextualisée.

## Lot 2 – Orchestration & profils
- **Statut (nov. 2024)** : ✅ commandes WP-CLI disponibles (`settings export`/`import`), fichiers signés SHA-256 et filtres (`tejlg_settings_export_snapshot`…) opérationnels.【F:theme-export-jlg/includes/class-tejlg-cli.php†L207-L336】【F:theme-export-jlg/includes/class-tejlg-settings.php†L7-L224】 Prochaines étapes : proposer des presets d’environnements et une interface graphique minimale pour lancer l’import.
- **Objectif produit** : permettre aux équipes d’initialiser plusieurs sites avec la même configuration d’exports/imports sans passer par un service tiers.
- **Actions techniques à poursuivre** :
  - Définir un format de presets (`development`, `staging`, `production`) basé sur les commandes existantes.
  - Ajouter des tests de non-régression couvrant l’import/export avec signature modifiée.
- **Livrables** :
  - Tutoriel multi-sites (CLI → second site) illustrant la duplication de configuration.
  - Proposition d’écran minimal côté admin pour importer/exporter un profil sans passer par WP-CLI.
- **Indicateurs de succès** :
  - Déploiement de presets sur au moins deux environnements en moins de 5 minutes.
  - Import graphique disponible sans dépendre de la ligne de commande.

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
