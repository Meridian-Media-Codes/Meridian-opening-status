<?php
/**
 * Plugin Name: Meridian Opening Status
 * Description: Displays a live open/closed message with click-to-call or contact links. Includes weekly hours, manual overrides, a shortcode and a widget.
 * Version: 1.0.1
 * Author: Meridian Media
 * Text Domain: meridian-opening-status
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Meridian_Opening_Status {

	const VERSION = '1.0.1';
	const OPTION  = 'mos_settings';

	private static $instance = null;
	private $assets_needed   = false;

	/**
	 * Return the plugin instance.
	 *
	 * @return Meridian_Opening_Status
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set up plugin hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_shortcode( 'opening_status', array( $this, 'shortcode' ) );

		add_action(
			'widgets_init',
			function() {
				register_widget( 'MOS_Opening_Status_Widget' );
			}
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'enqueue_assets_if_needed' ), 1 );

		add_action(
			'rest_api_init',
			function() {
				register_rest_route(
					'meridian-opening-status/v1',
					'/status',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'rest_status' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'phone_display' => '07368 830784',
			'phone_link'    => '07368830784',
			'contact_url'   => '/contact/',
			'timezone'      => wp_timezone_string() ? wp_timezone_string() : 'Europe/London',
			'override'      => 'auto',

			'open_text'   => "We're Open",
			'open_cta'    => 'Call {number}',
			'closed_text' => "We're Currently Closed",
			'closed_cta'  => 'Send a Message',

			/*
			 * The plugin background is always transparent.
			 * The Kadence header row controls the background colour.
			 */
			'text_colour'   => '#ffffff',
			'accent_colour' => '#8fcf45',
			'align'         => 'left',
			'font_size'     => '13',
			'padding_y'     => '0',
			'refresh'       => '60',

			'hours' => array(
				'monday' => array(
					'enabled' => 1,
					'open'    => '09:00',
					'close'   => '17:00',
				),
				'tuesday' => array(
					'enabled' => 1,
					'open'    => '09:00',
					'close'   => '17:00',
				),
				'wednesday' => array(
					'enabled' => 1,
					'open'    => '09:00',
					'close'   => '17:00',
				),
				'thursday' => array(
					'enabled' => 1,
					'open'    => '09:00',
					'close'   => '17:00',
				),
				'friday' => array(
					'enabled' => 1,
					'open'    => '09:00',
					'close'   => '17:00',
				),
				'saturday' => array(
					'enabled' => 0,
					'open'    => '09:00',
					'close'   => '13:00',
				),
				'sunday' => array(
					'enabled' => 0,
					'open'    => '09:00',
					'close'   => '13:00',
				),
			),
		);
	}

	/**
	 * Retrieve saved settings merged with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $this->defaults() );

		/*
		 * Ensure nested opening-hour defaults are also merged correctly.
		 */
		$default_hours = $this->defaults()['hours'];
		$saved_hours   = isset( $saved['hours'] ) && is_array( $saved['hours'] )
			? $saved['hours']
			: array();

		$settings['hours'] = array();

		foreach ( $default_hours as $day => $day_defaults ) {
			$settings['hours'][ $day ] = wp_parse_args(
				$saved_hours[ $day ] ?? array(),
				$day_defaults
			);
		}

		return $settings;
	}

	/**
	 * Add the settings page.
	 */
	public function admin_menu() {
		add_options_page(
			'Opening Status',
			'Opening Status',
			'manage_options',
			'meridian-opening-status',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'mos_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults(),
			)
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param array $input Submitted settings.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = $this->defaults();
		$output   = array();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$output['phone_display'] = sanitize_text_field(
			$input['phone_display'] ?? $defaults['phone_display']
		);

		$output['phone_link'] = preg_replace(
			'/[^0-9+]/',
			'',
			(string) ( $input['phone_link'] ?? $defaults['phone_link'] )
		);

		$output['contact_url'] = sanitize_text_field(
			$input['contact_url'] ?? $defaults['contact_url']
		);

		$submitted_timezone = $input['timezone'] ?? '';

		$output['timezone'] = in_array(
			$submitted_timezone,
			timezone_identifiers_list(),
			true
		)
			? $submitted_timezone
			: $defaults['timezone'];

		$override = $input['override'] ?? 'auto';

		$output['override'] = in_array(
			$override,
			array( 'auto', 'open', 'closed' ),
			true
		)
			? $override
			: 'auto';

		foreach (
			array(
				'open_text',
				'open_cta',
				'closed_text',
				'closed_cta',
			) as $key
		) {
			$output[ $key ] = sanitize_text_field(
				$input[ $key ] ?? $defaults[ $key ]
			);
		}

		/*
		 * Only text and accent colours are controlled by the plugin.
		 * The containing Kadence header controls the background.
		 */
		foreach (
			array(
				'text_colour',
				'accent_colour',
			) as $key
		) {
			$output[ $key ] = sanitize_hex_color(
				$input[ $key ] ?? $defaults[ $key ]
			);

			if ( empty( $output[ $key ] ) ) {
				$output[ $key ] = $defaults[ $key ];
			}
		}

		$output['align'] = in_array(
			$input['align'] ?? '',
			array( 'left', 'center', 'right' ),
			true
		)
			? $input['align']
			: 'left';

		$output['font_size'] = (string) min(
			24,
			max(
				10,
				absint( $input['font_size'] ?? $defaults['font_size'] )
			)
		);

		$output['padding_y'] = (string) min(
			30,
			max(
				0,
				absint( $input['padding_y'] ?? $defaults['padding_y'] )
			)
		);

		$output['refresh'] = (string) min(
			3600,
			max(
				30,
				absint( $input['refresh'] ?? $defaults['refresh'] )
			)
		);

		$output['hours'] = array();

		foreach ( $defaults['hours'] as $day => $day_defaults ) {
			$day_input = isset( $input['hours'][ $day ] ) && is_array( $input['hours'][ $day ] )
				? $input['hours'][ $day ]
				: array();

			$output['hours'][ $day ] = array(
				'enabled' => empty( $day_input['enabled'] ) ? 0 : 1,
				'open'    => $this->sanitize_time(
					$day_input['open'] ?? $day_defaults['open'],
					$day_defaults['open']
				),
				'close'   => $this->sanitize_time(
					$day_input['close'] ?? $day_defaults['close'],
					$day_defaults['close']
				),
			);
		}

		return $output;
	}

	/**
	 * Sanitize a 24-hour time value.
	 *
	 * @param string $value    Time value.
	 * @param string $fallback Fallback value.
	 *
	 * @return string
	 */
	private function sanitize_time( $value, $fallback ) {
		return preg_match(
			'/^(?:[01]\d|2[0-3]):[0-5]\d$/',
			(string) $value
		)
			? $value
			: $fallback;
	}

	/**
	 * Register front-end assets.
	 */
	public function register_assets() {
		wp_register_style(
			'meridian-opening-status',
			plugins_url( 'assets/opening-status.css', __FILE__ ),
			array(),
			self::VERSION
		);

		wp_register_script(
			'meridian-opening-status',
			plugins_url( 'assets/opening-status.js', __FILE__ ),
			array(),
			self::VERSION,
			true
		);

		wp_localize_script(
			'meridian-opening-status',
			'MOSOpeningStatus',
			array(
				'endpoint' => esc_url_raw(
					rest_url( 'meridian-opening-status/v1/status' )
				),
			)
		);
	}

	/**
	 * Load assets when the shortcode or widget is present.
	 */
	public function enqueue_assets_if_needed() {
		if ( ! $this->assets_needed ) {
			return;
		}

		wp_enqueue_style( 'meridian-opening-status' );
		wp_enqueue_script( 'meridian-opening-status' );
	}

	/**
	 * Mark assets as required.
	 */
	public function mark_assets_needed() {
		$this->assets_needed = true;
	}

	/**
	 * Determine whether the business is currently open.
	 *
	 * @return bool
	 */
	public function get_status() {
		$settings = $this->get_settings();

		if ( 'open' === $settings['override'] ) {
			return true;
		}

		if ( 'closed' === $settings['override'] ) {
			return false;
		}

		try {
			$timezone = new DateTimeZone( $settings['timezone'] );
		} catch ( Exception $exception ) {
			$timezone = wp_timezone();
		}

		$now       = new DateTimeImmutable( 'now', $timezone );
		$day       = strtolower( $now->format( 'l' ) );
		$day_hours = $settings['hours'][ $day ] ?? null;

		if ( empty( $day_hours['enabled'] ) ) {
			/*
			 * The current day may still be inside an overnight period
			 * that started on the previous day.
			 */
			return $this->is_inside_previous_overnight_period(
				$now,
				$settings
			);
		}

		$open = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i',
			$now->format( 'Y-m-d' ) . ' ' . $day_hours['open'],
			$timezone
		);

		$close = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i',
			$now->format( 'Y-m-d' ) . ' ' . $day_hours['close'],
			$timezone
		);

		if ( ! $open || ! $close ) {
			return false;
		}

		/*
		 * Matching opening and closing times are treated as closed,
		 * rather than open for 24 hours.
		 */
		if ( $open == $close ) {
			return false;
		}

		/*
		 * Support businesses that close after midnight.
		 */
		if ( $close < $open ) {
			$close = $close->modify( '+1 day' );
		}

		if ( $now >= $open && $now < $close ) {
			return true;
		}

		return $this->is_inside_previous_overnight_period(
			$now,
			$settings
		);
	}

	/**
	 * Check whether the current time falls inside an overnight period
	 * that began on the previous day.
	 *
	 * @param DateTimeImmutable $now      Current date and time.
	 * @param array             $settings Plugin settings.
	 *
	 * @return bool
	 */
	private function is_inside_previous_overnight_period(
		DateTimeImmutable $now,
		array $settings
	) {
		$timezone  = $now->getTimezone();
		$yesterday = $now->modify( '-1 day' );
		$day       = strtolower( $yesterday->format( 'l' ) );
		$hours     = $settings['hours'][ $day ] ?? null;

		if (
			empty( $hours['enabled'] ) ||
			$hours['close'] >= $hours['open']
		) {
			return false;
		}

		$open = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i',
			$yesterday->format( 'Y-m-d' ) . ' ' . $hours['open'],
			$timezone
		);

		$close = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i',
			$now->format( 'Y-m-d' ) . ' ' . $hours['close'],
			$timezone
		);

		return (
			$open &&
			$close &&
			$now >= $open &&
			$now < $close
		);
	}

	/**
	 * Build the current status response.
	 *
	 * @param bool|null $is_open Optional open state.
	 *
	 * @return array
	 */
	public function build_payload( $is_open = null ) {
		$settings = $this->get_settings();
		$is_open  = is_bool( $is_open )
			? $is_open
			: $this->get_status();

		if ( $is_open ) {
			$prefix = $settings['open_text'];

			$cta = str_replace(
				'{number}',
				$settings['phone_display'],
				$settings['open_cta']
			);

			$url   = 'tel:' . $settings['phone_link'];
			$state = 'open';
		} else {
			$prefix = $settings['closed_text'];
			$cta    = $settings['closed_cta'];
			$url    = $settings['contact_url'];
			$state  = 'closed';
		}

		return array(
			'is_open' => $is_open,
			'state'   => $state,
			'prefix'  => $prefix,
			'cta'     => $cta,
			'url'     => $url,
		);
	}

	/**
	 * Return the live status through the REST API.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_status() {
		return rest_ensure_response( $this->build_payload() );
	}

	/**
	 * Render the opening status.
	 *
	 * @param array $atts Shortcode or widget attributes.
	 *
	 * @return string
	 */
	public function render( $atts = array() ) {
		$this->mark_assets_needed();

		$settings = $this->get_settings();

		$atts = shortcode_atts(
			array(
				'class'      => '',
				'bare'       => 'no',
				'full_width' => 'yes',
			),
			$atts,
			'opening_status'
		);

		$payload = $this->build_payload();

		$classes = array(
			'mos-opening-status',
			'mos-state-' . $payload['state'],
			'mos-align-' . $settings['align'],
		);

		if ( 'yes' === strtolower( $atts['bare'] ) ) {
			$classes[] = 'mos-bare';
		}

		if ( 'yes' === strtolower( $atts['full_width'] ) ) {
			$classes[] = 'mos-full-width';
		}

		if ( ! empty( $atts['class'] ) ) {
			$custom_classes = preg_split(
				'/\s+/',
				sanitize_text_field( $atts['class'] )
			);

			foreach ( $custom_classes as $custom_class ) {
				$custom_class = sanitize_html_class( $custom_class );

				if ( $custom_class ) {
					$classes[] = $custom_class;
				}
			}
		}

		/*
		 * The background is explicitly transparent.
		 * This allows the Kadence header row or section to control it.
		 */
		$style = sprintf(
			'--mos-bg:transparent;--mos-text:%1$s;--mos-accent:%2$s;--mos-font-size:%3$spx;--mos-padding-y:%4$spx;',
			esc_attr( $settings['text_colour'] ),
			esc_attr( $settings['accent_colour'] ),
			esc_attr( $settings['font_size'] ),
			esc_attr( $settings['padding_y'] )
		);

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( implode( ' ', array_unique( $classes ) ) ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
			data-mos-opening-status
			data-refresh="<?php echo esc_attr( $settings['refresh'] ); ?>"
		>
			<div class="mos-opening-status__inner">

				<span
					class="mos-opening-status__dot"
					aria-hidden="true"
				></span>

				<span class="mos-opening-status__prefix">
					<?php echo esc_html( $payload['prefix'] ); ?>
				</span>

				<a
					class="mos-opening-status__link"
					href="<?php echo esc_url( $payload['url'] ); ?>"
				>
					<?php echo esc_html( $payload['cta'] ); ?>
				</a>

			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function shortcode( $atts ) {
		return $this->render( $atts );
	}

	/**
	 * Output the settings page.
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();

		$days = array(
			'monday'    => 'Monday',
			'tuesday'   => 'Tuesday',
			'wednesday' => 'Wednesday',
			'thursday'  => 'Thursday',
			'friday'    => 'Friday',
			'saturday'  => 'Saturday',
			'sunday'    => 'Sunday',
		);
		?>
		<div class="wrap">

			<h1>Opening Status</h1>

			<p>
				Use <code>[opening_status]</code> in a shortcode block,
				header element or widget area.
			</p>

			<p>
				The background colour is controlled by the Kadence header row
				or section containing the shortcode.
			</p>

			<form method="post" action="options.php">

				<?php settings_fields( 'mos_settings_group' ); ?>

				<h2>Contact details</h2>

				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">
							<label for="mos-phone-display">
								Displayed phone number
							</label>
						</th>

						<td>
							<input
								class="regular-text"
								id="mos-phone-display"
								name="<?php echo esc_attr( self::OPTION ); ?>[phone_display]"
								value="<?php echo esc_attr( $settings['phone_display'] ); ?>"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-phone-link">
								Telephone link number
							</label>
						</th>

						<td>
							<input
								class="regular-text"
								id="mos-phone-link"
								name="<?php echo esc_attr( self::OPTION ); ?>[phone_link]"
								value="<?php echo esc_attr( $settings['phone_link'] ); ?>"
							>

							<p class="description">
								Numbers and an optional leading + only.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-contact-url">
								Contact page or form URL
							</label>
						</th>

						<td>
							<input
								class="regular-text"
								type="text"
								id="mos-contact-url"
								name="<?php echo esc_attr( self::OPTION ); ?>[contact_url]"
								value="<?php echo esc_attr( $settings['contact_url'] ); ?>"
								placeholder="/contact/"
							>
						</td>
					</tr>

				</table>

				<h2>Messages</h2>

				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">
							<label for="mos-open-text">
								Open status text
							</label>
						</th>

						<td>
							<input
								class="regular-text"
								id="mos-open-text"
								name="<?php echo esc_attr( self::OPTION ); ?>[open_text]"
								value="<?php echo esc_attr( $settings['open_text'] ); ?>"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-open-cta">
								Open link text
							</label>
						</th>

						<td>
							<input
								class="regular-text"
								id="mos-open-cta"
								name="<?php echo esc_attr( self::OPTION ); ?>[open_cta]"
								value="<?php echo esc_attr( $settings['open_cta'] ); ?>"
							>

							<p class="description">
								Use <code>{number}</code> where the displayed
								phone number should appear.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-closed-text">
								Closed status text
							</label>
						</th>

						<td>
							<input
								class="regular-text"
								id="mos-closed-text"
								name="<?php echo esc_attr( self::OPTION ); ?>[closed_text]"
								value="<?php echo esc_attr( $settings['closed_text'] ); ?>"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-closed-cta">
								Closed link text
							</label>
						</th>

						<td>
							<input
								class="regular-text"
								id="mos-closed-cta"
								name="<?php echo esc_attr( self::OPTION ); ?>[closed_cta]"
								value="<?php echo esc_attr( $settings['closed_cta'] ); ?>"
							>
						</td>
					</tr>

				</table>

				<h2>Opening hours</h2>

				<table
					class="widefat striped"
					style="max-width: 760px;"
				>
					<thead>
						<tr>
							<th>Day</th>
							<th>Open this day</th>
							<th>Opening time</th>
							<th>Closing time</th>
						</tr>
					</thead>

					<tbody>

						<?php foreach ( $days as $key => $label ) : ?>

							<tr>

								<td>
									<strong>
										<?php echo esc_html( $label ); ?>
									</strong>
								</td>

								<td>
									<label>
										<input
											type="checkbox"
											name="<?php echo esc_attr( self::OPTION ); ?>[hours][<?php echo esc_attr( $key ); ?>][enabled]"
											value="1"
											<?php checked( ! empty( $settings['hours'][ $key ]['enabled'] ) ); ?>
										>
										Open
									</label>
								</td>

								<td>
									<input
										type="time"
										name="<?php echo esc_attr( self::OPTION ); ?>[hours][<?php echo esc_attr( $key ); ?>][open]"
										value="<?php echo esc_attr( $settings['hours'][ $key ]['open'] ); ?>"
									>
								</td>

								<td>
									<input
										type="time"
										name="<?php echo esc_attr( self::OPTION ); ?>[hours][<?php echo esc_attr( $key ); ?>][close]"
										value="<?php echo esc_attr( $settings['hours'][ $key ]['close'] ); ?>"
									>
								</td>

							</tr>

						<?php endforeach; ?>

					</tbody>
				</table>

				<p class="description">
					Closing times after midnight are supported. For example,
					17:00 to 01:00.
				</p>

				<h2>Behaviour</h2>

				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">
							<label for="mos-timezone">
								Timezone
							</label>
						</th>

						<td>
							<select
								id="mos-timezone"
								name="<?php echo esc_attr( self::OPTION ); ?>[timezone]"
							>
								<?php
								echo wp_timezone_choice(
									$settings['timezone'],
									get_user_locale()
								);
								?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-override">
								Manual override
							</label>
						</th>

						<td>
							<select
								id="mos-override"
								name="<?php echo esc_attr( self::OPTION ); ?>[override]"
							>
								<option
									value="auto"
									<?php selected( $settings['override'], 'auto' ); ?>
								>
									Automatic
								</option>

								<option
									value="open"
									<?php selected( $settings['override'], 'open' ); ?>
								>
									Force open
								</option>

								<option
									value="closed"
									<?php selected( $settings['override'], 'closed' ); ?>
								>
									Force closed
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-refresh">
								Live refresh interval
							</label>
						</th>

						<td>
							<input
								type="number"
								min="30"
								max="3600"
								id="mos-refresh"
								name="<?php echo esc_attr( self::OPTION ); ?>[refresh]"
								value="<?php echo esc_attr( $settings['refresh'] ); ?>"
							>
							seconds

							<p class="description">
								This keeps the status accurate even when the
								page is cached.
							</p>
						</td>
					</tr>

				</table>

				<h2>Appearance</h2>

				<p>
					The background colour is set within the Kadence Advanced
					Header. Use these options to make the status readable
					against light or dark header backgrounds.
				</p>

				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">
							Text colour
						</th>

						<td>
							<label>
								<input
									type="color"
									name="<?php echo esc_attr( self::OPTION ); ?>[text_colour]"
									value="<?php echo esc_attr( $settings['text_colour'] ); ?>"
								>
								Status message and link
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							Status dot colour
						</th>

						<td>
							<label>
								<input
									type="color"
									name="<?php echo esc_attr( self::OPTION ); ?>[accent_colour]"
									value="<?php echo esc_attr( $settings['accent_colour'] ); ?>"
								>
								Small open or closed indicator
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-align">
								Alignment
							</label>
						</th>

						<td>
							<select
								id="mos-align"
								name="<?php echo esc_attr( self::OPTION ); ?>[align]"
							>
								<option
									value="left"
									<?php selected( $settings['align'], 'left' ); ?>
								>
									Left
								</option>

								<option
									value="center"
									<?php selected( $settings['align'], 'center' ); ?>
								>
									Centre
								</option>

								<option
									value="right"
									<?php selected( $settings['align'], 'right' ); ?>
								>
									Right
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-font-size">
								Font size
							</label>
						</th>

						<td>
							<input
								type="number"
								min="10"
								max="24"
								id="mos-font-size"
								name="<?php echo esc_attr( self::OPTION ); ?>[font_size]"
								value="<?php echo esc_attr( $settings['font_size'] ); ?>"
							>
							px
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mos-padding-y">
								Vertical spacing
							</label>
						</th>

						<td>
							<input
								type="number"
								min="0"
								max="30"
								id="mos-padding-y"
								name="<?php echo esc_attr( self::OPTION ); ?>[padding_y]"
								value="<?php echo esc_attr( $settings['padding_y'] ); ?>"
							>
							px

							<p class="description">
								Leave this at 0 when Kadence controls the
								height and padding of the header row.
							</p>
						</td>
					</tr>

				</table>

				<?php submit_button(); ?>

			</form>

			<hr>

			<h2>Shortcode</h2>

			<p>
				For a Kadence Advanced Header row, use:
			</p>

			<p>
				<code>[opening_status]</code>
			</p>

			<p>
				The shortcode is transparent, so the Kadence row background
				will show behind it.
			</p>

			<p>
				You can also add your own class:
			</p>

			<p>
				<code>[opening_status class="my-custom-class"]</code>
			</p>

		</div>
		<?php
	}
}

/**
 * Opening Status widget.
 */
class MOS_Opening_Status_Widget extends WP_Widget {

	/**
	 * Set up the widget.
	 */
	public function __construct() {
		parent::__construct(
			'mos_opening_status_widget',
			'Opening Status',
			array(
				'description' => 'Displays the current open or closed message.',
			)
		);
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args     Widget wrapper arguments.
	 * @param array $instance Widget settings.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		echo Meridian_Opening_Status::instance()->render(
			array(
				'class'      => $instance['class'] ?? '',
				'bare'       => ! empty( $instance['bare'] )
					? 'yes'
					: 'no',
				'full_width' => ! empty( $instance['full_width'] )
					? 'yes'
					: 'no',
			)
		);

		echo $args['after_widget'];
	}

	/**
	 * Widget admin form.
	 *
	 * @param array $instance Widget settings.
	 */
	public function form( $instance ) {
		$class = $instance['class'] ?? '';

		$bare = ! empty( $instance['bare'] );

		$full_width = ! array_key_exists( 'full_width', $instance ) ||
			! empty( $instance['full_width'] );
		?>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'class' ) ); ?>">
				Extra CSS class
			</label>

			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'class' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'class' ) ); ?>"
				value="<?php echo esc_attr( $class ); ?>"
			>
		</p>

		<p>
			<label>
				<input
					type="checkbox"
					name="<?php echo esc_attr( $this->get_field_name( 'bare' ) ); ?>"
					value="1"
					<?php checked( $bare ); ?>
				>
				Bare mode
			</label>
		</p>

		<p>
			<label>
				<input
					type="checkbox"
					name="<?php echo esc_attr( $this->get_field_name( 'full_width' ) ); ?>"
					value="1"
					<?php checked( $full_width ); ?>
				>
				Full width
			</label>
		</p>

		<?php
	}

	/**
	 * Save widget settings.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'class' => sanitize_text_field(
				$new_instance['class'] ?? ''
			),
			'bare' => empty( $new_instance['bare'] )
				? 0
				: 1,
			'full_width' => empty( $new_instance['full_width'] )
				? 0
				: 1,
		);
	}
}

Meridian_Opening_Status::instance();