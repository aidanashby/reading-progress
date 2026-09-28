<?php
/**
 * Plugin Name:       Reading Progress
 * Description:        Reading time shortcode and a scroll-driven reading progress bar. Adds no styles of its own beyond what the bar needs to function.
 * Version:           0.1.2
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:            Aidan Ashby
 * License:           GPL-2.0-or-later
 * Text Domain:       reading-progress
 * Update URI:        https://github.com/aidanashby/reading-progress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'READING_PROGRESS_VERSION', '0.1.2' );
define( 'READING_PROGRESS_FILE', __FILE__ );
define( 'READING_PROGRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'READING_PROGRESS_OPTION', 'reading_progress_settings' );

/**
 * GitHub-based update checker.
 *
 * Activates once tagged releases exist at the repo below. Harmless before then:
 * the checker simply finds no release and reports no update available.
 * Upload a release asset named reading-progress.zip, or let it use the source zip.
 */
add_action( 'plugins_loaded', 'reading_progress_init_updater' );
function reading_progress_init_updater() {
	$loader = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $loader ) ) {
		return;
	}
	require_once $loader;

	$checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/aidanashby/reading-progress/',
		READING_PROGRESS_FILE,
		'reading-progress'
	);

	$api = $checker->getVcsApi();
	if ( method_exists( $api, 'enableReleaseAssets' ) ) {
		$api->enableReleaseAssets();
	}
}

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

/**
 * Defaults. Also the shape of the stored option.
 *
 * @return array
 */
function reading_progress_defaults() {
	return array(
		'wpm'              => 250,
		'label'            => '',
		'postfix'          => 'minutes reading time',
		'postfix_singular' => 'minute reading time',
		'bar_orientation'  => 'horizontal',
		'bar_thickness'    => 4,
		'bar_color'        => '#000000',
		'bar_selector'     => '#main-content .et_builder_inner_content .et_pb_section_0_tb_body',
	);
}

/**
 * Stored settings merged over defaults.
 *
 * @return array
 */
function reading_progress_get_settings() {
	$saved = get_option( READING_PROGRESS_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, reading_progress_defaults() );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'reading_progress_action_links' );
/**
 * Add a "Settings" link under the plugin on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function reading_progress_action_links( $links ) {
	$settings = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=reading-progress' ) ),
		esc_html__( 'Settings', 'reading-progress' )
	);
	array_unshift( $links, $settings );
	return $links;
}

add_action( 'admin_menu', 'reading_progress_add_settings_page' );
function reading_progress_add_settings_page() {
	add_options_page(
		__( 'Reading Progress', 'reading-progress' ),
		__( 'Reading Progress', 'reading-progress' ),
		'manage_options',
		'reading-progress',
		'reading_progress_render_settings_page'
	);
}

add_action( 'admin_init', 'reading_progress_register_settings' );
function reading_progress_register_settings() {
	register_setting(
		'reading_progress',
		READING_PROGRESS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'reading_progress_sanitize_settings',
			'default'           => reading_progress_defaults(),
		)
	);
}

/**
 * Sanitise the whole settings array on save.
 *
 * @param mixed $input Raw submitted value.
 * @return array
 */
function reading_progress_sanitize_settings( $input ) {
	$defaults = reading_progress_defaults();
	$out      = reading_progress_get_settings();

	if ( ! is_array( $input ) ) {
		return $out;
	}

	if ( isset( $input['wpm'] ) ) {
		$wpm        = absint( $input['wpm'] );
		$out['wpm'] = $wpm > 0 ? $wpm : $defaults['wpm'];
	}

	if ( isset( $input['label'] ) ) {
		$out['label'] = sanitize_text_field( $input['label'] );
	}
	if ( isset( $input['postfix'] ) ) {
		$out['postfix'] = sanitize_text_field( $input['postfix'] );
	}
	if ( isset( $input['postfix_singular'] ) ) {
		$out['postfix_singular'] = sanitize_text_field( $input['postfix_singular'] );
	}

	if ( isset( $input['bar_orientation'] ) ) {
		$out['bar_orientation'] = in_array( $input['bar_orientation'], array( 'horizontal', 'vertical' ), true )
			? $input['bar_orientation']
			: $defaults['bar_orientation'];
	}

	if ( isset( $input['bar_thickness'] ) ) {
		$thickness            = absint( $input['bar_thickness'] );
		$out['bar_thickness'] = $thickness > 0 ? $thickness : $defaults['bar_thickness'];
	}

	if ( isset( $input['bar_color'] ) ) {
		$color            = sanitize_hex_color( $input['bar_color'] );
		$out['bar_color'] = $color ? $color : $defaults['bar_color'];
	}

	if ( isset( $input['bar_selector'] ) ) {
		$selector            = sanitize_text_field( $input['bar_selector'] );
		$out['bar_selector'] = '' !== $selector ? $selector : $defaults['bar_selector'];
	}

	return $out;
}

add_action( 'admin_enqueue_scripts', 'reading_progress_admin_assets' );
function reading_progress_admin_assets( $hook ) {
	if ( 'settings_page_reading-progress' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_style(
		'reading-progress-admin',
		READING_PROGRESS_URL . 'assets/admin.css',
		array(),
		READING_PROGRESS_VERSION
	);
	wp_add_inline_script(
		'wp-color-picker',
		'jQuery(function($){ $(".reading-progress-color").wpColorPicker(); });'
	);
}

function reading_progress_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s   = reading_progress_get_settings();
	$opt = esc_attr( READING_PROGRESS_OPTION );
	?>
	<div class="wrap reading-progress-settings">
		<h1><?php esc_html_e( 'Reading Progress', 'reading-progress' ); ?></h1>
		<p class="rp-intro">
			<?php esc_html_e( 'Add the reading time with the [reading_time] shortcode, and the progress bar with the [reading_progress_bar] shortcode. The plugin adds no styling of its own beyond what the bar needs to work. Every setting below can be overridden per shortcode — see the reference at the bottom.', 'reading-progress' ); ?>
		</p>

		<form action="options.php" method="post">
			<?php settings_fields( 'reading_progress' ); ?>

			<div class="rp-card">
				<h2><?php esc_html_e( 'Reading time', 'reading-progress' ); ?></h2>
				<p class="rp-hint"><?php esc_html_e( 'Outputs: label + number + postfix, inside a span with class "reading-progress-time". Counts the post content only, not the title.', 'reading-progress' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rp-wpm"><?php esc_html_e( 'Words per minute', 'reading-progress' ); ?></label></th>
						<td><input name="<?php echo $opt; ?>[wpm]" id="rp-wpm" type="number" min="1" step="1" value="<?php echo esc_attr( $s['wpm'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="rp-label"><?php esc_html_e( 'Reading time label', 'reading-progress' ); ?></label></th>
						<td>
							<input name="<?php echo $opt; ?>[label]" id="rp-label" type="text" value="<?php echo esc_attr( $s['label'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Reading time: ', 'reading-progress' ); ?>">
							<p class="description"><?php esc_html_e( 'Text before the number. Leave blank for none. Include a trailing space if you want one.', 'reading-progress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rp-postfix"><?php esc_html_e( 'Reading time postfix', 'reading-progress' ); ?></label></th>
						<td>
							<input name="<?php echo $opt; ?>[postfix]" id="rp-postfix" type="text" value="<?php echo esc_attr( $s['postfix'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Text after the number for 2 or more minutes. Shown as: 11 minutes reading time', 'reading-progress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rp-postfix-singular"><?php esc_html_e( 'Reading time postfix (singular)', 'reading-progress' ); ?></label></th>
						<td>
							<input name="<?php echo $opt; ?>[postfix_singular]" id="rp-postfix-singular" type="text" value="<?php echo esc_attr( $s['postfix_singular'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Text after the number when it is exactly 1. Shown as: 1 minute reading time', 'reading-progress' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="rp-card">
				<h2><?php esc_html_e( 'Reading progress bar', 'reading-progress' ); ?></h2>
				<p class="rp-hint"><?php esc_html_e( 'Outputs a div with class "reading-progress-bar" containing a "reading-progress-bar__fill" div. Place it wherever you want the bar to sit. Position and any track styling are up to your theme.', 'reading-progress' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Orientation', 'reading-progress' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo $opt; ?>[bar_orientation]" value="horizontal" <?php checked( $s['bar_orientation'], 'horizontal' ); ?>> <?php esc_html_e( 'Horizontal', 'reading-progress' ); ?></label><br>
							<label><input type="radio" name="<?php echo $opt; ?>[bar_orientation]" value="vertical" <?php checked( $s['bar_orientation'], 'vertical' ); ?>> <?php esc_html_e( 'Vertical', 'reading-progress' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rp-thickness"><?php esc_html_e( 'Thickness (px)', 'reading-progress' ); ?></label></th>
						<td>
							<input name="<?php echo $opt; ?>[bar_thickness]" id="rp-thickness" type="number" min="1" step="1" value="<?php echo esc_attr( $s['bar_thickness'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Height when horizontal, width when vertical.', 'reading-progress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rp-color"><?php esc_html_e( 'Colour', 'reading-progress' ); ?></label></th>
						<td><input name="<?php echo $opt; ?>[bar_color]" id="rp-color" type="text" value="<?php echo esc_attr( $s['bar_color'] ); ?>" class="reading-progress-color" data-default-color="#000000"></td>
					</tr>
					<tr>
						<th scope="row"><label for="rp-selector"><?php esc_html_e( 'Tracked element selector', 'reading-progress' ); ?></label></th>
						<td>
							<input name="<?php echo $opt; ?>[bar_selector]" id="rp-selector" type="text" value="<?php echo esc_attr( $s['bar_selector'] ); ?>" class="large-text code">
							<p class="description"><?php esc_html_e( 'The bar tracks scroll progress through the first element matching this selector.', 'reading-progress' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>

		<div class="rp-card rp-reference">
			<h2><?php esc_html_e( 'Shortcode reference', 'reading-progress' ); ?></h2>
			<p class="rp-hint"><?php esc_html_e( 'Attributes are optional. Anything you leave out falls back to the settings above.', 'reading-progress' ); ?></p>

			<h3><code>[reading_time]</code></h3>
			<ul class="rp-attrs">
				<li><code>wpm</code> &mdash; <?php esc_html_e( 'words per minute', 'reading-progress' ); ?></li>
				<li><code>label</code> &mdash; <?php esc_html_e( 'text before the number', 'reading-progress' ); ?></li>
				<li><code>postfix</code> &mdash; <?php esc_html_e( 'text after the number (2+ minutes)', 'reading-progress' ); ?></li>
				<li><code>postfix_singular</code> &mdash; <?php esc_html_e( 'text after the number (exactly 1)', 'reading-progress' ); ?></li>
			</ul>
			<p><code>[reading_time wpm="300" label="Reading time: " postfix="min read" postfix_singular="min read"]</code></p>

			<h3><code>[reading_progress_bar]</code></h3>
			<ul class="rp-attrs">
				<li><code>orientation</code> &mdash; <code>horizontal</code> <?php esc_html_e( 'or', 'reading-progress' ); ?> <code>vertical</code></li>
				<li><code>thickness</code> &mdash; <?php esc_html_e( 'bar thickness in pixels', 'reading-progress' ); ?></li>
				<li><code>color</code> &mdash; <?php esc_html_e( 'hex colour, e.g.', 'reading-progress' ); ?> <code>#c2185b</code></li>
				<li><code>selector</code> &mdash; <?php esc_html_e( 'CSS selector of the element to track', 'reading-progress' ); ?></li>
			</ul>
			<p><code>[reading_progress_bar orientation="vertical" thickness="6" color="#c2185b"]</code></p>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Feature 1: reading time shortcode
 * ---------------------------------------------------------------------- */

add_shortcode( 'reading_time', 'reading_progress_reading_time_shortcode' );
/**
 * [reading_time] — outputs "label + n + postfix" in a targetable span.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function reading_progress_reading_time_shortcode( $atts ) {
	$post = get_post();
	if ( ! $post ) {
		return '';
	}

	$s = reading_progress_get_settings();

	$atts = shortcode_atts(
		array(
			'wpm'              => $s['wpm'],
			'label'            => $s['label'],
			'postfix'          => $s['postfix'],
			'postfix_singular' => $s['postfix_singular'],
		),
		$atts,
		'reading_time'
	);

	$wpm = absint( $atts['wpm'] );
	if ( $wpm < 1 ) {
		$wpm = 250;
	}

	$text  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words = str_word_count( $text );

	$minutes = (int) round( $words / $wpm );
	if ( $minutes < 1 ) {
		$minutes = 1;
	}

	$postfix = ( 1 === $minutes ) ? $atts['postfix_singular'] : $atts['postfix'];

	return sprintf(
		'<span class="reading-progress-time">%s%s %s</span>',
		esc_html( $atts['label'] ),
		esc_html( number_format_i18n( $minutes ) ),
		esc_html( $postfix )
	);
}

/* -------------------------------------------------------------------------
 * Feature 2: reading progress bar shortcode
 * ---------------------------------------------------------------------- */

add_action( 'init', 'reading_progress_register_bar_script' );
function reading_progress_register_bar_script() {
	wp_register_script(
		'reading-progress-bar',
		READING_PROGRESS_URL . 'assets/progress-bar.js',
		array(),
		READING_PROGRESS_VERSION,
		true
	);
}

add_shortcode( 'reading_progress_bar', 'reading_progress_bar_shortcode' );
/**
 * [reading_progress_bar] — outputs the bar markup and loads the tracking script.
 *
 * The only inline styles are the ones the bar needs to be visible and animate:
 * the fill's size axis, colour, transition, and transform-origin. Everything
 * else (position, track background, radius) is left to the theme.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function reading_progress_bar_shortcode( $atts ) {
	$s = reading_progress_get_settings();

	$atts = shortcode_atts(
		array(
			'orientation' => $s['bar_orientation'],
			'thickness'   => $s['bar_thickness'],
			'color'       => $s['bar_color'],
			'selector'    => $s['bar_selector'],
		),
		$atts,
		'reading_progress_bar'
	);

	$orientation = in_array( $atts['orientation'], array( 'horizontal', 'vertical' ), true )
		? $atts['orientation']
		: 'horizontal';
	$thickness = absint( $atts['thickness'] );
	if ( $thickness < 1 ) {
		$thickness = 4;
	}
	$color = sanitize_hex_color( $atts['color'] );
	if ( ! $color ) {
		$color = '#000000';
	}
	$selector = sanitize_text_field( $atts['selector'] );
	if ( '' === $selector ) {
		$selector = $s['bar_selector'];
	}

	wp_enqueue_script( 'reading-progress-bar' );

	if ( 'vertical' === $orientation ) {
		$fill_style = sprintf(
			'width:%dpx;height:100%%;background:%s;transform:scaleY(0);transform-origin:top;transition:transform .1s linear;',
			$thickness,
			esc_attr( $color )
		);
	} else {
		$fill_style = sprintf(
			'height:%dpx;width:100%%;background:%s;transform:scaleX(0);transform-origin:left;transition:transform .1s linear;',
			$thickness,
			esc_attr( $color )
		);
	}

	return sprintf(
		'<div class="reading-progress-bar" data-rp-selector="%1$s" data-rp-orientation="%2$s"><div class="reading-progress-bar__fill" style="%3$s"></div></div>',
		esc_attr( $selector ),
		esc_attr( $orientation ),
		$fill_style
	);
}
