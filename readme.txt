=== Pathly – Uptime and Checkout Monitoring ===
Contributors: pathly
Tags: uptime, monitoring, woocommerce, checkout, status page
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Know that your checkout, contact form or login still works. A Ping on your site, a Flow in a real browser, replayed after each update.

== Description ==

Your server answers, the home page loads… and the checkout is broken since the last plugin update. Uptime checks do not see it. Your customers do.

Pathly watches the path of your visitors:

* **Ping**: does the site answer?
* **Flow**: can a visitor walk the path to the end? A real browser adds a product to the cart and reaches the payment page, or fills your contact form and stops before sending, or logs in.
* **Chain**: do the services behind the site hold? (set up in Pathly)

This plugin connects your WordPress or WooCommerce site to your Pathly account:

* **Ping set up for you.** As soon as your API key is saved, Pathly checks your home page every five minutes. If a Ping already exists on this address, the plugin uses it: no duplicate.
* **Flow prepared by Lumi.** Lumi, the Pathly assistant, recognizes WooCommerce and WordPress and prepares a Flow: the checkout up to the payment page with WooCommerce, otherwise your contact form or your login. Each proposed Flow is replayed before it is shown to you. Lumi pays nothing, sends no form and only types test values. You validate the Flow in one click.
* **Replayed after each update.** When WordPress, a plugin or a theme is updated, the plugin asks Pathly to replay the Flow. An update that breaks the checkout is seen in minutes.
* **Dashboard box.** Status of the Ping and the Flow, date and screenshot of the last failure, your plan and quotas, and a link to Pathly.
* **Status badge, off by default.** Turn it on, and the `[pathly_badge]` shortcode or the Pathly badge block shows the status of your site.

Alerts (email, Slack, Teams, webhooks…), the monthly PDF report and the history live in Pathly.

The free Solo plan, without a card and without a time limit, includes one Flow and five Ping. Paid plans add more Flow, shorter intervals and team features: see [pricing](https://pathlyhq.com/en/pricing).

= External service: Pathly =

This plugin is a client of the Pathly service (https://pathlyhq.com), operated by Elerine. It does nothing until an administrator saves a Pathly API key in Settings, Pathly.

Once a key is saved, the plugin calls `https://api.pathlyhq.com` only, and sends:

* the address of your site (home page), to create or find the Ping and to prepare the Flow;
* the versions of WordPress and of the plugin, in the User-Agent header;
* your API key, in the Authorization header.

It sends these calls when you connect, when you ask Lumi to prepare the Flow, when you validate it, when you turn the badge on or off, two minutes after an update of WordPress, a plugin or a theme (to replay the Flow), and at most every five minutes when an administrator opens the dashboard (to show the statuses).

No customer, order, visitor or user data is sent by the plugin.

Pathly then visits your site from its probes, like a visitor would: the Ping requests the home page, the Flow walks the path in a real browser. On a site Lumi does not recognize, an AI hosted in the EU (Vertex AI, europe-west1) reads the structure and text of the page, never the screenshots, without training. Pathly runs on Google Cloud in europe-west1 (Belgium).

When the badge is on, your visitors' browsers load the badge image from Pathly servers.

When the Flow last failed, the dashboard box shows the screenshot of the failed step: the administrator's browser loads this image from the address given by the Pathly API. It is a screenshot of your own public pages, taken by the Pathly probe.

* Terms of service: https://pathlyhq.com/en/legal/cgu
* Privacy policy: https://pathlyhq.com/en/legal/privacy
* Data processing agreement: https://pathlyhq.com/en/legal/dpa

= Test carts =

A Flow on a shop adds a product to a cart at each run, with test values only (test@example.com, "Pathly Test"). Abandoned cart tools may count these carts or send reminders for them: exclude this address and this name from them. A Flow never pays and never submits an order or a form.

== Installation ==

1. Install and activate the plugin.
2. Open Settings, Pathly.
3. No account yet? Click "Create a free account". Then, in Pathly, open Settings, Integrations and create an API key. Prefer a key limited to one Space, with the scopes `org:read`, `scenarios:read`, `scenarios:write`, `runs:read`, `runs:trigger`, `assistant:run`, `assistant:save`, `status:read` and `status:write`.
4. Paste the key and click Connect. The Ping is created.
5. Click "Ask Lumi to prepare the Flow", wait one to three minutes, and validate the proposed Flow.

The site must be reachable from the internet: a site in maintenance mode, behind a password or on a local address cannot be watched.

== Frequently Asked Questions ==

= Is it free? =

The plugin is free. The free Solo plan of Pathly, without a card, covers one site with its Flow. Paid plans are for more sites, more Flow and shorter intervals.

= Does the plugin slow my site down? =

No. The plugin does nothing on the public pages, except printing the badge if you turned it on. The checks run from Pathly servers. The replay after an update is queued with WP-Cron, so the update itself is not slowed down.

= Will the Flow buy something or send my contact form? =

No. Lumi stops before payment and before sending a form, and only types test values. Each Flow is replayed once before you see it, and you validate it.

= Where is my API key stored? =

In the WordPress database, encrypted with a secret derived from the keys of your wp-config.php file. It is never printed back once saved. Revoke it in Pathly at any time.

= What happens when I disconnect or delete the plugin? =

The plugin forgets the key and the identifiers, and deleting it removes all its options and scheduled events. Your Ping, Flow and history stay in your Pathly account, where you can delete them.

= Does it work without WooCommerce? =

Yes. Without WooCommerce, Lumi prepares a Flow on your contact form, or on your login page.

= Does it work on a multisite network? =

Yes, site by site: each site connects with its own key. Deleting the plugin cleans every site of the network.

== Screenshots ==

1. Settings, Pathly: paste your API key or create a free account.
2. Lumi proposes a Flow for your checkout: validate it in one click.
3. The Pathly box on the dashboard: Ping and Flow statuses, last failure with its screenshot, plan and quotas.
4. The status badge, off by default, here turned on: add it with the `[pathly_badge]` shortcode or the Pathly badge block.

== Changelog ==

= Unreleased =
* The settings forms end the request right after their redirection.
* The readme describes the screenshot of the last failure, loaded from Pathly in the dashboard box.

= 0.2.0 =
* Plugin for WordPress and WooCommerce: API key, Ping set up for you, Flow prepared by Lumi, replay after updates, dashboard box, status badge (shortcode and block).
* The port of the site address is taken into account to find its Ping and its Flow.

= 0.1.0 =
* First version.

== Upgrade Notice ==

= 0.2.0 =
First public version.
