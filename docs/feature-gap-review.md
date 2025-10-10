# Analyse des fonctionnalités manquantes

Cette note recense les écarts constatés entre le programme fonctionnel décrit dans la documentation et l’état actuel du code. Chaque point s’appuie sur les spécifications existantes et sur une revue du code source.

## 1. Résumé exportable en fin d’assistant
- **Ce que prévoit le programme** : le plan d’implémentation précise qu’un export JSON/PDF doit être généré à la dernière étape pour récapituler les éléments traités.【F:readme.md†L124-L167】
- **Constat dans le code** : l’assistant d’export s’arrête après la génération de l’archive ZIP ou la diffusion du flux sans proposer de fichier de synthèse. La méthode `handle_theme_export_form_submission()` redirige immédiatement après le succès de l’export, sans générer de rapport annexe.【F:theme-export-jlg/includes/class-tejlg-admin-export-page.php†L1205-L1342】

## 2. Interface graphique pour l’import/export de profils
- **Ce que prévoit le programme** : le lot « Orchestration & profils » mentionne la création d’un écran minimal côté administration pour importer/exporter un profil sans passer par WP-CLI.【F:readme.md†L128-L134】
- **Constat dans le code** : l’interface d’administration expose uniquement quatre onglets (Exporter, Importer, Guide de migration, Débogage). Aucun écran n’est dédié aux profils ou aux réglages persistés, l’action restant limitée aux commandes CLI `settings` existantes.【F:theme-export-jlg/includes/class-tejlg-admin.php†L312-L344】【F:theme-export-jlg/includes/class-tejlg-cli.php†L16-L35】

## 3. Commandes WP-CLI pour piloter la planification
- **Ce que prévoit le programme** : la feuille de route recommande d’ajouter des sous-commandes permettant de configurer les fréquences, déclencher un export planifié ou envoyer un rapport depuis WP-CLI.【F:readme.md†L148-L149】
- **Constat dans le code** : la classe `TEJLG_CLI` enregistre les commandes pour l’export ponctuel, l’import, l’historique et la gestion des réglages, mais aucune sous-commande ne permet encore de modifier la planification ou de lancer un export planifié à la demande.【F:theme-export-jlg/includes/class-tejlg-cli.php†L16-L338】

## 4. Optimisations mobile et bouton d’actions rapides
- **Ce que prévoit le programme** : la section « Optimisation mobile » demande la conversion des onglets en accordéons sous 600 px et l’ajout d’un bouton flottant « Actions rapides ».【F:readme.md†L120-L122】【F:readme.md†L167-L176】
- **Constat dans le code** : la mise en page conserve des onglets horizontaux et ne définit aucun composant flottant. Le bandeau principal ne propose qu’un lien vers l’assistant et un bouton de preset, sans bouton fixe ou menu radial pour les actions rapides.【F:theme-export-jlg/templates/admin/export.php†L760-L822】【F:theme-export-jlg/assets/css/admin-styles.css†L1-L120】

## 5. Vue compacte persistante
- **Ce que prévoit le programme** : la feuille de route prévoit d’introduire un `ToggleControl` mémorisé côté utilisateur pour activer une vue compacte réduisant marges et espacements.【F:readme.md†L133-L134】
- **Constat dans le code** : l’interface propose uniquement une bascule « Mode simple / Mode expert » stockée via `tejlg_interface_mode`, sans préférence dédiée à une vue compacte ni ajustements CSS associés.【F:theme-export-jlg/templates/admin/export.php†L784-L820】【F:theme-export-jlg/assets/css/admin-styles.css†L1-L200】

---
Ces points constituent la liste prioritaire des fonctionnalités à intégrer avant la phase de validation finale. Ils peuvent être utilisés pour alimenter un plan d’action ou de nouveaux tests automatisés.
