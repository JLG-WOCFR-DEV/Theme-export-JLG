# Matrice de tests — Connecteurs distants Theme Export JLG

Cette matrice sert de référence aux équipes support pour valider la montée en charge et la fiabilité des envois vers les connecteurs distants (`s3` et `sftp`) exposés par `TEJLG_Export_Connectors`. Elle complète les hooks documentés dans le [guide notifications](notifications-guide.md) et s’appuie sur les résultats historisés par `TEJLG_Export_History::attach_remote_connector_results()`.

## Pré-requis

- Un site de test équipé de Theme Export JLG ≥ 1.5 avec la fonctionnalité de connecteurs activée.
- Un compte AWS S3 et/ou un serveur SFTP de recette disposant de quotas d’écriture dédiés à l’équipe QA.
- Un lot d’archives de test couvrant plusieurs tailles (50 Mo, 200 Mo, 1 Go) et au moins un export volontairement invalide (fichier tronqué ou permissions insuffisantes).
- L’accès aux journaux PHP / WP-CLI et à la base de données pour contrôler l’historique (`wp_tejlg_export_history`).

## Légende

| Résultat | Description |
| --- | --- |
| ✅ | Test conforme, aucun ajustement requis |
| ⚠️ | Anomalie non bloquante (débit réduit, reprise) nécessitant investigation |
| ❌ | Test bloquant (échec de transfert, corruption) à traiter en priorité |

## Tableau de couverture

| Priorité | Scénario | Étapes | Attendus | Notes |
| --- | --- | --- | --- | --- |
| P0 | Upload S3 nominal | 1. Configurer le filtre `tejlg_export_remote_connector_s3_settings` avec un bucket de recette et un préfixe unique.<br>2. Lancer un export complet (≥ 200 Mo).<br>3. Vérifier l’entrée d’historique associée (`remote_connectors`). | - Archive transférée dans le bucket avec le préfixe attendu.<br>- Statut `success`, durée renseignée, `persistent_url` inchangé.<br>- Aucun warning dans les logs PHP ou WP-CLI. | Contrôler les ACL appliquées (champ `acl`) et la classe de stockage si personnalisée. |
| P0 | Upload SFTP nominal | 1. Configurer `tejlg_export_remote_connector_sftp_settings` avec un compte de test dédié.<br>2. Déclencher un export complet (≥ 200 Mo).<br>3. Inspecter le dossier distant et l’historique. | - Fichier présent sur le serveur SFTP avec le nom attendu.<br>- Statut `success`, durée renseignée dans `remote_connectors`.<br>- Permissions du fichier alignées sur la politique cible. | Vérifier la présence d’un `umask` correct côté serveur pour éviter les permissions `777`. |
| P0 | Archivage + connecteurs concurrents | 1. Activer simultanément les connecteurs S3 et SFTP.<br>2. Déclencher 3 exports successifs (dont un via WP-CLI).<br>3. Vérifier l’ordre et la granularité des résultats dans l’historique. | - Chaque entrée contient deux résultats (`s3-primary`, `sftp-primary`).<br>- Les durées sont cohérentes avec le débit observé.<br>- Aucun verrouillage de fichier ni doublon d’uploads. | Utiliser la colonne `started_at` pour vérifier que les connecteurs sont séquencés et non exécutés en parallèle. |
| P1 | Gestion d’erreur S3 (identifiants invalides) | 1. Fournir volontairement une clé secrète invalide.<br>2. Relancer un export léger (50 Mo). | - Statut `error` pour `s3-primary` avec un message précis (`SignatureDoesNotMatch`, etc.).<br>- L’export reste marqué `success` et l’archive locale disponible. | Documenter le code de statut HTTP renvoyé pour faciliter le support N2. |
| P1 | Gestion d’erreur SFTP (quota / disque plein) | 1. Utiliser un dossier volontairement saturé ou retirer les permissions d’écriture.<br>2. Exécuter un export léger. | - Statut `error` pour `sftp-primary` indiquant le code d’échec (`disk full`, `permission denied`).<br>- L’export n’est pas interrompu côté WordPress. | Conserver les logs SFTP pour transmission à l’hébergeur si besoin. |
| P1 | Reprise après absence de connecteurs | 1. Désactiver tous les filtres de connecteur.<br>2. Effectuer un export.<br>3. Réactiver les filtres et relancer un export. | - Aucune entrée `remote_connectors` lorsqu’aucun connecteur n’est configuré.<br>- Retour à la normale une fois les filtres restaurés. | Permet de vérifier la non-régression sur les installations qui n’utilisent pas la fonctionnalité. |
| P2 | Stress test taille maximale | 1. Utiliser une archive de 1 Go (ou la taille maximale supportée par l’hébergement).<br>2. Chronométrer la durée d’upload et comparer au temps consigné. | - Débit cohérent, absence de timeouts PHP/SSH.<br>- Statut `warning` si l’upload dépasse un seuil interne (facultatif via filtre). | Surveiller la mémoire du processus PHP et ajuster la limite `max_execution_time` au besoin. |
| P2 | Nettoyage des échecs | 1. Provoquer un échec S3 ou SFTP.<br>2. Déployer un correctif (identifiants valides).<br>3. Relancer l’export. | - L’historique conserve la trace de l’échec précédent.<br>- La tentative suivante passe à `success` sans intervention manuelle. | Utiliser la commande WP-CLI `wp option get tejlg_export_history` pour auditer rapidement les derniers résultats. |

## Exploitation des résultats

- **Journalisation** : branchez-vous sur l’action `tejlg_export_remote_connectors_processed` pour exporter automatiquement la matrice (ex. DataDog, Grafana) et remonter les statuts `warning` / `error` en temps réel.
- **Capacity planning** : les durées enregistrées dans le champ `duration` permettent d’estimer le débit moyen par connecteur. Conservez un historique mensuel pour affiner la taille des buckets ou volumes SFTP.
- **Retour utilisateur** : coupler les messages d’erreur avec le gabarit HTML de notification (`templates/emails/export-notification.php`) afin d’alerter les administrateurs sans ambiguïté.

## Mise à jour

- Mars 2025 : première version de la matrice, axée sur les connecteurs S3 et SFTP introduits dans Theme Export JLG 1.5.
