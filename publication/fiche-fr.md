# Fiche WordPress.org, en français

La fiche de WordPress.org est rédigée en anglais dans `readme.txt`. La version française s'affiche sur fr.wordpress.org une fois traduite sur translate.wordpress.org (projet « Readme » de l'extension). Les textes ci-dessous sont prêts à y être collés.

## Titre

Pathly – surveillance de disponibilité et du tunnel de commande

## Résumé court (150 caractères au plus)

Sachez que votre tunnel de commande, votre formulaire ou votre connexion marchent. Un Ping, un Flow en vrai navigateur, rejoué après chaque mise à jour.

## Description

Votre serveur répond, la page d'accueil s'affiche… et le tunnel de commande est cassé depuis la dernière mise à jour d'une extension. Une simple vérification de disponibilité ne le voit pas. Vos clients, si.

Pathly surveille le chemin de vos visiteurs :

- **Ping** : le site répond-il ?
- **Flow** : un visiteur peut-il aller au bout ? Un vrai navigateur ajoute un produit au panier et atteint la page de paiement, ou remplit votre formulaire de contact et s'arrête avant l'envoi, ou se connecte.
- **Chain** : les services derrière le site tiennent-ils ? (à régler dans Pathly)

L'extension relie votre site WordPress ou WooCommerce à votre compte Pathly :

- **Ping posé pour vous.** Dès que la clé d'API est enregistrée, Pathly vérifie votre page d'accueil toutes les cinq minutes. Si un Ping existe déjà sur cette adresse, l'extension le reprend : pas de doublon.
- **Flow préparé par Lumi.** Lumi, l'assistant de Pathly, reconnaît WooCommerce et WordPress et prépare un Flow : le tunnel de commande jusqu'à la page de paiement avec WooCommerce, sinon votre formulaire de contact ou votre connexion. Chaque Flow proposé est rejoué avant de vous être montré. Lumi ne paie rien, n'envoie aucun formulaire et ne saisit que des valeurs de test. Vous validez le Flow en un clic.
- **Rejoué après chaque mise à jour.** Quand WordPress, une extension ou un thème est mis à jour, l'extension demande à Pathly de rejouer le Flow. Une mise à jour qui casse le tunnel se voit en quelques minutes.
- **Encart dans le tableau de bord.** État du Ping et du Flow, date et capture du dernier échec, votre offre et vos quotas, et un lien vers Pathly.
- **Badge de statut, éteint par défaut.** Allumez-le, et le code court `[pathly_badge]` ou le bloc Badge Pathly affiche l'état de votre site.

Les alertes (e-mail, Slack, Teams, webhooks…), le rapport PDF mensuel et l'historique sont dans Pathly.

L'offre Solo, gratuite, sans carte et sans limite de durée, comprend un Flow et cinq Ping. Les offres payantes ajoutent des Flow, des intervalles plus courts et le travail en équipe : voir les [tarifs](https://pathlyhq.com/fr/pricing).

### Service externe : Pathly

Cette extension est un client du service Pathly (https://pathlyhq.com), édité par Elerine. Elle ne fait rien tant qu'un administrateur n'a pas enregistré de clé d'API Pathly dans Réglages, Pathly.

Une fois la clé enregistrée, l'extension n'appelle que `https://api.pathlyhq.com` et envoie :

- l'adresse de votre site (page d'accueil), pour créer ou retrouver le Ping et préparer le Flow ;
- les versions de WordPress et de l'extension, dans l'en-tête User-Agent ;
- votre clé d'API, dans l'en-tête Authorization.

Ces appels partent quand vous vous connectez, quand vous demandez à Lumi de préparer le Flow, quand vous le validez, quand vous allumez ou éteignez le badge, deux minutes après une mise à jour (pour rejouer le Flow), et au plus toutes les cinq minutes quand un administrateur ouvre le tableau de bord.

L'extension n'envoie aucune donnée de client, de commande, de visiteur ou d'utilisateur.

Pathly visite ensuite votre site depuis ses sondes, comme un visiteur. Sur un site que Lumi ne reconnaît pas, une IA en UE (Vertex AI, europe-west1) lit la structure et le texte de la page, jamais les captures, sans entraînement. Pathly tourne sur Google Cloud, en europe-west1 (Belgique).

Badge allumé : le navigateur de vos visiteurs charge l'image du badge depuis les serveurs de Pathly.

- Conditions générales : https://pathlyhq.com/fr/legal/cgu
- Politique de confidentialité : https://pathlyhq.com/fr/legal/privacy
- Accord de traitement des données (DPA) : https://pathlyhq.com/fr/legal/dpa

### Paniers de test

Sur une boutique, un Flow ajoute un produit à un panier à chaque passage, avec des valeurs de test seulement (test@example.com, « Pathly Test »). Les outils de panier abandonné peuvent compter ces paniers ou envoyer des relances : excluez cette adresse et ce nom. Un Flow ne paie jamais et n'envoie jamais de commande ni de formulaire.

## FAQ

**Est-ce gratuit ?**
L'extension est gratuite. L'offre Solo de Pathly, gratuite et sans carte, couvre un site avec son Flow. Les offres payantes servent à surveiller plus de sites, plus de Flow, plus souvent.

**L'extension ralentit-elle mon site ?**
Non. Elle ne fait rien sur les pages publiques, sauf afficher le badge si vous l'avez allumé. Les vérifications partent des serveurs de Pathly. Le rejeu après une mise à jour passe par WP-Cron et ne ralentit pas la mise à jour.

**Le Flow va-t-il acheter quelque chose ou envoyer mon formulaire ?**
Non. Lumi s'arrête avant le paiement et avant l'envoi d'un formulaire, et ne saisit que des valeurs de test. Chaque Flow est rejoué une fois avant de vous être montré, et c'est vous qui le validez.

**Où est stockée ma clé d'API ?**
Dans la base de WordPress, chiffrée avec un secret tiré des clés de votre fichier wp-config.php. Elle n'est jamais réaffichée. Vous pouvez la révoquer dans Pathly à tout moment.

**Que se passe-t-il si je déconnecte ou supprime l'extension ?**
L'extension oublie la clé et les identifiants. La supprimer efface toutes ses options et tâches planifiées. Votre Ping, votre Flow et l'historique restent dans votre compte Pathly, où vous pouvez les supprimer.

**Ça marche sans WooCommerce ?**
Oui. Sans WooCommerce, Lumi prépare un Flow sur votre formulaire de contact, ou sur votre page de connexion.

**Ça marche sur un réseau multisite ?**
Oui, site par site : chaque site se connecte avec sa propre clé. Supprimer l'extension nettoie tous les sites du réseau.

## Légendes des captures (`captures/fr/`)

1. Réglages, Pathly : collez votre clé d'API ou créez un compte gratuit.
2. Lumi propose un Flow pour votre tunnel de commande : validez-le en un clic.
3. L'encart Pathly du tableau de bord : état du Ping et du Flow, dernier échec avec sa capture, offre et quotas.
4. Le badge de statut, éteint par défaut, ici allumé : ajoutez-le avec le code court `[pathly_badge]` ou le bloc Badge Pathly.

## Journal des changements

**0.1.0**
- Première version : clé d'API, Ping posé pour vous, Flow préparé par Lumi, rejeu après les mises à jour, encart du tableau de bord, badge de statut (code court et bloc).
