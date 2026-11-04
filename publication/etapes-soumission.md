# Publier l'extension sur WordPress.org : étapes pour Simon

Ce que Claude n'a pas fait et ne fera pas : créer le compte, accepter des conditions, soumettre l'extension, répondre aux relecteurs. Tout ce qui suit est à faire par Simon.

Frais : **0 €**. Pas de commission. L'annuaire WordPress.org est gratuit.

## Où on en est (04/11/2026)

- Compte WordPress.org `pathly` créé, extension soumise le 03/11/2026 (e-mail « Successful Plugin Submission - Pathly » reçu sur `simon@pathlyhq.com`). Adresse réservée : `https://wordpress.org/plugins/pathly/` (slug `pathly`).
- La revue humaine n'a pas encore commencé. Tant qu'elle n'a pas commencé, la page de soumission permet d'**envoyer une nouvelle version** de l'archive : c'est ce qu'il faut faire avec la 0.2.1.
- Le détail des champs et des cases est dans `champs-wordpress-org.md`.

## Avant d'envoyer la 0.2.1

1. Les API de #662 sont en production (`/v1/account`, `/v1/flow-assistant/*` par clé, `/v1/status-pages`, `screenshotUrl` dans `/v1/runs/{id}`). Sans elles, le relecteur verrait des erreurs en essayant l'extension.
2. Vérifier à la main, sur un WordPress en ligne, avec une vraie clé de production limitée à un Espace : connexion, Ping créé, Lumi, encart, badge. Un site wp-env sur `localhost` n'est pas joignable par Pathly : le Ping et Lumi y répondent « site injoignable ».
3. Archive : `C:\exploit\soumissions-modules\wordpress-pathly-0.2.1.zip`. Elle est identique à ce que produira la PR de version de release-please (0.2.1) une fois la branche `feat/0.4.3-marketplaces` fusionnée.

## Revue

- Un relecteur bénévole teste l'extension et écrit à `simon@pathlyhq.com`. Délai annoncé par WordPress.org : 1 à 10 jours, objectif 5 jours ouvrés. Aucune priorité possible.
- Ajouter `plugins@wordpress.org` aux contacts. Pas de réponse automatique sur la boîte, sinon la revue s'arrête.
- Répondre par e-mail, en anglais, en répondant au message de revue. Chaque correction demandée se fait dans ce dépôt, passe la CI, puis on renvoie la nouvelle archive.
- Points qu'ils regardent souvent, déjà traités : section « External service » avec les liens CGU et confidentialité (règle 6), rien n'est envoyé avant la configuration (règle 7), pas de lien ou de crédit imposé sur le site public (badge éteint par défaut, règle 10), pas de pub ni de relance insistante dans l'administration (règle 11), échappement des sorties, nonces et droits sur chaque formulaire, `exit` après chaque redirection, préfixe `pathly`.
- Plugin Check (version du 04/11/2026) ne signale aucune erreur et un seul avertissement, laissé exprès : `load_plugin_textdomain()`. Il charge la traduction française livrée avec l'extension, tant que translate.wordpress.org ne l'a pas. Si le relecteur demande de le retirer, on le retire et la traduction passe par translate.wordpress.org (voir plus bas).

## Après l'approbation

1. WordPress.org envoie l'accès SVN de `https://plugins.svn.wordpress.org/pathly/`. Créer le mot de passe SVN dans le profil (Account & Security). C'est un mot de passe distinct de celui du compte.
2. Dans le dépôt GitHub `Pathly-Monitoring/pathly-wordpress`, Settings, Secrets and variables, Actions :
   - secrets `SVN_USERNAME` (`pathly`) et `SVN_PASSWORD` (mot de passe SVN) ;
   - variable `WPORG_DEPLOY` = `true`.
3. Rendre le dépôt public, si c'est toujours le plan.
4. Fusionner la PR de version ouverte par release-please (« chore(main): release 0.2.1 »). La release GitHub déclenche le workflow « Publication WordPress.org », qui envoie l'extension dans `trunk`, crée le tag `0.2.1` et envoie les captures, l'icône et la bannière de `.wordpress-org/` dans `assets/`.
5. Vérifier la fiche : https://wordpress.org/plugins/pathly/

## Journal des changements du readme

Il reste en anglais. Les notes de la prochaine version s'écrivent à la main sous `= Unreleased =` dans `readme.txt`. La PR de version de release-please renomme ce titre au numéro de version (`bin/sync-version.js`). Les messages de commit, en français, n'y sont plus copiés.

## Fiche en français

- Sur https://translate.wordpress.org/projects/wp-plugins/pathly/ , demander le rôle de traducteur validé (PTE) pour le français, en tant qu'auteur.
- Importer `languages/pathly-fr_FR.po` dans le projet « Stable », et coller les textes de `publication/fiche-fr.md` dans le projet « Readme ».

## Ce qu'il ne faut pas faire

- Pas de second compte, pas d'avis demandé à des proches ou à des clients, pas de note sollicitée.
- Pas de lien `ref`, de programme partenaire ni de code promo dans la fiche.
