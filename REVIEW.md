# Code Review Notes

## Blocking Issues

- Aucun à date (nov. 2024). La signature factice de `wp_check_invalid_utf8()` dans la suite PHPUnit accepte désormais le paramètre `$strip`, ce qui aligne le comportement sur WordPress.【F:tests/test-export-sanitization.php†L1-L18】

## Observations récentes

- *(Résolu — voir mises à jour)* `TEJLG_Export::persist_export_archive()` renvoyait un tableau vide en cas d’échec de copie ou de création de dossier sans log dédié.【F:theme-export-jlg/includes/class-tejlg-export.php†L1973-L2053】
- *(Résolu — voir mises à jour)* Le module de notifications (`TEJLG_Export_Notifications`) exposait un hook `tejlg_export_notifications_mail` sans exemples pratiques.【F:theme-export-jlg/includes/class-tejlg-export-notifications.php†L7-L205】
- Les exports ne publiaient pas de payload unique comparable aux solutions pro (ManageWP, BlogVault) pour piloter des alertes multi-canaux. Les filtres historiques recevaient des tableaux hétérogènes qu’il fallait retraiter à chaque intégration.【F:theme-export-jlg/includes/class-tejlg-export-notifications.php†L7-L420】

## Mises à jour (déc. 2024)

- ✅ `persist_export_archive()` déclenche désormais l’action `tejlg_export_persist_archive_failed` et consigne le motif de l’échec avant de retourner un résultat vide, avec un filtre pour neutraliser le log si besoin.【F:theme-export-jlg/includes/class-tejlg-export.php†L1996-L2060】
- ✅ La documentation développeur inclut des recettes prêtes à l’emploi pour personnaliser `tejlg_export_notifications_mail` et réagir aux échecs de persistance.【F:docs/notifications-guide.md†L1-L122】

## Mises à jour (janv. 2025)

- ✅ `TEJLG_Export_Notifications` normalise désormais un payload `$event` injecté dans tous les filtres, fournit une version HTML, expose un drapeau `tejlg_export_notifications_should_send_mail` et déclenche l’action `tejlg_export_notifications_dispatched`, ce qui met l’extension au niveau des apps pro en matière d’observabilité.【F:theme-export-jlg/includes/class-tejlg-export-notifications.php†L180-L420】
- ✅ Le guide notifications documente ces nouveaux hooks avec des recettes Slack/e-mail, ce qui réduit l’écart fonctionnel avec les workflows des solutions SaaS de migration.【F:docs/notifications-guide.md†L1-L150】

## Observations (fév. 2025)

- 🔎 Le badge FPS/latence du panneau de débogage cesse de se mettre à jour après un passage de l’onglet en arrière-plan : `stopMonitoring()` coupe définitivement la boucle `requestAnimationFrame` sans reprise lors du retour en visibilité. Une correction est recommandée pour éviter aux équipes support de devoir recharger l’écran.【F:theme-export-jlg/assets/js/admin-debug.js†L304-L330】【F:docs/code-review-2025-02-18.md†L8-L19】
- *(Résolu — voir mises à jour)* L’audit RGAA confirmait la bonne gestion du focus et des annonces vocales sur les écrans d’import/export, tout en suggérant de mesurer le contraste réel des badges de catégories générés via `color-mix` pour garantir le ratio 4,5 :1 sur tous les thèmes d’administration.【F:docs/code-review-2025-02-18.md†L21-L44】

## Mises à jour (fév. 2025)

- ✅ Le moniteur FPS/latence s’interrompt désormais proprement en arrière-plan puis reprend automatiquement dès que l’onglet redevient visible, grâce à une bascule `pause/resume` qui réinitialise la boucle `requestAnimationFrame` sans perdre les mesures affichées.【F:theme-export-jlg/assets/js/admin-debug.js†L180-L347】
- ✅ Les badges de catégories ajustent dynamiquement la couleur de texte via un contrôle du ratio de contraste RGAA, avec un fallback CSS en cas d’absence de support `color-mix`, une réévaluation lorsqu’un thème à fort contraste est activé et une surveillance des changements de palette (`data-admin-color`, classes `admin-color-*`) pour maintenir le ratio après un switch à chaud.【F:theme-export-jlg/assets/js/admin-export.js†L1-L230】【F:theme-export-jlg/assets/js/admin-export.js†L240-L580】【F:theme-export-jlg/assets/css/admin-styles.css†L1700-L1724】

## Recommandations

- Proposer un connecteur optionnel (S3, SFTP) pour rapprocher la redondance des exports de ce que proposent BlogVault ou ManageWP, en capitalisant sur le nouveau payload `$event` pour tracer les envois.
- Ajouter un gabarit d’e-mail HTML personnalisable (via template PHP ou bloc) afin d’aligner le rendu sur les notifications transactionnelles des suites pro.
