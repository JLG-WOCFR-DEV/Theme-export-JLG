# Personnaliser les notifications Theme Export JLG

Ce guide regroupe des recettes prêtes à l’emploi pour capitaliser sur les métadonnées exposées par `TEJLG_Export_Notifications` et sur le nouveau crochet `tejlg_export_persist_archive_failed`. Chaque exemple est autonome et peut être ajouté dans le fichier `functions.php` d’un thème enfant ou dans une extension dédiée.

## Ajuster le contenu de l’e-mail standard

Le filtre `tejlg_export_notifications_mail` reçoit le payload normalisé (sujet, destinataires, contenu HTML/texte) ainsi que l’entrée d’historique associée. On peut le modifier pour enrichir l’e-mail avec des informations supplémentaires ou changer la liste des destinataires.

```php
add_filter(
    'tejlg_export_notifications_mail',
    static function (array $payload, array $history_entry) {
        $payload['subject'] = sprintf('[Theme Export] %s — %s', $history_entry['status'], $payload['subject']);
        $payload['headers'][] = 'Reply-To: support@example.com';
        $payload['to'][]      = 'observability@example.com';

        $payload['message_html'] .= sprintf(
            '<p><strong>Durée :</strong> %s – <strong>Taille :</strong> %s</p>',
            esc_html($history_entry['duration_human']),
            esc_html($history_entry['size_human'])
        );

        return $payload;
    },
    10,
    2
);
```

## Déclencher une notification Slack via webhook

À partir du même filtre, on peut propager une version JSON du payload vers un webhook Slack. L’exemple suivant utilise `wp_remote_post` pour envoyer un message formaté avec les principaux indicateurs.

```php
add_filter(
    'tejlg_export_notifications_mail',
    static function (array $payload, array $history_entry) {
        $webhook_url = getenv('TEJLG_SLACK_WEBHOOK');

        if (!$webhook_url) {
            return $payload;
        }

        $status_emoji = [
            'success' => ':white_check_mark:',
            'warning' => ':warning:',
            'error'   => ':x:',
        ];

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf(
                        "%s *Export %s*\nDurée : %s – Taille : %s",
                        $status_emoji[$history_entry['status']] ?? ':information_source:',
                        $history_entry['status'],
                        $history_entry['duration_human'],
                        $history_entry['size_human']
                    ),
                ],
            ],
        ];

        wp_remote_post(
            $webhook_url,
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['blocks' => $blocks]),
                'timeout' => 3,
            ]
        );

        return $payload;
    },
    10,
    2
);
```

## Exploiter les échecs de persistance

Lorsque la copie du ZIP persistant échoue (dossier inaccessible, collision de nom, etc.), le plugin déclenche désormais l’action `tejlg_export_persist_archive_failed`. On peut l’utiliser pour créer un journal ou avertir une équipe d’astreinte.

```php
add_action(
    'tejlg_export_persist_archive_failed',
    static function ($job, array $context) {
        if (!function_exists('wp_remote_post')) {
            return;
        }

        $payload = [
            'job_id'    => $context['job_id'] ?? ($job['id'] ?? ''),
            'reason'    => $context['reason'] ?? 'unknown',
            'timestamp' => gmdate(DATE_ATOM),
            'details'   => $context,
        ];

        wp_remote_post(
            'https://observability.example.com/hooks/theme-export',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode($payload),
                'timeout' => 2,
            ]
        );
    },
    10,
    2
);
```

Pensez à filtrer `tejlg_export_persist_archive_log_errors` si vous souhaitez désactiver le log PHP local (par exemple sur un environnement de recette), tout en conservant l’appel au webhook :

```php
add_filter(
    'tejlg_export_persist_archive_log_errors',
    static function () {
        return false;
    }
);
```

Ces quelques extraits couvrent les cas les plus fréquents (e-mail enrichi, webhook Slack, observabilité). Ils servent également de base pour alimenter des scénarios plus avancés décrits dans la feuille de route (rapports hebdomadaires, notifications multi-canaux).
