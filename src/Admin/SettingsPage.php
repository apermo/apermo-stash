<?php

declare(strict_types=1);

namespace Apermo\LinkStash\Admin;

\defined( 'ABSPATH' ) || exit();

use Apermo\LinkStash\Auth\TokenStore;
use Apermo\LinkStash\Main;

/**
 * Renders the Settings → LinkStash page that manages API tokens.
 */
class SettingsPage {

	private const PAGE_SLUG = 'linkstash';
	private const ACTION_CREATE = 'linkstash_token_create';
	private const ACTION_REVOKE = 'linkstash_token_revoke';
	private const TRANSIENT_PREFIX = 'linkstash_new_token_';

	/**
	 * Holds the token store.
	 *
	 * @var TokenStore
	 */
	private TokenStore $store;

	/**
	 * Constructs the screen.
	 *
	 * @param TokenStore $store Token store.
	 */
	public function __construct( TokenStore $store ) {
		$this->store = $store;
	}

	/**
	 * Reads (and clears) the just-generated plain token, if any.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return string|null
	 */
	private static function pop_new_token( int $user_id ): ?string {
		$key   = self::TRANSIENT_PREFIX . $user_id;
		$value = get_transient( $key );
		if ( ! \is_string( $value ) || $value === '' ) {
			return null;
		}

		delete_transient( $key );

		return $value;
	}

	/**
	 * Returns the settings page URL.
	 *
	 * @return string
	 */
	private static function settings_url(): string {
		return admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Formats a Unix timestamp the way WP admin list tables do.
	 *
	 * Returns "x ago" (via human_time_diff) for events younger than 24h
	 * and falls back to "<site date format> at <site time format>" for
	 * older ones. Mirrors WP_Privacy_Requests_Table::get_timestamp_as_date().
	 *
	 * @param int $timestamp Unix timestamp.
	 *
	 * @return string
	 */
	private static function formatted_date( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return '—';
		}

		$time_diff = \time() - $timestamp;

		if ( $time_diff >= 0 && $time_diff < \DAY_IN_SECONDS ) {
			/* translators: %s: Human-readable time difference. */
			return \sprintf( __( '%s ago', 'linkstash' ), human_time_diff( $timestamp ) );
		}

		return \sprintf(
			/* translators: 1: token date, 2: token time. */
			__( '%1$s at %2$s', 'linkstash' ),
			wp_date( (string) get_option( 'date_format' ), $timestamp ),
			wp_date( (string) get_option( 'time_format' ), $timestamp ),
		);
	}

	/**
	 * Renders the just-generated-token notice when present.
	 *
	 * @param string|null $token Plain token to surface, or null.
	 *
	 * @return void
	 */
	private static function render_new_token_notice( ?string $token ): void {
		if ( $token === null ) {
			return;
		}
		?>
		<div class="notice notice-success">
			<p><strong><?php esc_html_e( 'New token created. Copy it now — it will not be shown again.', 'linkstash' ); ?></strong></p>
			<p><code style="display:inline-block;padding:.5rem 1rem;background:#f0f0f1;"><?php echo esc_html( $token ); ?></code></p>
		</div>
		<?php
	}

	/**
	 * Renders the generate-a-token form.
	 *
	 * @return void
	 */
	private static function render_create_form(): void {
		?>
		<h2><?php esc_html_e( 'Generate a new token', 'linkstash' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_CREATE ); ?>" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION_CREATE ) ); ?>" />
			<p>
				<label for="linkstash-token-name"><?php esc_html_e( 'Name', 'linkstash' ); ?></label>
				<input id="linkstash-token-name" type="text" name="token_name" required class="regular-text" placeholder="<?php esc_attr_e( 'Chrome extension on laptop', 'linkstash' ); ?>" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate', 'linkstash' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Renders the existing-tokens table.
	 *
	 * @param array<int, array{id: string, name: string, created: int, last_used: ?int}> $tokens Tokens.
	 *
	 * @return void
	 */
	private static function render_tokens_table( array $tokens ): void {
		?>
		<h2><?php esc_html_e( 'Existing tokens', 'linkstash' ); ?></h2>
		<?php
		if ( $tokens === [] ) {
			echo '<p>' . esc_html__( 'No tokens yet.', 'linkstash' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'linkstash' ); ?></th>
					<th><?php esc_html_e( 'Created', 'linkstash' ); ?></th>
					<th><?php esc_html_e( 'Last used', 'linkstash' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $tokens as $entry ) {
					self::render_token_row( $entry );
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders a single token-table row.
	 *
	 * @param array{id: string, name: string, created: int, last_used: ?int} $entry Token entry.
	 *
	 * @return void
	 */
	private static function render_token_row( array $entry ): void {
		?>
		<tr>
			<td><?php echo esc_html( $entry['name'] ); ?></td>
			<td><?php echo esc_html( self::formatted_date( $entry['created'] ) ); ?></td>
			<td>
				<?php
				if ( $entry['last_used'] === null ) {
					esc_html_e( 'never', 'linkstash' );
				} else {
					echo esc_html( self::formatted_date( $entry['last_used'] ) );
				}
				?>
			</td>
			<td>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REVOKE ); ?>" />
					<input type="hidden" name="token_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
					<?php wp_nonce_field( self::ACTION_REVOKE . ':' . $entry['id'] ); ?>
					<button type="submit" class="button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Revoke this token?', 'linkstash' ) ); ?>');"><?php esc_html_e( 'Revoke', 'linkstash' ); ?></button>
				</form>
			</td>
		</tr>
		<?php
	}

	/**
	 * Hooks the menu and form-handling actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_post_' . self::ACTION_CREATE, [ $this, 'handle_create' ] );
		add_action( 'admin_post_' . self::ACTION_REVOKE, [ $this, 'handle_revoke' ] );
		add_filter(
			'plugin_action_links_' . plugin_basename( Main::file() ),
			[ $this, 'plugin_action_links' ],
		);
	}

	/**
	 * Registers the Settings → LinkStash menu entry.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'LinkStash', 'linkstash' ),
			__( 'LinkStash', 'linkstash' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ],
		);
	}

	/**
	 * Prepends a "Settings" link to the plugin row actions.
	 *
	 * @param array<int|string, string> $links Existing action links.
	 *
	 * @return array<int|string, string>
	 */
	public function plugin_action_links( array $links ): array {
		$settings = \sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::settings_url() ),
			esc_html__( 'Settings', 'linkstash' ),
		);

		\array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'linkstash' ), '', [ 'response' => 403 ] );
		}

		$user_id   = get_current_user_id();
		$tokens    = $this->store->list( $user_id );
		$new_token = self::pop_new_token( $user_id );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LinkStash API Tokens', 'linkstash' ); ?></h1>
			<?php
			self::render_new_token_notice( $new_token );
			self::render_create_form();
			self::render_tokens_table( $tokens );
			?>
		</div>
		<?php
	}

	/**
	 * Handles a token-create POST.
	 *
	 * @return void
	 */
	public function handle_create(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'linkstash' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::ACTION_CREATE );

		$name = isset( $_POST['token_name'] ) && \is_string( $_POST['token_name'] )
			? sanitize_text_field( wp_unslash( $_POST['token_name'] ) )
			: '';

		if ( $name === '' ) {
			wp_safe_redirect( self::settings_url() );
			exit();
		}

		$user_id = get_current_user_id();
		$plain   = $this->store->create( $user_id, $name );

		set_transient( self::TRANSIENT_PREFIX . $user_id, $plain, 60 );

		wp_safe_redirect( self::settings_url() );
		exit();
	}

	/**
	 * Handles a token-revoke POST.
	 *
	 * @return void
	 */
	public function handle_revoke(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'linkstash' ), '', [ 'response' => 403 ] );
		}

		$token_id = isset( $_POST['token_id'] ) && \is_string( $_POST['token_id'] )
			? sanitize_text_field( wp_unslash( $_POST['token_id'] ) )
			: '';

		check_admin_referer( self::ACTION_REVOKE . ':' . $token_id );

		if ( $token_id !== '' ) {
			$this->store->revoke( get_current_user_id(), $token_id );
		}

		wp_safe_redirect( self::settings_url() );
		exit();
	}
}
