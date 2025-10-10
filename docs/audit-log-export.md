# Export du journal d'audit

Le module **Historique des exports** propose désormais un export complet des entrées filtrées, au format JSON ou CSV. Ce flux permet d'exposer l'activité d'exportation à des outils de supervision externes ou à des tableurs sans écrire de requête personnalisée.

## Depuis l'interface d'administration

1. Ouvrez l'onglet **Exporter & Outils** puis déroulez la section « Historique des exports ».
2. Appliquez les filtres souhaités (statut, origine, période, ordre d'affichage).
3. Cliquez sur **Télécharger (JSON)** ou **Télécharger (CSV)** dans le bloc « Exporter le journal filtré ».

Les deux liens respectent strictement les filtres affichés : modifier la période ou l'ordre met immédiatement à jour le fichier généré.

## Points clés des fichiers produits

- **JSON**
  - Ajoute un en-tête `site_url`, les filtres appliqués, l'horodatage de génération et la liste des entrées.
  - Chaque entrée comprend les timestamps ISO 8601 (timezone du site et UTC), le statut normalisé, le message, l'utilisateur, la durée, la taille de l'archive et les motifs d'exclusion.
  - Structure prête à être consommée par des scripts (Power Automate, n8n, Zapier via Webhooks).
- **CSV**
  - Encode le fichier en UTF‑8 avec BOM pour conserver les accents dans Excel/LibreOffice.
  - Colonnes disponibles : identifiant, timestamps site/UTC, statut, message, origine, utilisateur, durée (sec & lisible), taille (octets & lisible), nom du ZIP, URL persistante, exclusions.

## Automatisation par URL

Les mêmes paramètres GET que l'interface sont acceptés :

```text
/wp-admin/admin.php?page=theme-export-jlg&tab=export&history_result=success&history_start_date=2024-10-01&history_end_date=2024-10-31&tejlg_history_export=1&history_format=csv
```

Ajoutez toujours le nonce `tejlg_history_nonce` (présent dans les liens générés) pour satisfaire la vérification de sécurité.

### Paramètres pris en charge

| Paramètre            | Rôle | Exemple |
|----------------------|------|---------|
| `history_result`     | Filtrer par statut (`success`, `warning`, `error`, `info`). | `history_result=error` |
| `history_origin`     | Filtrer par origine (`web`, `cli`, `schedule`). | `history_origin=cli` |
| `history_start_date` | Date minimale incluse (`YYYY-MM-DD`). | `history_start_date=2024-09-01` |
| `history_end_date`   | Date maximale incluse (`YYYY-MM-DD`). | `history_end_date=2024-09-30` |
| `history_orderby`    | Tri (`timestamp`, `duration`, `zip_file_size`). | `history_orderby=duration` |
| `history_order`      | Ordre (`asc` ou `desc`). | `history_order=asc` |
| `history_format`     | Format de sortie (`json` par défaut, ou `csv`). | `history_format=json` |
| `history_limit`      | Limiter le nombre d'entrées (optionnel). | `history_limit=50` |

> ℹ️ `history_start_date` et `history_end_date` acceptent également des valeurs complètes (`2024-10-01 08:00`). Elles sont normalisées côté serveur.

## Cas d'usage

- Envoyer automatiquement le journal CSV chaque semaine à un outil de reporting.
- Brancher un workflow n8n/Zapier pour déclencher une alerte Slack dès qu'une entrée `error` est détectée.
- Conserver un audit partagé avec une équipe de support sans accorder d'accès au back-office WordPress.
