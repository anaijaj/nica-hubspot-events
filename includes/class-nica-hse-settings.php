<?php
/**
 * Settings storage, the settings page, "Sync now" and "Test connection".
 *
 * @package NICA_HubSpot_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NICA_HSE_Settings {

	const OPTION = 'nica_hse_settings';
	const SLUG   = 'nica-hubspot-events';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_nica_hse_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_post_nica_hse_test', array( __CLASS__, 'handle_test' ) );
	}

	/** Effective settings. Constants in wp-config.php win over saved values. */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$s     = wp_parse_args(
			$saved,
			array(
				'token'       => '',
				'object_type' => '',
				'league'      => '',
				'map_text'    => NICA_HSE_Util::map_to_text( NICA_HSE_Util::default_map() ),
				'secret'      => '',
				'events_url'  => '',
			)
		);
		if ( defined( 'NICA_HSE_TOKEN' ) ) {
			$s['token'] = (string) NICA_HSE_TOKEN;
		}
		if ( defined( 'NICA_HSE_OBJECT_TYPE' ) ) {
			$s['object_type'] = (string) NICA_HSE_OBJECT_TYPE;
		}
		if ( defined( 'NICA_HSE_LEAGUE' ) ) {
			$s['league'] = (string) NICA_HSE_LEAGUE;
		}
		if ( defined( 'NICA_HSE_SYNC_SECRET' ) ) {
			$s['secret'] = (string) NICA_HSE_SYNC_SECRET;
		}
		$s['map']      = NICA_HSE_Util::parse_map( $s['map_text'] );
		$s['api_base'] = defined( 'NICA_HSE_API_BASE' ) ? (string) NICA_HSE_API_BASE : 'https://api.hubapi.com';
		return $s;
	}

	public static function menu() {
		add_options_page( 'HubSpot Events', 'HubSpot Events', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting(
			'nica_hse',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	public static function sanitize( $in ) {
		$old = get_option( self::OPTION, array() );
		$old = is_array( $old ) ? $old : array();
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		$token        = isset( $in['token'] ) ? trim( (string) $in['token'] ) : '';
		$out['token'] = '' !== $token ? $token : ( isset( $old['token'] ) ? $old['token'] : '' );

		$secret        = isset( $in['secret'] ) ? trim( (string) $in['secret'] ) : '';
		$out['secret'] = '' !== $secret ? $secret : ( isset( $old['secret'] ) ? $old['secret'] : '' );

		$out['object_type'] = isset( $in['object_type'] ) ? sanitize_text_field( $in['object_type'] ) : '';
		$out['league']      = isset( $in['league'] ) ? sanitize_text_field( $in['league'] ) : '';
		$out['events_url']  = isset( $in['events_url'] ) ? esc_url_raw( trim( (string) $in['events_url'] ) ) : '';

		// Re-serialise through the parser so only valid keys and property names are stored.
		$out['map_text'] = NICA_HSE_Util::map_to_text( NICA_HSE_Util::parse_map( isset( $in['map_text'] ) ? (string) $in['map_text'] : '' ) );
		return $out;
	}

	private static function client( array $s ) {
		return new NICA_HSE_HubSpot( $s['token'], $s['api_base'] );
	}

	public static function handle_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'nica_hse_sync' );
		NICA_HSE_Sync::run();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG . '&synced=1' ) );
		exit;
	}

	public static function handle_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'nica_hse_test' );
		set_transient( 'nica_hse_diag', self::diagnose(), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG . '&tested=1' ) );
		exit;
	}

	/** Checks the connection, the mapped property names and the League value. */
	public static function diagnose() {
		$s   = self::get();
		$res = array(
			'messages' => array(),
			'missing'  => array(),
			'league'   => array(),
			'count'    => null,
			'ok'       => false,
		);
		if ( '' === $s['token'] || '' === $s['object_type'] ) {
			$res['messages'][] = 'Enter the HubSpot token and the object type ID first.';
			return $res;
		}
		$client = self::client( $s );
		$props  = $client->get_properties( $s['object_type'] );
		if ( is_wp_error( $props ) ) {
			$res['messages'][] = 'Could not read the Events object: ' . $props->get_error_message();
			return $res;
		}
		$names = array();
		foreach ( $props as $p ) {
			if ( isset( $p['name'] ) ) {
				$names[ $p['name'] ] = isset( $p['label'] ) ? $p['label'] : $p['name'];
			}
		}
		$res['messages'][] = 'Connected. The object has ' . count( $names ) . ' properties.';
		foreach ( $s['map'] as $key => $prop ) {
			if ( ! isset( $names[ $prop ] ) ) {
				$res['missing'][ $key ] = $prop;
			}
		}
		$res['properties'] = $names;

		$league_prop = $s['map']['league'];
		foreach ( $props as $p ) {
			if ( isset( $p['name'] ) && $p['name'] === $league_prop && ! empty( $p['options'] ) ) {
				$res['league'] = NICA_HSE_Util::options_to_labels( $p['options'] );
			}
		}

		if ( '' !== $s['league'] && ! isset( $res['missing']['league'] ) ) {
			$found = $client->search_events( $s['object_type'], $league_prop, $s['league'], array( $s['map']['title'] ) );
			if ( is_wp_error( $found ) ) {
				$res['messages'][] = 'Search failed: ' . $found->get_error_message();
			} else {
				$res['count'] = count( $found );
			}
		}
		$res['ok'] = empty( $res['missing'] );
		return $res;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = self::get();
		$last = get_option( 'nica_hse_last_sync', array() );
		$diag = get_transient( 'nica_hse_diag' );
		$lock = function ( $const ) {
			return defined( $const ) ? ' <em>(set in wp-config.php)</em>' : '';
		};
		?>
		<div class="wrap">
			<h1>HubSpot Events</h1>
			<p>Events created in HubSpot for this site's league are copied here automatically (every 15 minutes, or when you press Sync now).</p>

			<?php if ( isset( $_GET['synced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-info is-dismissible"><p>Sync finished. See the result below.</p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'nica_hse' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="nica_hse_token">HubSpot private app token</label></th>
						<td>
							<input type="password" id="nica_hse_token" name="<?php echo esc_attr( self::OPTION ); ?>[token]" class="regular-text" autocomplete="new-password" placeholder="<?php echo '' !== $s['token'] ? '(saved)' : 'pat-...'; ?>" <?php disabled( defined( 'NICA_HSE_TOKEN' ) ); ?> />
							<?php echo wp_kses( $lock( 'NICA_HSE_TOKEN' ), array( 'em' => array() ) ); ?>
							<p class="description">Safer: put <code>define( 'NICA_HSE_TOKEN', '...' );</code> in wp-config.php instead. Leave blank to keep the saved token.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nica_hse_object">Events object type ID</label></th>
						<td>
							<input type="text" id="nica_hse_object" name="<?php echo esc_attr( self::OPTION ); ?>[object_type]" value="<?php echo esc_attr( $s['object_type'] ); ?>" class="regular-text" placeholder="2-12345678" <?php disabled( defined( 'NICA_HSE_OBJECT_TYPE' ) ); ?> />
							<p class="description">HubSpot: Settings &rarr; Data Management &rarr; Objects &rarr; Events. It looks like <code>2-12345678</code> (or <code>p12345_events</code>).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nica_hse_league">League for this site</label></th>
						<td>
							<input type="text" id="nica_hse_league" name="<?php echo esc_attr( self::OPTION ); ?>[league]" value="<?php echo esc_attr( $s['league'] ); ?>" class="regular-text" placeholder="florida" <?php disabled( defined( 'NICA_HSE_LEAGUE' ) ); ?> />
							<p class="description">The League dropdown's <strong>internal value</strong> for this site (Test connection lists the values). Only events with this League are copied.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nica_hse_events_url">Events page URL</label></th>
						<td>
							<input type="url" id="nica_hse_events_url" name="<?php echo esc_attr( self::OPTION ); ?>[events_url]" value="<?php echo esc_attr( $s['events_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/events/' ) ); ?>" />
							<p class="description">Optional. Adds an "All events" link on each event page.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nica_hse_map">Property names</label></th>
						<td>
							<textarea id="nica_hse_map" name="<?php echo esc_attr( self::OPTION ); ?>[map_text]" rows="16" cols="50" class="large-text code"><?php echo esc_textarea( $s['map_text'] ); ?></textarea>
							<p class="description">Left: the field on the website. Right: the HubSpot <strong>internal name</strong> of the property. Press Test connection to see which names HubSpot does not recognise.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nica_hse_secret">Sync trigger secret</label></th>
						<td>
							<input type="password" id="nica_hse_secret" name="<?php echo esc_attr( self::OPTION ); ?>[secret]" class="regular-text" autocomplete="new-password" placeholder="<?php echo '' !== $s['secret'] ? '(saved)' : 'optional'; ?>" <?php disabled( defined( 'NICA_HSE_SYNC_SECRET' ) ); ?> />
							<p class="description">Optional, for n8n or a HubSpot workflow later: <code>POST <?php echo esc_html( rest_url( 'nica-hse/v1/sync' ) ); ?></code> with header <code>X-NICA-Secret</code> starts an immediate sync.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save settings' ); ?>
			</form>

			<h2>Actions</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px">
				<input type="hidden" name="action" value="nica_hse_test" />
				<?php wp_nonce_field( 'nica_hse_test' ); ?>
				<?php submit_button( 'Test connection', 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<input type="hidden" name="action" value="nica_hse_sync" />
				<?php wp_nonce_field( 'nica_hse_sync' ); ?>
				<?php submit_button( 'Sync now', 'primary', 'submit', false ); ?>
			</form>

			<?php if ( is_array( $diag ) ) : ?>
				<h2>Connection test</h2>
				<?php foreach ( $diag['messages'] as $m ) : ?>
					<p><?php echo esc_html( $m ); ?></p>
				<?php endforeach; ?>
				<?php if ( ! empty( $diag['missing'] ) ) : ?>
					<p><strong>These names are not properties on the Events object:</strong></p>
					<ul style="list-style:disc;margin-left:20px">
						<?php foreach ( $diag['missing'] as $key => $prop ) : ?>
							<li><code><?php echo esc_html( $key . '=' . $prop ); ?></code></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! empty( $diag['properties'] ) ) : ?>
						<p>Properties HubSpot has (internal name &mdash; label):</p>
						<ul style="list-style:disc;margin-left:20px;columns:2">
							<?php foreach ( $diag['properties'] as $name => $label ) : ?>
								<li><code><?php echo esc_html( $name ); ?></code> &mdash; <?php echo esc_html( $label ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php elseif ( ! empty( $diag['ok'] ) ) : ?>
					<p>All mapped property names exist.</p>
				<?php endif; ?>
				<?php if ( ! empty( $diag['league'] ) ) : ?>
					<p>League values (internal value &mdash; label):</p>
					<ul style="list-style:disc;margin-left:20px;columns:3">
						<?php foreach ( $diag['league'] as $value => $label ) : ?>
							<li><code><?php echo esc_html( $value ); ?></code> &mdash; <?php echo esc_html( $label ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( null !== $diag['count'] ) : ?>
					<p>Events found for league <code><?php echo esc_html( $s['league'] ); ?></code>: <strong><?php echo (int) $diag['count']; ?></strong></p>
				<?php endif; ?>
			<?php endif; ?>

			<h2>Last sync</h2>
			<?php if ( empty( $last ) ) : ?>
				<p>No sync has run yet.</p>
			<?php else : ?>
				<p>
					<?php echo esc_html( wp_date( 'M j, Y g:i a', (int) $last['time'] ) ); ?> &mdash;
					<?php if ( ! empty( $last['error'] ) ) : ?>
						<strong style="color:#b32d2e"><?php echo esc_html( $last['error'] ); ?></strong>
					<?php else : ?>
						created <?php echo (int) $last['created']; ?>, updated <?php echo (int) $last['updated']; ?>,
						unchanged <?php echo (int) $last['unchanged']; ?>, unpublished <?php echo (int) $last['unpublished']; ?>.
					<?php endif; ?>
				</p>
				<?php foreach ( array( 'skipped' => 'Skipped', 'warnings' => 'Warnings' ) as $key => $title ) : ?>
					<?php if ( ! empty( $last[ $key ] ) ) : ?>
						<p><strong><?php echo esc_html( $title ); ?>:</strong></p>
						<ul style="list-style:disc;margin-left:20px">
							<?php foreach ( $last[ $key ] as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
