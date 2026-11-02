#!/usr/bin/env bash
# Integration test against a real WordPress in Docker (wp-env), with the Pathly
# API simulated by tests/integration/pathly-api-mock.php.
#
# Install, activate, configure through the real admin forms, prepare the Flow,
# show the dashboard box, run a real plugin update to fire
# upgrader_process_complete, turn the badge on and off, run Plugin Check,
# then uninstall (files kept) and check that nothing is left.
#
# Usage: bash tests/integration/run.sh [--keep]   (--keep leaves WordPress running)
set -euo pipefail
cd "$(dirname "$0")/../.."

BASE="http://localhost:${WP_ENV_PORT:-8890}"
JAR="$(mktemp)"
PAGE="$(mktemp)"
JUNK="$(mktemp)"
trap 'rm -f "$JAR" "$PAGE" "$JUNK"' EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { printf 'ÉCHEC : %s\n' "$1" >&2; exit 1; }
wp() { MSYS_NO_PATHCONV=1 npx wp-env run cli wp "$@" 2>/dev/null | tr -d '\r'; }
calls() { wp option get pathly_mock_calls --format=json || echo '[]'; }
# Prints how many recorded calls match "METHOD /path-prefix".
count() {
	calls | node -e '
		let s = ""; process.stdin.on("data", d => s += d).on("end", () => {
			const [m, p] = process.argv[1].split(" ");
			const list = JSON.parse(s || "[]");
			console.log(list.filter(c => c.method === m && c.path.startsWith(p)).length);
		});' "$1"
}
nonce() {
	{ grep -o "value=\"pathly_$1\"><input type=\"hidden\" id=\"_wpnonce\" name=\"_wpnonce\" value=\"[a-f0-9]*\"" "$PAGE" || true; } | grep -o '[a-f0-9]*"$' | tr -d '"' | head -1 || true
}
settings() { curl -fsS -b "$JAR" -c "$JAR" "$BASE/wp-admin/options-general.php?page=pathly" -o "$PAGE"; }
post() {
	local action="$1"; shift
	settings
	local n; n="$(nonce "$action")"
	[ -n "$n" ] || fail "no nonce for pathly_$action on the settings screen"
	curl -fsS -b "$JAR" -c "$JAR" -o "$JUNK" "$BASE/wp-admin/admin-post.php" \
		--data-urlencode "action=pathly_$action" --data-urlencode "_wpnonce=$n" "$@"
	settings
}
contains() { grep -q -- "$1" "$PAGE" || fail "$2"; }

step "Build and start WordPress"
bash bin/build.sh >/dev/null
npx wp-env start >/dev/null
wp plugin deactivate pathly >/dev/null || true
wp option delete pathly_api_key pathly_state pathly_mock_calls >/dev/null || true
wp plugin delete hello-dolly hello >/dev/null || true
echo "WordPress $(wp core version), PHP $(MSYS_NO_PATHCONV=1 npx wp-env run cli php -r 'echo PHP_VERSION;' 2>/dev/null)"

step "Activate: nothing is sent to Pathly before a key is set (rule 7)"
wp plugin activate pathly >/dev/null
curl -fsS "$BASE/" -o "$JUNK"
curl -fsS -c "$JAR" -b "wordpress_test_cookie=WP%20Cookie%20check" -o "$JUNK" \
	--data-urlencode "log=admin" --data-urlencode "pwd=password" \
	--data-urlencode "testcookie=1" --data-urlencode "redirect_to=$BASE/wp-admin/" "$BASE/wp-login.php"
curl -fsS -b "$JAR" "$BASE/wp-admin/" -o "$PAGE"
contains "Connect your site to Pathly" "dashboard box without key"
settings
contains 'name="pathly_api_key"' "key field"
contains "ref=wordpress" "sign-up link"
contains "legal/privacy" "privacy link"
[ "$(calls)" = "[]" ] || fail "calls sent before configuration: $(calls)"

step "A refused key is not saved"
post connect --data-urlencode "pathly_api_key=sp_refused_0123456789abcdef"
contains "notice-error" "error notice"
wp option get pathly_api_key >/dev/null && fail "refused key saved"

step "Connect: the Ping is created on the home page, the key is stored encrypted"
post connect --data-urlencode "pathly_api_key=sp_live_0123456789abcdef"
contains "notice-success" "success notice"
grep -q "sp_live_0123456789abcdef" "$PAGE" && fail "key printed back"
wp option get pathly_api_key | grep -q "sp_live" && fail "key stored in clear"
[ "$(count 'POST /v1/scenarios')" = "1" ] || fail "Ping not created once"
calls | node -e '
	let s = ""; process.stdin.on("data", d => s += d).on("end", () => {
		const list = JSON.parse(s);
		const ping = list.find(c => c.method === "POST" && c.path === "/v1/scenarios");
		if (!ping.body.url.startsWith("http://localhost")) throw new Error("Ping URL " + ping.body.url);
		if (!ping.idem.startsWith("wp-ping-")) throw new Error("no idempotency key");
		if (!list.every(c => c.bearer && /^Pathly-WordPress\/\S+ WordPress\/\S+$/.test(c.user_agent)))
			throw new Error("auth header or user agent");
		const keys = new Set(list.flatMap(c => Object.keys(c.body || {})));
		const allowed = ["name", "type", "url", "intervalSec", "tags"];
		for (const k of keys) if (!allowed.includes(k)) throw new Error("unexpected field " + k);
	});'

step "Lumi prepares the Flow, the administrator validates it"
post flow_start
contains "Validate this Flow" "proposal"
contains "Add to cart, up to the payment page" "cart proposal"
post flow_save --data-urlencode "pathly_index=0"
contains "Your Flow is saved" "saved Flow"
[ "$(wp eval 'echo get_option("pathly_state")["flow_id"];')" = "flow-1" ] || fail "flow_id"

step "Dashboard box: statuses, last failure with screenshot, plan"
wp transient delete pathly_dashboard >/dev/null || true
curl -fsS -b "$JAR" "$BASE/wp-admin/" -o "$PAGE"
contains "pathly-pill--ok" "Ping OK"
contains "pathly-pill--fail" "Flow failing"
contains "storage.example/run-1.png" "screenshot"
contains "Plan: Pro" "plan"
contains "Flow: 1 / unlimited" "unlimited quota"

step "A real plugin update fires upgrader_process_complete and replays the Flow"
wp plugin install https://downloads.wordpress.org/plugin/hello-dolly.1.6.zip --force >/dev/null
wp plugin update hello-dolly >/dev/null
wp cron event list --fields=hook --format=csv | grep -q pathly_replay_flow || fail "replay not scheduled"
wp cron event run pathly_replay_flow >/dev/null
[ "$(count 'POST /v1/scenarios/flow-1/run')" = "1" ] || fail "Flow not replayed"
wp plugin delete hello-dolly >/dev/null || true

step "Badge: off by default, then on, then off"
[ -z "$(wp eval 'echo do_shortcode("[pathly_badge]");')" ] || fail "badge shown while off"
[ "$(wp eval 'echo WP_Block_Type_Registry::get_instance()->is_registered("pathly/badge") ? "yes" : "no";')" = "yes" ] || fail "block"
post badge --data-urlencode "pathly_badge=1"
wp eval 'echo do_shortcode("[pathly_badge]");' | grep -q "test-shop/badge.svg" || fail "badge not shown once on"
post badge --data-urlencode "pathly_badge=0"
[ -z "$(wp eval 'echo do_shortcode("[pathly_badge]");')" ] || fail "badge shown after off"
[ "$(count 'PATCH /v1/status-pages/page-1')" = "1" ] || fail "status page not unpublished"

step "Plugin Check"
wp plugin install plugin-check --activate >/dev/null
wp plugin check pathly --format=csv --fields=type,code,message,file,line > "$PAGE" || true
cat "$PAGE"
grep -q '^ERROR' "$PAGE" && fail "Plugin Check reports errors"
wp plugin deactivate plugin-check >/dev/null

step "Uninstall: options, transients and scheduled events are removed"
wp eval 'wp_schedule_single_event( time() + 600, "pathly_replay_flow" );'
wp plugin deactivate pathly >/dev/null
wp plugin uninstall pathly --skip-delete >/dev/null
wp option get pathly_api_key >/dev/null && fail "key left"
wp option get pathly_state >/dev/null && fail "state left"
wp cron event list --fields=hook --format=csv | grep -q pathly_replay_flow && fail "event left"
wp option delete pathly_mock_calls >/dev/null || true

if [ "${1:-}" != "--keep" ]; then
	npx wp-env stop >/dev/null
fi
printf '\nIntegration tests passed.\n'
