# Pathly pour WordPress et WooCommerce

[English](README.md)

Extension WordPress de [Pathly](https://pathlyhq.com). *Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold.*

L'extension est un client de l'API publique de Pathly (`https://api.pathlyhq.com/v1`) :

- **Réglages, Pathly** (administrateurs seulement) : coller une clé d'API, ou créer un compte gratuit.
- **Ping posé tout seul** sur la page d'accueil dès que la clé est valide. Un Ping qui existe déjà sur la même adresse est repris, jamais doublé.
- **Flow préparé par Lumi**, l'assistant de Pathly : le tunnel de commande jusqu'à la page de paiement avec WooCommerce, sinon le formulaire de contact ou la connexion. L'administrateur le valide en un clic.
- **Rejeu après une mise à jour** : `upgrader_process_complete` (mise à jour de WordPress, d'une extension ou d'un thème) programme `POST /v1/scenarios/{id}/run` deux minutes plus tard avec WP-Cron.
- **Encart du tableau de bord** : état du Ping et du Flow, date et capture du dernier échec, offre et quotas, lien vers Pathly.
- **Badge de statut**, éteint par défaut : code court `[pathly_badge]` et bloc `pathly/badge`.

Prérequis : WordPress 6.4 ou plus, PHP 7.4 ou plus. WooCommerce est facultatif.

## Données et sécurité

- Rien ne part avant qu'un administrateur enregistre une clé. Ensuite, seules l'adresse du site et les versions de WordPress et de l'extension partent vers `api.pathlyhq.com`. Aucune donnée de client, de commande ou de visiteur.
- L'extension n'appelle que `https://api.pathlyhq.com` (`includes/class-pathly-http.php`). Elle n'appelle jamais une adresse saisie par un utilisateur.
- La clé est chiffrée (libsodium, secret tiré des clés de `wp-config.php`), dans une option non chargée d'office, et n'est jamais réaffichée.
- Chaque formulaire vérifie `manage_options` et un nonce.
- Supprimer l'extension efface ses options, ses transients et ses tâches planifiées, sur chaque site d'un réseau.

**Paniers de test.** Sur une boutique, un Flow ajoute un produit à un panier à chaque passage, avec des valeurs de test seulement (`test@example.com`, « Pathly Test »). Les outils de panier abandonné peuvent compter ces paniers ou envoyer des relances : excluez cette adresse et ce nom.

## Développement

Voir la section « Development » du [README anglais](README.md#development) : les commandes sont les mêmes.

## Licence

GPL-2.0-or-later. Voir [LICENSE](LICENSE).
