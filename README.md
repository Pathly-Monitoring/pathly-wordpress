# Pathly for WordPress and WooCommerce

[Français](README.fr.md)

WordPress plugin for [Pathly](https://pathlyhq.com). *Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold.*

The plugin is a client of the public Pathly API (`https://api.pathlyhq.com/v1`):

- **Settings, Pathly** (administrators only): paste an API key, or create a free account.
- **Ping set up for you** on the home page as soon as the key is valid. An existing Ping on the same address is reused, never duplicated.
- **Flow prepared by Lumi**, the Pathly assistant: checkout up to the payment page with WooCommerce, otherwise contact form or login. The administrator validates it in one click.
- **Replay after updates**: `upgrader_process_complete` (WordPress, plugin or theme update) queues `POST /v1/scenarios/{id}/run` two minutes later with WP-Cron.
- **Dashboard box**: Ping and Flow status, date and screenshot of the last failure, plan and quotas, link to Pathly.
- **Status badge**, off by default: `[pathly_badge]` shortcode and `pathly/badge` block.

Requirements: WordPress 6.4+, PHP 7.4+. WooCommerce is optional.

## Data and security

- Nothing is sent before an administrator saves a key. Then only the site address and the WordPress and plugin versions go to `api.pathlyhq.com`. No customer, order or visitor data.
- The plugin only calls `https://api.pathlyhq.com` (`includes/class-pathly-http.php`). It never requests an address typed by a user.
- The key is stored encrypted (libsodium, secret derived from the `wp-config.php` salts), in a non-autoloaded option, and never printed back.
- Every form checks `manage_options` and a nonce.
- Deleting the plugin removes its options, transients and scheduled events, on every site of a network.

**Test carts.** A Flow on a shop adds a product to a cart at each run, with test values only (`test@example.com`, "Pathly Test"). Abandoned cart tools may count these carts or send reminders for them: exclude this address and this name.

## API routes used

| Route | Scope |
| --- | --- |
| `GET /v1/scenarios`, `GET /v1/scenarios/{id}`, `POST /v1/scenarios` | `scenarios:read`, `scenarios:write` |
| `POST /v1/scenarios/{id}/run` | `runs:trigger` |
| `GET /v1/runs`, `GET /v1/runs/{id}` (with `screenshotUrl`) | `runs:read` |
| `GET /v1/account` | `org:read` |
| `POST /v1/flow-assistant/detect`, `POST /v1/flow-assistant/trials`, `GET /v1/flow-assistant/trials/{id}` | `assistant:run` |
| `POST /v1/flow-assistant/trials/{id}/save` | `assistant:save` |
| `POST /v1/status-pages`, `PATCH /v1/status-pages/{id}` | `status:read`, `status:write` |

Recommended key: limited to one Space, with exactly these scopes.

## Development

```bash
composer install            # PHPUnit, Brain Monkey, PHPCS (WordPress Coding Standards, PHPCompatibilityWP)
composer lint               # phpcs
composer format             # phpcbf
composer test               # unit tests
composer coverage           # unit tests with Xdebug, fails under 100 % of lines or branches

npm ci
npm run test:integration    # wp-env (Docker): install, configure, update hook, badge, Plugin Check, uninstall
node tests/integration/captures.mjs   # screenshots, icon and banners (wp-env running)
bash bin/build.sh           # dist/pathly.zip
```

The integration environment answers the Pathly API with `tests/integration/pathly-api-mock.php` (constant `PATHLY_API_MOCK` in `.wp-env.json`). To try the plugin against the real API, set `PATHLY_API_MOCK` to `false` in a `.wp-env.override.json`.

Translations: `languages/pathly.pot` is generated with `wp i18n make-pot`. French strings live in `bin/i18n/fr_FR.json`, and `node bin/make-po.js fr_FR` writes the `.po` file.

Releases: [release-please](https://github.com/googleapis/release-please) keeps a release PR up to date, and `bin/sync-version.js` copies the version into the plugin header, the readme and the block. Publishing a GitHub release deploys to WordPress.org once the `WPORG_DEPLOY` variable is set.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
