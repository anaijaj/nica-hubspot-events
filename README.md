# NICA HubSpot Events (WordPress plugin)

Copies this league's events from the HubSpot **Events** object into WordPress.

- Only events whose **League** matches this site's league are copied (a Canada event never reaches the Florida site).
- Each event gets its own page automatically (dark hero from the Featured Image, description, links, host, map).
- `[nica_events]` shows the cards. New events appear in it with no editing.
- No ACF. HubSpot holds every field.
- Runs on WP-Cron every 15 minutes, plus a **Sync now** button. No n8n needed. n8n can be added later (see the end).

## 1. HubSpot setup (once, shared by all sites)

1. Settings > Integrations > **Private Apps** > Create. Scopes:
   - `crm.objects.custom.read` (reads the Events object)
   - `crm.schemas.custom.read` (reads property names and dropdown labels)
   - `files` (reads the Featured Image file)
   If your Events object turns out not to be a custom object, tell me which scopes it asks for and I'll adjust.
2. Copy the access token.
3. Find the **object type ID**: Settings > Data Management > Objects > Events. It looks like `2-12345678`.
4. Find the **internal name of each property** (Settings > Data Management > Properties > Events > click a property). The plugin guesses names like `event_name`, `event_start_date`, `league`. If yours differ, edit them in the plugin's property map (step 3 below). **Test connection** tells you exactly which names it can't find.
5. Find the **League internal value** for this site. The dropdown shows labels ("Florida") but filtering uses the internal value (often `florida` or `Florida`). **Test connection** lists every League option as `value => label`.

## 2. Install on a league site

1. Upload the `nica-hubspot-events` folder to `wp-content/plugins/` (or upload the zip) and activate.
2. Settings > **HubSpot Events**. Fill in token, object type ID, this site's League value, events page URL.
   Safer for Kinsta: put the secrets in `wp-config.php` instead (they override the form and aren't stored in the database):

```php
define( 'NICA_HSE_TOKEN', 'pat-na1-...' );
define( 'NICA_HSE_OBJECT_TYPE', '2-12345678' );
define( 'NICA_HSE_LEAGUE', 'florida' );
define( 'NICA_HSE_SYNC_SECRET', 'a-long-random-string' ); // only needed for the instant-trigger endpoint
```

3. Click **Test connection**. Fix anything it lists, then click **Sync now**.
4. Make a page called Events and put `[nica_events]` on it. Save its URL in the settings (used for the "All events" link).
5. Settings > Permalinks > Save (once) so event URLs like `/league-event/ocala-showdown/` work.

## 3. The shortcode

```
[nica_events]                       upcoming events, 3 per row
[nica_events show="past"]           past events (newest first, with Results / Photos buttons)
[nica_events show="all"]            upcoming, then past
[nica_events limit="3"]             only the next 3 (good for a home page)
[nica_events type="Race Weekend"]   only one event type (use the label)
[nica_events columns="2"]
```

## 4. What a card does

- Event type badge (filled pill), date range, venue, address (opens Google Maps).
- Upcoming: **Register** (only if there is a registration link), **Volunteer**, **Event flyer**, **Learn more**, share button.
- After the event ends the buttons switch to **Results**, **Photos**, **Event flyer** (when those links exist). Nothing to maintain.
- Buttons only appear when the HubSpot field has a link.

## 5. Testing without n8n

- **Sync now** button on the settings page.
- WP-Cron runs it every 15 minutes by itself. On Kinsta WP-Cron works, but only when the site gets visits. For a guaranteed schedule, set a Kinsta cron job calling `wp cron event run nica_hse_cron_sync`.
- From a terminal (this is also what n8n or a HubSpot workflow would do):

```
curl -X POST "https://YOURSITE.org/wp-json/nica-hse/v1/sync" -H "X-NICA-Secret: a-long-random-string"
```

- Create a test event in HubSpot with League = your league, wait about a minute (HubSpot's search can lag), click Sync now.

## 6. How syncing behaves

- Match key is the HubSpot record ID, so editing an event in HubSpot updates the same page and URL.
- Unchanged events are skipped.
- If an event disappears from HubSpot (or its League changes), its page goes to **Draft**, never deleted. It comes back if it returns.
- If HubSpot returns zero events for the league, nothing is unpublished (protects against a wrong League value or an outage). The settings page shows a warning.
- Events missing a name or a start date are skipped and listed on the settings page.
- Edits made in WordPress are overwritten at the next sync. HubSpot is the source of truth.

## 7. Customising the look

CSS variables: `.nica-events, .nica-single { --nica-accent: #e4002b; }`. To change markup, copy `templates/event-card.php` or `templates/single-event.php` into `your-theme/nica-hubspot-events/`. Block themes and classic themes are both supported.

## 8. Known caveats

- The embedded map uses Google's keyless `maps?q=...&output=embed` URL. It is not an official API and Google could change it. The "Get directions" link is the stable fallback. If the embed stops working, switch to the Maps Embed API (free, needs a key).
- The Featured Image is copied into the Media Library (HubSpot file links can expire). If your Featured Image file is private and the `files` scope is missing, the event still publishes without the picture and a warning appears.
- Tested here against a real WordPress with a mock HubSpot server (paging, league filter, idempotency, unpublish/republish, zero-result guard, REST endpoint, image copy, block theme). **Not tested against your real HubSpot account**, so run Test connection first.

## 9. Adding n8n later (optional, instant updates)

HubSpot workflow ("when an Events record is created or updated") > webhook/n8n > one POST to `/wp-json/nica-hse/v1/sync` with the secret header. With n8n you can fan one trigger out to every league site. WP-Cron remains the safety net.

## Tests

`php tests/util-test.php` runs 60 checks on date formatting, URL/email safety, the league filter, and button logic.
