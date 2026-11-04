# WordPress.org : ce qu'il faut remplir, et où

Pour Simon. Sur WordPress.org, la fiche publique n'est pas un formulaire : elle est lue dans `readme.txt` (titre, résumé, description, FAQ, légendes des captures, journal) et dans le dossier `assets/` du SVN (captures, icône, bannière). Le seul formulaire est celui de la soumission. Tous les textes sont déjà en anglais dans l'archive.

## 1. Envoyer la 0.2.1 sur la soumission en attente

Page : https://wordpress.org/plugins/developers/add/ , connecté avec le compte `pathly`.

| Élément de la page | Quoi faire |
|---|---|
| Soumission en attente « Pathly » | Elle doit apparaître, avec l'adresse `https://wordpress.org/plugins/pathly/`. Vérifier que le slug est bien `pathly`. Ne pas le changer. |
| Upload a new version (ou bouton équivalent sur la soumission en attente) | Envoyer `C:\exploit\soumissions-modules\wordpress-pathly-0.2.1.zip` (moins de 10 Mo : 118 ko). Possible seulement tant que la revue n'a pas commencé. |
| Cases à cocher | Les lire avant de cocher. Elles portent sur le respect des règles de l'annuaire, la licence GPL de l'extension et de ce qu'elle contient, et le droit de la publier. L'extension est en GPL-2.0-or-later et n'embarque aucune bibliothèque tierce. |

Si la page ne propose plus d'envoyer une nouvelle version (revue commencée), ne pas soumettre une seconde extension : répondre au message de revue en joignant le lien de l'archive, ou attendre les remarques du relecteur.

## 2. Ce que la fiche affichera (rien à saisir : lu dans `readme.txt`)

| Élément de la fiche | Valeur |
|---|---|
| Nom | Pathly – Uptime and Checkout Monitoring |
| Contributeur | `pathly` (doit être le nom du compte WordPress.org) |
| Étiquettes (5 au plus) | uptime, monitoring, woocommerce, checkout, status page |
| Requires at least / Tested up to / Requires PHP | 6.4 / 7.1 (dernière version : 7.1.4 au 04/11/2026) / 7.4 |
| Stable tag | 0.2.1 |
| Licence | GPLv2 or later |
| Résumé (150 caractères au plus) | Know that your checkout, contact form or login still works. A Ping on your site, a Flow in a real browser, replayed after each update. |
| Description, service externe, FAQ, journal | Sections de `readme.txt` |
| Légendes des captures | Section « Screenshots » de `readme.txt`, 4 légendes pour 4 captures |

## 3. Images (envoyées par le workflow après l'approbation, dans `assets/` du SVN)

| Image | Fichier | Taille demandée par WordPress.org |
|---|---|---|
| Icône | `.wordpress-org/icon-128x128.png`, `icon-256x256.png`, `icon.svg` | 128 × 128 et 256 × 256 |
| Bannière | `.wordpress-org/banner-772x250.png`, `banner-1544x500.png` | 772 × 250 et 1544 × 500 |
| Captures | `.wordpress-org/screenshot-1.png` à `screenshot-4.png` | Libre, numérotées comme les légendes |

Copie des mêmes images dans `C:\exploit\soumissions-modules\wordpress-0.2.1\` pour les avoir sous la main.

## 4. Si le relecteur pose une question

Répondre en anglais, court, en réponse à son e-mail. Modèle pour la question la plus probable :

```
Thank you for the review.

load_plugin_textdomain() loads the French translation shipped in /languages until it is available on translate.wordpress.org. If you prefer, we remove it and rely on language packs only.

The plugin calls only https://api.pathlyhq.com, and only after an administrator saves an API key. The external service, the data sent and the links to the terms and privacy policy are described in the "External service: Pathly" section of readme.txt.
```
