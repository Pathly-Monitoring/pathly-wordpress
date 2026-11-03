# Publier l'extension sur WordPress.org : étapes pour Simon

Ce que Claude n'a pas fait et ne fera pas : créer le compte, accepter des conditions, soumettre l'extension, répondre aux relecteurs. Tout ce qui suit est à faire par Simon.

Frais : **0 €**. Pas de commission. L'annuaire WordPress.org est gratuit.

## Avant de soumettre

1. Les API de #662 sont en production (`/v1/account`, `/v1/flow-assistant/*` par clé, `/v1/status-pages`, `screenshotUrl` dans `/v1/runs/{id}`). Sans elles, le relecteur verrait des erreurs en essayant l'extension.
2. Vérifier à la main, sur un WordPress de test, avec une vraie clé de production limitée à un Espace : connexion, Ping créé, Lumi, encart, badge. Commande : `npx wp-env start` avec un fichier `.wp-env.override.json` qui contient `{ "config": { "PATHLY_API_MOCK": false } }`. Attention : un site wp-env sur `localhost` n'est pas joignable par Pathly. Le Ping et Lumi répondront « site injoignable ». Pour un essai complet, il faut un WordPress en ligne.
3. Prendre la dernière archive : onglet Actions du dépôt, dernier passage vert de la CI, artefact `pathly-plugin` (`pathly.zip`). Ou en local : `bash bin/build.sh`.

## 1. Créer le compte WordPress.org

- Adresse : https://login.wordpress.org/register
- E-mail : `simon@pathlyhq.com`. Nom d'utilisateur retenu : `pathly`. **Il doit être identique** à la ligne `Contributors:` de `readme.txt`. Sinon, corriger cette ligne avant de soumettre.
- Activer la double authentification dans le profil (Account & Security). WordPress.org l'exige pour les auteurs d'extensions (non vérifié pour la date exacte de cette règle).
- Un seul compte, au nom de Simon (règle `legal-comptes.md`).

## 2. Soumettre l'extension

- Adresse : https://wordpress.org/plugins/developers/add/
- Envoyer `pathly.zip` (moins de 10 Mo).
- Cocher les cases : l'extension respecte les règles de l'annuaire, a le droit d'être publiée en GPL, et son auteur accepte les conditions. Les lire avant de cocher.
- Le formulaire affiche le **slug** qui sera réservé. Il doit être `pathly` (tiré du nom `Pathly` de l'en-tête de `pathly.php`). S'il est différent, ne pas soumettre et me prévenir : le domaine de traduction et le workflow de publication utilisent `pathly`.

## 3. Revue

- Un relecteur bénévole teste l'extension et écrit à `simon@pathlyhq.com`. La file d'attente est affichée sur la page de soumission. Délai constaté par d'autres éditeurs : de quelques jours à plusieurs semaines (non vérifié).
- Répondre par e-mail, en anglais, sans relancer avant la réponse. Chaque correction demandée se fait dans ce dépôt, passe la CI, puis on renvoie la nouvelle archive.
- Points qu'ils regardent souvent, déjà traités : section « External service » avec les liens CGU et confidentialité (règle 6), rien n'est envoyé avant la configuration (règle 7), pas de lien ou de crédit imposé sur le site public (badge éteint par défaut, règle 10), pas de pub ni de relance insistante dans l'administration (règle 11), échappement des sorties, nonces, préfixe `pathly`.
- Plugin Check ne signale aucune erreur et deux avertissements, laissés exprès :
  - `load_plugin_textdomain()` : il livre la traduction française dès le premier jour. Si le relecteur demande de le retirer, on le retire et la traduction passe par translate.wordpress.org ;
  - nom différent entre `readme.txt` (« Pathly – Uptime and Checkout Monitoring », plus parlant dans la recherche) et l'en-tête (« Pathly », qui donne le slug `pathly`). Si le relecteur demande d'aligner, le titre de `readme.txt` devient « Pathly ».

## 4. Après l'approbation

1. WordPress.org envoie l'accès SVN de `https://plugins.svn.wordpress.org/pathly/`. Créer le mot de passe SVN dans le profil (Account & Security). C'est un mot de passe distinct de celui du compte.
2. Dans le dépôt GitHub `Pathly-Monitoring/pathly-wordpress`, Settings, Secrets and variables, Actions :
   - secrets `SVN_USERNAME` (nom d'utilisateur WordPress.org) et `SVN_PASSWORD` (mot de passe SVN) ;
   - variable `WPORG_DEPLOY` = `true`.
3. Rendre le dépôt public, si c'est toujours le plan.
4. Fusionner la PR de version ouverte par release-please (« chore(main): release 0.1.0 »). La release GitHub déclenche le workflow « Publication WordPress.org », qui envoie l'extension dans `trunk`, crée le tag `0.1.0` et envoie les captures, l'icône et la bannière dans `assets/`.
5. Vérifier la fiche : https://wordpress.org/plugins/pathly/

## 5. Fiche en français

- Sur https://translate.wordpress.org/projects/wp-plugins/pathly/ , demander le rôle de traducteur validé (PTE) pour le français, en tant qu'auteur.
- Coller les textes de `publication/fiche-fr.md` dans le projet « Readme », et vérifier les chaînes de l'extension (déjà traduites dans `languages/pathly-fr_FR.po`).

## Ce qu'il ne faut pas faire

- Pas de second compte, pas d'avis demandé à des proches ou à des clients, pas de note sollicitée.
- Pas de lien `ref`, de programme partenaire ni de code promo dans la fiche.
