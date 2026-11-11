# NICA HubSpot Events (WordPress plugin)

Copies this league's events from the HubSpot **Events** object into WordPress.

- Only events whose **League** matches this site's league are copied (a Canada event never reaches the Florida site).
- Each event gets its own page at `/events/event-title/`.
- `[nica_events]` shows the cards. New events appear with no editing.
- No ACF. HubSpot holds every field.
- Updates itself every 15 minutes (WP-Cron). **You never need to press Sync now**; the button is only for testing. n8n can make it instant (section 6).

## 1. HubSpot setup (once)

1. Settings > Integrations > **Service Keys** (may be under Development > Keys). HubSpot no longer lets accounts create new Private Apps. You need Super Admin or developer-tools access.
2. Create a key with **read** access to the Events object, its property definitions (schemas), and files. Pick the custom-object / schema / files read scopes.
3. **Object type ID:** Settings > Data Management > Objects > Events > Properties. The page URL contains `type=2-12345678`. The API name (`p12345678_events`) also works.
4. **Property internal names:** Settings > Data Management > Properties > Events > click a property. The plugin guesses `event_name`, `event_start_date`, `league`, and so on. **Test connection** shows any it can't find; fix them in the property map on the settings page.
5. **League value:** filtering uses the dropdown's *internal value* (often `florida`), not its label. **Test connection** lists every option.

## 2. Install on a league site

1. Upload the zip (Plugins > Add New > Upload) and activate. Try it on a dev/staging site first.
2. Put the secrets in `wp-config.php`, above the "stop editing" line. They override the settings page and stay out of the database:

```php
define( 'NICA_HSE_TOKEN', 'your-service-key' );
define( 'NICA_HSE_OBJECT_TYPE', '2-12345678' );
define( 'NICA_HSE_LEAGUE', 'florida' );
define( 'NICA_HSE_SYNC_SECRET', 'a-long-random-string' ); // only for the n8n / instant-sync endpoint
```

3. Settings > **HubSpot Events**: set the events page URL (used for "All events" links), click **Test connection**, then **Sync now**.
4. Make a page called **Events** with `[nica_events]` in it.

Event pages live at `/events/event-title/`, next to the Events page at `/events/`. Rewrite rules refresh by themselves. **If ME Calendar is still active**, it uses the `/events/` URL too; deactivate it first (or change the plugin's base with `add_filter( 'nica_hse_rewrite_slug', fn() => 'league-events' );`).

## 3. The shortcode

```
[nica_events]                       upcoming events, one per row
[nica_events show="past"]           past events (newest first)
[nica_events show="all"]            upcoming, then past
[nica_events limit="3"]             only the next 3 (home page)
[nica_events type="Race Weekend"]   one event type only (use the label)
[nica_events columns="2"]           two cards per row on wide screens
```

## 4. Cards and buttons

- Date (`08/22/2026 — 08/23/2026`), type badge, big title, venue and address (address opens Google Maps).
- Buttons appear only for links that exist in HubSpot: Register, Volunteer, Event Flyer, Photos, Results, plus **Learn More**.
- After an event ends, Register and Volunteer are hidden and Results / Photos come first. No maintenance needed.
- Every button is an `<a class="leaguePrimaryBtn nica-btn">`, so your existing `leaguePrimaryBtn` styling applies. If the theme doesn't style it, the plugin falls back to `background: var(--leaguePrimaryColor)` (blue if that variable isn't defined). This assumes `--leaguePrimaryColor` is a CSS custom property; if it's something else, change the one `background:` line under `/* Buttons */` in `assets/events.css`.
- Card colours: `.nica-events { --nica-card-bg: #212d52; --nica-card-art: url(/path/tire.png); }`. `--nica-card-art` is the tyre graphic on the right.
- Markup: copy `templates/event-card.php` or `templates/single-event.php` into `your-theme/nica-hubspot-events/` to change it.

## 5. How syncing behaves

- Match key is the HubSpot record ID, so editing an event in HubSpot updates the same page and URL.
- Unchanged events are skipped.
- An event that disappears from HubSpot (or whose League changes) goes to **Draft**, never deleted. It comes back if it returns.
- If HubSpot returns zero events for the league, nothing is unpublished (protects against a wrong League value or an outage). A warning shows on the settings page.
- Events missing a name or start date are skipped and listed on the settings page.
- Edits made in WordPress are overwritten at the next sync. HubSpot is the source of truth.

## 6. n8n (instant updates, all league sites from one place)

Each site exposes `POST /wp-json/nica-hse/v1/sync` (needs the `X-NICA-Secret` header). n8n's only job is to call it.

1. n8n > Workflows > Import from file > `n8n/nica-events-sync.n8n.json`.
2. Open **Which sites?** and list your league sites (league value, site URL, that site's sync secret). Add more as you roll out.
3. Activate. The **Every 15 minutes** trigger is a safety net and runs without HubSpot's help.
4. For instant updates, in HubSpot create a workflow on the Events object ("record created" and "property changed") with a **Send a webhook** action (POST) to the n8n webhook URL, with the body `{ "league": "<League value>" }`. n8n then calls only that league's site.

Verify any site by hand first:

```
curl -X POST "https://YOURSITE.org/wp-json/nica-hse/v1/sync" -H "X-NICA-Secret: a-long-random-string"
```

The workflow file was checked for valid JSON and its site-picking logic was run in Node, but it has **not been imported into a real n8n instance**. Node versions in n8n differ slightly, so if an import complains, rebuild the 4 nodes by hand (Webhook, Schedule, Code, HTTP Request).

Plugin vs n8n for the later headless move: the HubSpot-to-site step is what n8n replaces. The WordPress plugin is just the receiver. When the sites become headless, n8n calls the new site's rebuild/revalidate endpoint instead.

## 7. Known caveats

- The embedded map uses Google's keyless `maps?q=...&output=embed` URL. It isn't an official API and could change. "Get directions" is the stable fallback; the Maps Embed API (free, needs a key) is the upgrade.
- The Featured Image is copied into the Media Library (HubSpot file links can expire). If the Service Key lacks file access, the event still publishes without the picture and a warning shows.
- Tested on real WordPress against a mock HubSpot (paging, league filter, idempotency, unpublish/republish, zero-result guard, REST endpoint, image copy, pretty URLs, block theme). **Not tested against your real HubSpot or n8n**, so run Test connection first.

## Tests

`php tests/util-test.php` runs 69 checks on dates, URL/email safety, the league filter, and button logic.
