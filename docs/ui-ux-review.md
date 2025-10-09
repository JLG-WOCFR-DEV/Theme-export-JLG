# Audit ergonomie & UX — Theme Export JLG

## Synthèse

L’interface dépasse largement le thème natif de WordPress (cartes, stepper, monitoring) mais reste en-dessous d’applications professionnelles (ManageWP, BlogVault, WP Migrate) sur trois axes : hiérarchisation des actions, assistance contextuelle et robustesse face aux échecs réseau. Les points forts sont la granularité des réglages, le suivi de progression avec reprise et les garde-fous d’accessibilité (contraste élevé, rôles ARIA). En revanche, la surcharge décorative, la fragmentation des réglages et l’absence de scénarios guidés pénalisent la prise en main et la productivité.

## Ergonomie & hiérarchisation

- Le stepper d’export guide bien les étapes, mais l’écran reste verbeux : description, cartes, collapsibles empilés obligent à scroller avant d’atteindre l’action primaire.【F:theme-export-jlg/templates/admin/export.php†L434-L589】 Pro : proposer un bandeau d’action fixe (CTA + dernier statut) pour être opérationnel immédiatement.
- Les paramètres critiques (planification, alertes) sont relégués derrière `<details>` sans résumé visuel des seuils ou destinataires.【F:theme-export-jlg/templates/admin/export.php†L595-L704】 Solution : convertir ces sections en panneaux repliables avec badges (ex. « Quotidien · 3 destinataires ») visibles sans interaction.
- Les historiques et exports secondaires sont mélangés au même niveau que l’action principale, contrairement aux apps pro qui priorisent « Run now »/« Restore ». Ajouter une barre latérale ou un entête compact avec raccourcis « Exporter maintenant », « Télécharger dernier ZIP », « Configurer la planification » réduirait la charge cognitive.

## Présentation des options

- Le formulaire de planification reprend la table WordPress historique, peu lisible sur mobile et moins cohérent avec les cartes modernes.【F:theme-export-jlg/templates/admin/export.php†L605-L704】 Repenser la zone en cards/groupes alignés type SettingsGroup (comme WP Site Tools) clarifierait les dépendances (fréquence → heure → rétention).
- Les contrôles d’exclusion/pattern tester sont puissants mais denses. Ajouter des exemples préremplis (chips cliquables) et un résumé clair (« 3 motifs exclus → 12 fichiers ») dans le stepper rapprocherait l’expérience de ManageWP.

## UX / UI

- L’identité visuelle repose sur `color-mix` et des gradients multiples, ce qui alourdit le rendu et détourne l’attention des CTA.【F:theme-export-jlg/assets/css/admin-styles.css†L1-L198】 Alléger le fond (surface plane, accents ponctuels) et réserver les ombres fortes aux éléments interactifs harmoniserait avec le backend WP.
- Les cartes de monitoring ne comportent ni icônes ni codes couleur cohérents avec les statuts (`success`, `warning`, `error`). Ajouter des pictogrammes + badges (vert/orange/rouge) et un lien direct vers l’historique filtré améliorerait la lisibilité.
- Comparativement aux solutions SaaS, il manque une vue contextuelle (« Dernier export réussi il y a X heures », « Prochain export dans Y » en haut de page) et une aide en ligne (tooltips, liens docs contextualisés) sur chaque carte.

## Performance perçue

- La feuille de style applique plusieurs grands dégradés et ombres lourdes (`box-shadow` de 32px) sur la `<body>` et les cartes, déclenchant souvent la composition GPU sur des machines modestes.【F:theme-export-jlg/assets/css/admin-styles.css†L109-L147】 Simplifier les calques (couleur unie + ombres légères) réduirait le coût de rendu et alignerait l’expérience avec les dashboards professionnels sobres.
- Le bundle JS non minifié dépasse 3 700 lignes et est chargé d’emblée, même pour les utilisateurs qui n’emploient pas la sélection de patterns ou les prévisualisations.【F:theme-export-jlg/assets/js/admin-scripts.js†L1-L3770】 Découper en modules (export, import, prévisualisation) et charger dynamiquement chaque bloc diminuerait le TTI et rapprocherait la réactivité des apps pro.
- Le polling fixe toutes les 4 s (`pollInterval`) ne tient pas compte de la charge serveur ou des erreurs répétées.【F:theme-export-jlg/assets/js/admin-scripts.js†L244-L603】 Introduire backoff exponentiel et annulation automatique après N échecs éviterait des requêtes inutiles et offrirait un feedback plus fiable.

## Accessibilité

- Les composants sont globalement balisés (rôles, aria-live) et un mode contraste est fourni.【F:theme-export-jlg/templates/admin/export.php†L420-L431】【F:theme-export-jlg/assets/js/admin-scripts.js†L200-L228】 Cependant, le contraste par défaut de certains textes (violet sur fond clair) reste limite (~3:1). Prévoir un thème par défaut plus neutre éviterait d’obliger les utilisateurs à activer le mode contraste.
- Le stepper gère le focus, mais les messages d’erreur du pattern tester reposent surtout sur la couleur et un paragraphe générique.【F:theme-export-jlg/assets/js/admin-scripts.js†L318-L475】 Ajouter des `aria-describedby` ciblés sur le `<textarea>` et résumer l’erreur en début de message améliorerait la compréhension pour les lecteurs d’écran.
- Les sections `<details>` n’exposent pas leur état aux lecteurs d’écran ni ne conservent la préférence utilisateur. Enregistrer l’état (localStorage) et refléter `aria-expanded` sur le `<summary>` faciliterait la navigation clavier.

## Fiabilité & robustesse

- L’export asynchrone prévoit la reprise d’un job précédent côté client (`resumePersistedJob`) mais ne persiste rien localement : un rechargement avant que le serveur ne renvoie `previousJob` laisse l’utilisateur sans feedback.【F:theme-export-jlg/assets/js/admin-scripts.js†L799-L857】 Stocker l’ID du job dans `sessionStorage` et afficher une bannière « Export en cours » harmoniserait avec les workflows pro.
- En cas d’échec réseau, `handleError` affiche un message générique mais ne propose ni relance ni collecte automatique de logs.【F:theme-export-jlg/assets/js/admin-scripts.js†L723-L920】 Ajouter un bouton « Réessayer », un lien vers les logs et des codes d’erreur humanisés renforcerait la confiance utilisateur.
- Le pattern tester expose les motifs invalides mais ne suggère pas de correction ni de documentation directe.【F:theme-export-jlg/assets/js/admin-scripts.js†L318-L539】 Ajouter des liens vers la doc, des exemples valides et un bouton « Réinsérer le motif corrigé » limiterait les erreurs.

## Pistes d’amélioration rapides

1. **Réordonner l’entête** : injecter un bandeau statique « Dernier export / Prochain export / Exporter maintenant » inspiré des dashboards SaaS.
2. **Moderniser la zone Planification** : remplacer la table par des cartes éditables, regrouper fréquence/heure/rétention et afficher les destinataires en chips.
3. **Optimiser les assets** : alléger le CSS décoratif, scinder le JS en bundles conditionnels et mettre en place un backoff sur le polling.
4. **Accessibilité renforcée** : contraste natif >4.5:1, persistance des panneaux `<details>`, messages d’erreur plus prescriptifs.
5. **Fiabilité** : persister l’ID de job côté client, proposer une relance rapide après erreur, exposer les logs/identifiants dans l’UI.

Ces ajustements rapprocheraient l’expérience de celle des solutions professionnelles sans réécrire le socle fonctionnel.
