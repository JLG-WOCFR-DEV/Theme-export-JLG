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

## Recommandations

- Proposer un connecteur optionnel (S3, SFTP) pour rapprocher la redondance des exports de ce que proposent BlogVault ou ManageWP, en capitalisant sur le nouveau payload `$event` pour tracer les envois.
- Ajouter un gabarit d’e-mail HTML personnalisable (via template PHP ou bloc) afin d’aligner le rendu sur les notifications transactionnelles des suites pro.
