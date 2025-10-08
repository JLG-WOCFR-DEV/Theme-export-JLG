# Personnaliser les notifications Theme Export JLG

Ce guide regroupe des recettes prêtes à l’emploi pour capitaliser sur les métadonnées exposées par `TEJLG_Export_Notifications` et sur le crochet `tejlg_export_persist_archive_failed`. Chaque exemple est autonome et peut être ajouté dans le fichier `functions.php` d’un thème enfant ou dans une extension dédiée.

## Comprendre le payload normalisé `$event`

À partir de la version 1.5, chaque export déclenche la construction d’un tableau normalisé `$event` partagé entre tous les filtres :`tejlg_export_notifications_event`, `tejlg_export_notifications_mail`, `tejlg_export_notifications_should_send_mail`, `tejlg_export_notifications_recipients` et l’action `tejlg_export_notifications_dispatched`.

Ce payload contient notamment :

- `result` / `result_label` : statut machine et libellé humain (`success`, `warning`, `error`, `info`).
- `origin` / `origin_label` : déclencheur (`web`, `cli`, `schedule`).
- `completed_at` et `completed_at_gmt` : date de fin localisée et au format ISO 8601 (UTC).
- `duration` / `duration_label` : durée en secondes et version lisible (`2 minutes`).
- `size` / `size_label` : taille du ZIP en octets et format humain.
- `user` : identifiant et nom d’affichage de l’initiateur (si disponible).
- `exclusions` : motifs d’exclusion appliqués.
- `persistent_url` : lien de téléchargement de l’archive lorsqu’elle est conservée.
- `job_id` : identifiant interne de la tâche d’export.
- `site` : nom du site (`blogname`) et URL `home_url()`.

Grâce à ce socle commun, il devient simple d’alimenter un rapport HTML, un webhook ou un outil d’observabilité sans retraiter l’entrée d’historique brute.

## Ajuster le contenu de l’e-mail standard

Le filtre `tejlg_export_notifications_mail` reçoit désormais le payload complet (destinataires, sujet, corps texte et HTML, en-têtes, pièces jointes, `$event`). On peut le modifier pour enrichir l’e-mail ou changer la liste des destinataires.

```php
add_filter(
    'tejlg_export_notifications_mail',
    static function (array $payload, array $entry, array $job, array $context, array $event) {
        // Ajouter un préfixe lisible et personnaliser les destinataires.
        $payload['subject'] = sprintf('[Theme Export] %s — %s', $event['result_label'], $payload['subject']);
        $payload['to'][]    = 'observability@example.com';
        $payload['headers'][] = 'Reply-To: support@example.com';

        // Construire un contenu HTML riche tout en conservant une version texte.
        $rows = [
            sprintf('<li><strong>Déclencheur :</strong> %s</li>', esc_html($event['origin_label'])),
            sprintf('<li><strong>Durée :</strong> %s</li>', esc_html($event['duration_label'] ?: __('Instantané', 'theme-export-jlg'))),
            sprintf('<li><strong>Taille :</strong> %s</li>', esc_html($event['size_label'] ?: __('Inconnue', 'theme-export-jlg'))),
        ];

        if ($event['persistent_url']) {
            $rows[] = sprintf('<li><a href="%1$s">%1$s</a></li>', esc_url($event['persistent_url']));
        }

        $payload['message_html'] = sprintf(
            '<p>%s</p><ul>%s</ul>',
            esc_html($event['result_label']),
            implode('', $rows)
        );

        // Alignement texte : on fournit une version sans balises pour les lecteurs non HTML.
        $payload['message'] = wp_strip_all_tags($payload['message_html']);
        $payload['headers'][] = 'Content-Type: text/html; charset=UTF-8';

        return $payload;
    },
    10,
    5
);
```

## Déclencher une notification Slack via webhook

Le nouveau hook `tejlg_export_notifications_dispatched` est déclenché après chaque traitement (e-mail envoyé, ignoré ou en échec). Il reçoit le payload `$event` et un booléen `$sent`. On peut donc publier un message Slack en parallèle, ou remplacer complètement l’e-mail en filtrant `tejlg_export_notifications_should_send_mail`.

```php
add_filter(
    'tejlg_export_notifications_should_send_mail',
    static function ($should_send, array $event) {
        // Laisser l’interface gérer les erreurs critiques, mais ignorer les succès.
        if ('success' === $event['result']) {
            return false;
        }

        return $should_send;
    },
    10,
    2
);

add_action(
    'tejlg_export_notifications_dispatched',
    static function (array $event, $payload, array $entry, array $job, array $context, bool $sent) {
        $webhook = getenv('TEJLG_SLACK_WEBHOOK');

        if (!$webhook) {
            return;
        }

        $emoji = [
            'success' => ':white_check_mark:',
            'warning' => ':warning:',
            'error'   => ':x:',
        ][$event['result']] ?? ':information_source:';

        $message = sprintf(
            "%s Export %s\nDurée : %s — Taille : %s",
            $emoji,
            $event['result_label'],
            $event['duration_label'] ?: __('Instantané', 'theme-export-jlg'),
            $event['size_label'] ?: __('Inconnue', 'theme-export-jlg')
        );

        wp_remote_post(
            $webhook,
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode([
                    'text' => $message,
                    'attachments' => [
                        [
                            'title' => get_bloginfo('name'),
                            'title_link' => home_url(),
                            'text' => $event['persistent_url'] ? $event['persistent_url'] : __('Pas d’archive disponible', 'theme-export-jlg'),
                        ],
                    ],
                ]),
                'timeout' => 3,
            ]
        );
    },
    10,
    6
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
