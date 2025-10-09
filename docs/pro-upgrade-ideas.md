# Pistes d'amélioration professionnelles

Ces propositions complètent les chantiers déjà menés sur l'interface d'export. Elles visent à rapprocher l'expérience des attentes observées dans les suites SaaS dédiées à la sauvegarde et au déploiement.

## 1. Support proactif et observabilité
- **Webhooks et intégrations** : offrir un connecteur sortant (Slack, Teams, Discord) pour pousser les alertes critiques en temps réel sans dépendre de l'e-mail.
- **Flux d'audit exportable** : permettre le téléchargement d'un journal JSON/CSV contenant toutes les métadonnées du job (durée, taille, origine, utilisateur) sur une période donnée.
- **Tableau de bord des performances** : ajouter un micro-graphique « temps moyen de génération » et « poids moyen » avec des bornes configurables.

## 2. Collaboration et gouvernance
- **Rôles affinés** : introduire une capacité « Gestion des exports » afin de déléguer la supervision sans donner l'accès total à l'admin WordPress.
- **Notifications contextualisées** : envoyer un lien direct vers le job et vers la documentation interne, avec un résumé des actions recommandées.
- **Journal des annotations** : autoriser des commentaires sur un export (raison de la relance, anomalies détectées) visibles par l'équipe support.

## 3. Expérience utilisateur avancée
- **Assistant guidé** : proposer un walkthrough facultatif à la première utilisation pour présenter le fonctionnement du stepper et des cartes de planification.
- **Bibliothèque de motifs** : lister les exclusions les plus courantes (node_modules, cache, assets volumineux) et permettre l'ajout en un clic.
- **Génération en arrière-plan** : notifier l'utilisateur via la barre d'admin quand un export se termine, même si l'onglet a été quitté.

## 4. Fiabilité et reprise sur incident
- **Auto-reprise** : relancer automatiquement un export interrompu à cause d'une expiration nonce si le serveur confirme que le traitement n'a pas démarré.
- **Snapshots différés** : sauvegarder les paramètres de planification dans un « brouillon » pour vérifier les réglages avec un collègue avant de les activer.
- **Quotas dynamiques** : calculer la taille cumulée des archives conservées et avertir quand un seuil proche des limites d'hébergement est atteint.

## 5. Accessibilité et conformité
- **Thème à contraste renforcé certifié** : proposer un mode alternatif aligné sur RGAA/WCAG 2.2 avec indicateurs d'état textuels partout où une couleur est utilisée.
- **Navigation au clavier enrichie** : ajouter des raccourcis (par exemple `g` puis `h` pour aller à l'historique) et afficher une modale d'aide (`?`).
- **Compatibilité lecteur d'écran** : fournir un résumé synthétique du job (statut + prochain pas recommandé) via un bouton « Résumé vocal » qui déclenche un `aria-live` dédié.

Ces axes peuvent être planifiés progressivement : commencer par l'observabilité (faible dette technique), poursuivre avec la gouvernance (nécessite des capacités côté serveur), et terminer par les fonctionnalités collaboratives plus larges.
