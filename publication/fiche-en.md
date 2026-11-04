# WordPress.org listing, in English

The source of the listing is `readme.txt` at the root of the repository: WordPress.org reads its title, short description, description, FAQ, screenshot captions and changelog. This file sums up what to check before submitting.

- **Title**: Pathly – Uptime and Checkout Monitoring
- **Plugin name in the header** (`pathly.php`): the same as the title. The slug `pathly`, which matches the text domain, was reserved at the first submission (03/11/2026), when the header still said "Pathly".
- **Short description** (150 characters max): Know that your checkout, contact form or login still works. A Ping on your site, a Flow in a real browser, replayed after each update.
- **Tags** (5 max): uptime, monitoring, woocommerce, checkout, status page
- **Description, external service section, FAQ, changelog**: see `readme.txt`. Validate it with https://wordpress.org/plugins/developers/readme-validator/ after any change.
- **Screenshots**: `.wordpress-org/screenshot-1.png` to `screenshot-4.png`, captions in the `== Screenshots ==` section of `readme.txt`.
- **Icon**: `.wordpress-org/icon-128x128.png`, `icon-256x256.png`, `icon.svg`.
- **Banner**: `.wordpress-org/banner-772x250.png`, `banner-1544x500.png`.

These assets go to the `assets/` folder of the SVN repository, not into the plugin zip. The `Publication WordPress.org` workflow sends them with each release.
