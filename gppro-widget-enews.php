<?php
/*
Plugin Name: Genesis Design Palette Pro - eNews Widget
Plugin URI: http://genesisdesignpro.com
Description: Genesis Design Palette Pro add-on for styling the Genesis eNews Extended widget.
Author: Reaktiv Studios
Version: 1.0.4
Requires at least: 3.8
Author URI: http://reaktivstudios.com
*/

/*
	Copyright 2013 Andrew Norcross, Josh Eaton

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; version 2 of the License (GPL v2) only.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'GPWEN_BASE' ) ) {
	define( 'GPWEN_BASE', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'GPWEN_DIR' ) ) {
	define( 'GPWEN_DIR', dirname( __FILE__ ) );
}

if ( ! defined( 'GPWEN_VER' ) ) {
	define( 'GPWEN_VER', '1.0.4' );
}

class GP_Pro_Widget_Enews {

	/**
	 * Static property to hold our singleton instance
	 * @var GP_Pro_Widget_Enews
	 */
	static $instance = false;

	/**
	 * This is our constructor
	 *
	 * @return GP_Pro_Widget_Enews
	 */
	private function __construct() {

		// general backend
		add_action( 'plugins_loaded',                   array( $this, 'textdomain'                  )           );
		add_action( 'admin_notices',                    array( $this, 'gppro_active_check'          ),  10      );
		add_action( 'admin_notices',                    array( $this, 'gppro_version_check'         ),  10      );
		add_action( 'admin_notices',                    array( $this, 'enews_active_check'          ),  10      );

		// GP Pro specific
		add_filter( 'gppro_admin_block_add',            array( $this, 'genesis_widgets_block'       ),  61      );
		add_filter( 'gppro_sections',                   array( $this, 'genesis_widgets_section'     ),  10, 2   );

		// Defaults
		add_filter( 'gppro_set_defaults',               array( $this, 'enews_defaults_base'         ),  30      );

		// Modify defaults if known child theme is set
		add_filter( 'gppro_enews_set_defaults',         array( $this, 'enews_defaults_child_themes' ),  10      );

		// Override default checks
		add_filter( 'gppro_compare_default',            array( $this, 'override_default_check'      )           );

		// Check for placeholder text changes.
		add_filter( 'gppro_css_builder',                array( $this, 'placeholder_text_filter'     ),  50, 3   );

		// activation hooks
		register_deactivation_hook( __FILE__,           array( $this, 'enews_clear_check'           )           );
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * returns it.
	 *
	 * @return $instance
	 */
	public static function getInstance() {

		// check for self instance
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		// return the instance
		return self::$instance;
	}

	/**
	 * load textdomain
	 *
	 * @return
	 */
	public function textdomain() {
		load_plugin_textdomain( 'gpwen', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Set widget dependency data.
	 *
	 * @param  string $key      optional key to return
	 *
	 * @return array/strng      either the individual string, or the entire array
	 */
	public function plugin_info( $key = '' ) {

		// Build the array data.
		$data   = array(
			'name'  => __( 'Genesis eNews Extended', 'gpwen' ),
			'file'  => 'genesis-enews-extended/plugin.php',
			'key'   => 'genesis-enews',
		);

		// Return all of it if requested.
		if ( empty( $key ) ) {
			return $data;
		}

		// Return the specific key, or false if that key does not exist.
		return ! empty( $key ) && array_key_exists( $key, $data ) ? $data[ $key ] : false;
	}

	/**
	 * check for GP Pro being active
	 *
	 * @return GP_Pro_Widget_Enews
	 */
	public function gppro_active_check() {

		// Confirm we are on the correct screen to check.
		if ( class_exists( 'GP_Pro_Utilities' ) && false === $check = GP_Pro_Utilities::check_current_dpp_screen( 'plugins.php' ) ) {
			return;
		}

		// Confirm that DPP isn't active before proceeding.
		if ( class_exists( 'Genesis_Palette_Pro' ) && false !== $active = Genesis_Palette_Pro::check_active() ) {
			return;
		}

		// DPP is not active. show message.
		echo '<div id="message" class="error notice is-dismissible"><p><strong>' . __( sprintf( 'This plugin requires Genesis Design Palette Pro to function and cannot be activated.' ), 'gpwen' ) . '</strong></p></div>';

		// Hide activation method.
		unset( $_GET['activate'] );

		// Deactivate the plugin.
		deactivate_plugins( plugin_basename( __FILE__ ) );

		// And finish.
		return;
	}

	/**
	 * Check for valid Design Palette Pro Version
	 *
	 * Requires version 1.3.0+
	 *
	 * @since 1.0.1
	 */
	public function gppro_version_check() {

		// Fetch our version number for DPP.
		$plugin = defined( 'GPP_VER' ) ? GPP_VER : 0;

		// Check against our version of DPP.
		if ( version_compare( $plugin, '1.3.0', '>=' ) >= 0 ) {
			return;
		}

		// Output the message regarding updates.
		printf(
			'<div class="updated"><p>' . esc_html__( 'Please upgrade %2$sDesign Palette Pro to version 1.3.0 or greater%3$s to continue using the %1$s extension.', 'gppro' ) . '</p></div>',
			'<strong>' . 'Genesis Design Palette Pro - eNews Widget' . '</strong>',
			'<a href="' . esc_url( admin_url( 'plugins.php?plugin_status=upgrade' ) ) . '">',
			'</a>'
		);
	}

	/**
	 * check for correct child theme being active
	 *
	 * @return notice
	 */
	public function enews_active_check() {

		// Confirm we are on the correct screen to check.
		if ( class_exists( 'GP_Pro_Utilities' ) && false === $check = GP_Pro_Utilities::check_current_dpp_screen() ) {
			return;
		}

		// get our Genesis Plugin dependency name
		$info   = $this->plugin_info();

		// Bail without our file, name, or key.
		if ( empty( $info['file'] ) || empty( $info['name'] ) || empty( $info['key'] ) ) {
			return;
		}

		// set the file and name
		$file   = esc_attr( $info['file'] );
		$name   = esc_attr( $info['name'] );
		$key    = esc_attr( $info['key'] );

		// Check our ignore flag.
		if ( class_exists( 'GP_Pro_Helper' ) && false !== $ignore = GP_Pro_Helper::get_single_option( 'gppro-warning-' . $key, '', false ) ) {
			return;
		}

		// check if plugin is active, display warning
		if ( false === $active = is_plugin_active( $file ) ) {

			echo '<div id="message" class="error notice gppro-admin-warning gppro-admin-warning-' . $key . '"><p>';
			echo '<strong>' . __( 'Warning: You have the ' . $name . ' widget add-on enabled but do not have the ' . $name . ' plugin active.', 'gpwen' ) . '</strong>';
			echo '<span class="ignore" data-child="' . $key . '">' . __( 'Ignore this message', 'gpwen' ) . '</span>';
			echo '</p></div>';
		}
	}

	/**
	 * add block to side
	 *
	 * @return
	 */
	public function genesis_widgets_block( $blocks ) {

		// Only add the block if it's not already set (another Genesis widget plugin has already added it).
		if ( ! isset( $blocks['genesis-widgets'] ) ) {

			$blocks['genesis-widgets'] = array(
				'tab'       => __( 'Genesis Widgets', 'gpwen' ),
				'title'     => __( 'Genesis Widgets', 'gpwen' ),
				'slug'      => 'genesis_widgets',
			);
		}

		// Return all the blocks.
		return $blocks;
	}

	/**
	 * add section to side
	 *
	 * @return
	 */
	public function genesis_widgets_section( $sections, $class ) {

		$genesis_widgets    = array(

			'genesis-widget-setup'  => array(
				'headline'  => __( 'Genesis Widgets', 'gpwen' ),
				'intro'     => __( 'Target and style individual widgets such as eNews Extended', 'gpwen' ),
				'title'     => '',
				'data'      => '',
			),

			'section-break-enews-widget-general'    => array(
				'break' => array(
					'type'  => 'full',
					'title' => __( 'eNews Widget', 'gpwen' ),
				),
			),

			'enews-widget-general'  => array(
				'headline'  => __( 'eNews Widget', 'gpwen' ),
				'intro'     => __( 'Style subscription forms created with Genesis eNews Extended', 'gpwen' ),
				'title'     => 'General Colors',
				'data'      => array(
					'enews-widget-back' => array(
						'label'     => __( 'Background', 'gpwen' ),
						'input'     => 'color',
						'target'    => array( '.enews-widget', '.sidebar .enews-widget' ),
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'background-color'
					),
					'enews-widget-title-color'  => array(
						'label'     => __( 'Title Color', 'gpwen' ),
						'input'     => 'color',
						'target'    => '.enews-widget .enews .widget-title',
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'color'
					),
					'enews-widget-text-color'   => array(
						'label'     => __( 'Text Color', 'gpwen' ),
						'input'     => 'color',
						'target'    => '.enews-widget .enews',
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'color'
					),
				),
			),

			'enews-widget-typography'   => array(
				'title' => __( 'Before and After Text Typography', 'gpwen' ),
				'data'  => array(
					'enews-widget-gen-stack'    => array(
						'label'     => __( 'Font Stack', 'gpwen' ),
						'input'     => 'font-stack',
						'target'    => '.enews-widget p',
						'builder'   => 'GP_Pro_Builder::stack_css',
						'selector'  => 'font-family'
					),
					'enews-widget-gen-size' => array(
						'label'     => __( 'Font Size', 'gpwen' ),
						'input'     => 'font-size',
						'scale'     => 'text',
						'target'    => '.enews-widget p',
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'font-size',
					),
					'enews-widget-gen-weight'   => array(
						'label'     => __( 'Font Weight', 'gpwen' ),
						'input'     => 'font-weight',
						'target'    => '.enews-widget p',
						'builder'   => 'GP_Pro_Builder::number_css',
						'selector'  => 'font-weight',
						'tip'       => __( 'Certain fonts will not display every weight.', 'gpwen' )
					),
					'enews-widget-gen-transform'    => array(
						'label'     => __( 'Text Appearance', 'gpwen' ),
						'input'     => 'text-transform',
						'target'    => '.enews-widget p',
						'builder'   => 'GP_Pro_Builder::text_css',
						'selector'  => 'text-transform'
					),
					'enews-widget-gen-text-margin-bottom' => array(
						'label'     => __( 'Bottom Margin', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => '.enews-widget p',
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'margin-bottom',
						'min'       => '0',
						'max'       => '48',
						'step'      => '1'
					),
				),
			),

			'enews-widget-field-inputs' => array(
				'title'     => __( 'Field Inputs', 'gpwen' ),
				'data'      => array(

					'enews-widget-field-input-colors-divider' => array(
						'title'     => __( 'Colors', 'gpwen' ),
						'input'     => 'divider',
						'style'     => 'block-thin'
					),

					'enews-widget-field-input-back' => array(
						'label'     => __( 'Background', 'gpwen' ),
						'input'     => 'color',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'background-color'
					),
					'enews-widget-field-input-text-color'   => array(
						'label'     => __( 'Input Text', 'gpwen' ),
						'input'     => 'color',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'color'
					),
					'enews-widget-field-place-text-color'   => array( // Target and Builder removed on purpose.
						'label'     => __( 'Placeholder Text', 'gpwen' ),
						'input'     => 'color',
						'selector'  => 'color',
						'tip'       => __( 'Placeholder text color will not be viewable in the preview window until after settings are saved.', 'gpwen' )
					),

					'enews-widget-field-input-typography-divider' => array(
						'title'     => __( 'Typography', 'gpwen' ),
						'input'     => 'divider',
						'style'     => 'block-thin'
					),

					'enews-widget-field-input-stack'    => array(
						'label'     => __( 'Font Stack', 'gpwen' ),
						'input'     => 'font-stack',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::stack_css',
						'selector'  => 'font-family'
					),
					'enews-widget-field-input-size' => array(
						'label'     => __( 'Font Size', 'gpwen' ),
						'input'     => 'font-size',
						'scale'     => 'text',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'font-size'
					),
					'enews-widget-field-input-weight'   => array(
						'label'     => __( 'Font Weight', 'gpwen' ),
						'input'     => 'font-weight',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::number_css',
						'selector'  => 'font-weight',
						'tip'       => __( 'Certain fonts will not display every weight.', 'gpwen' )
					),
					'enews-widget-field-input-transform' => array(
						'label'     => __( 'Text Appearance', 'gpwen' ),
						'input'     => 'text-transform',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::text_css',
						'selector'  => 'text-transform'
					),

					'enews-widget-field-input-borders-divider' => array(
						'title'     => __( 'Borders', 'gpwen' ),
						'input'     => 'divider',
						'style'     => 'block-thin'
					),
					'enews-widget-field-input-border-color' => array(
						'label'     => __( 'Border Color', 'gpwen' ),
						'input'     => 'color',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'border-color',
					),
					'enews-widget-field-input-border-type'  => array(
						'label'     => __( 'Border Type', 'gpwen' ),
						'input'     => 'borders',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::text_css',
						'selector'  => 'border-style',
						'tip'       => __( 'Setting the type to "none" will remove the border completely.', 'gpwen' )
					),
					'enews-widget-field-input-border-width' => array(
						'label'     => __( 'Border Width', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'border-width',
						'min'       => '0',
						'max'       => '10',
						'step'      => '1'
					),
					'enews-widget-field-input-border-radius'    => array(
						'label'     => __( 'Border Radius', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'border-radius',
						'min'       => '0',
						'max'       => '16',
						'step'      => '1'
					),
					'enews-widget-field-input-border-color-focus'   => array(
						'label'     => __( 'Border Color', 'gpwen' ),
						'sub'       => __( 'Focus', 'gpwen' ),
						'input'     => 'color',
						'target'    => array( '.enews-widget input[type="text"]:focus', '.enews-widget input[type="email"]:focus' ),
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'border-color',
					),
					'enews-widget-field-input-border-type-focus'    => array(
						'label'     => __( 'Border Type', 'gpwen' ),
						'sub'       => __( 'Focus', 'gpwen' ),
						'input'     => 'borders',
						'target'    => array( '.enews-widget input[type="text"]:focus', '.enews-widget input[type="email"]:focus' ),
						'builder'   => 'GP_Pro_Builder::text_css',
						'selector'  => 'border-style',
						'tip'       => __( 'Setting the type to "none" will remove the border completely.', 'gpwen' )
					),
					'enews-widget-field-input-border-width-focus'   => array(
						'label'     => __( 'Border Width', 'gpwen' ),
						'sub'       => __( 'Focus', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => array( '.enews-widget input[type="text"]:focus', '.enews-widget input[type="email"]:focus' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'border-width',
						'min'       => '0',
						'max'       => '10',
						'step'      => '1'
					),


					'enews-widget-field-input-padding-margins-divider' => array(
						'title'     => __( 'Padding & Margin', 'gpwen' ),
						'input'     => 'divider',
						'style'     => 'block-thin'
					),
					'enews-widget-field-input-pad-top'  => array(
						'label'     => __( 'Top', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'padding-top',
						'min'       => '0',
						'max'       => '32',
						'step'      => '1'
					),
					'enews-widget-field-input-pad-bottom' => array(
						'label'     => __( 'Bottom', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'padding-bottom',
						'min'       => '0',
						'max'       => '32',
						'step'      => '1'
					),
					'enews-widget-field-input-pad-left' => array(
						'label'     => __( 'Left', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'padding-left',
						'min'       => '0',
						'max'       => '32',
						'step'      => '1'
					),
					'enews-widget-field-input-pad-right' => array(
						'label'     => __( 'Right', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'padding-right',
						'min'       => '0',
						'max'       => '32',
						'step'      => '1'
					),
					'enews-widget-field-input-margin-bottom' => array(
						'label'     => __( 'Bottom Margin', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'margin-bottom',
						'min'       => '0',
						'max'       => '48',
						'step'      => '1'
					),


					'enews-widget-field-input-box-shadow'   => array(
						'label'     => __( 'Box Shadow', 'gpwen' ),
						'input'     => 'radio',
						'options'   => array(
							array(
								'label' => __( 'Keep', 'gpwen' ),
								'value' => 'inherit',
							),
							array(
								'label' => __( 'Remove', 'gpwen' ),
								'value' => 'none'
							),
						),
						'target'    => array( '.enews-widget input[type="text"]', '.enews-widget input[type="email"]' ),
						'builder'   => 'GP_Pro_Builder::text_css',
						'selector'  => 'box-shadow'
					),
				),
			),

			'enews-widget-submit-button'    => array(
				'title'     => __( 'Submit Button', 'gpwen' ),
				'data'      => array(
					'enews-widget-button-colors-divider' => array(
						'title'     => __( 'Colors', 'gpwen' ),
						'input'     => 'divider',
						'style'     => 'block-thin'
					),
					'enews-widget-button-back'  => array(
						'label'     => __( 'Background', 'gpwen' ),
						'sub'       => __( 'Base', 'gpwen' ),
						'input'     => 'color',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'background-color'
					),
					'enews-widget-button-back-hov'  => array(
						'label'     => __( 'Background', 'gpwen' ),
						'sub'       => __( 'Hover', 'gpwen' ),
						'input'     => 'color',
						'target'    => array( '.enews-widget input:hover[type="submit"]', '.enews-widget input:focus[type="submit"]' ),
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'background-color'
					),
					'enews-widget-button-text-color'    => array(
						'label'     => __( 'Font Color', 'gpwen' ),
						'sub'       => __( 'Base', 'gpwen' ),
						'input'     => 'color',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'color'
					),
					'enews-widget-button-text-color-hov'    => array(
						'label'     => __( 'Font Color', 'gpwen' ),
						'sub'       => __( 'Hover', 'gpwen' ),
						'input'     => 'color',
						'target'    => array( '.enews-widget input:hover[type="submit"]', '.enews-widget input:focus[type="submit"]' ),
						'builder'   => 'GP_Pro_Builder::hexcolor_css',
						'selector'  => 'color'
					),


					'enews-widget-button-typography-divider' => array(
						'title'     => __( 'Typography', 'gpwen' ),
						'input'     => 'divider',
						'style'     => 'block-thin'
					),
					'enews-widget-button-stack' => array(
						'label'     => __( 'Font Stack', 'gpwen' ),
						'input'     => 'font-stack',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::stack_css',
						'selector'  => 'font-family'
					),
					'enews-widget-button-size'  => array(
						'label'     => __( 'Font Size', 'gpwen' ),
						'input'     => 'font-size',
						'scale'     => 'text',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'font-size'
					),
					'enews-widget-button-weight'    => array(
						'label'     => __( 'Font Weight', 'gpwen' ),
						'input'     => 'font-weight',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::number_css',
						'selector'  => 'font-weight',
						'tip'       => __( 'Certain fonts will not display every weight.', 'gpwen' )
					),
					'enews-widget-button-transform' => array(
						'label'     => __( 'Text Appearance', 'gpwen' ),
						'input'     => 'text-transform',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::text_css',
						'selector'  => 'text-transform'
					),


					'enews-widget-button-padding-divider' => array(
						'title'     => __( 'Padding & Margin', 'gpwen' ),
						'input'     => 'divider',
						'style'     => 'block-thin'
					),
					'enews-widget-button-pad-top'   => array(
						'label'     => __( 'Top', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'padding-top',
						'min'       => '0',
						'max'       => '32',
						'step'      => '1'
					),
					'enews-widget-button-pad-bottom'    => array(
						'label'     => __( 'Bottom', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'padding-bottom',
						'min'       => '0',
						'max'       => '32',
						'step'      => '1'
					),
					'enews-widget-button-pad-left'  => array(
						'label'     => __( 'Left', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'padding-left',
						'min'       => '0',
						'max'       => '32',
						'step'      => '1'
					),
					'enews-widget-button-pad-right' => array(
						'label'     => __( 'Right', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'padding-right',
						'min'       => '0',
						'max'       => '32',
						'step'      => '1'
					),
					'enews-widget-button-margin-bottom' => array(
						'label'     => __( 'Bottom Margin', 'gpwen' ),
						'input'     => 'spacing',
						'target'    => '.enews-widget input[type="submit"]',
						'builder'   => 'GP_Pro_Builder::px_css',
						'selector'  => 'margin-bottom',
						'min'       => '0',
						'max'       => '48',
						'step'      => '1'
					),
				),
			),
		); // end section

		// If another plugin has created the genesis widgets block, then merge ours in, otherwise we can set it.
		if ( isset( $sections['genesis_widgets'] )) {
			$sections['genesis_widgets'] = array_merge( $sections['genesis_widgets'], $genesis_widgets );
		} else {
			$sections['genesis_widgets'] = $genesis_widgets;
		}

		// return the sections
		return $sections;
	}

	/**
	 * set the defaults
	 *
	 * @param  [type] $defaults [description]
	 * @return [type]           [description]
	 */
	public function enews_defaults_base( $defaults ) {

		// General
		$defaults['enews-widget-back']                             = '#333333';
		$defaults['enews-widget-title-color']                      = '#ffffff'; // check default
		$defaults['enews-widget-text-color']                       = '#999999'; // check default

		// General Typography
		$defaults['enews-widget-gen-stack']                        = isset( $defaults['body-type-stack'] ) ? $defaults['body-type-stack']   : '';
		$defaults['enews-widget-gen-size']                         = '16';
		$defaults['enews-widget-gen-weight']                       = '300';
		$defaults['enews-widget-gen-transform']                    = 'none';
		$defaults['enews-widget-gen-text-margin-bottom']           = '24';

		// Field Inputs
		$defaults['enews-widget-field-input-back']                 = '#ffffff';
		$defaults['enews-widget-field-input-text-color']           = '#999999';
		$defaults['enews-widget-field-place-text-color']           = '#666666';
		$defaults['enews-widget-field-input-stack']                = isset( $defaults['body-type-stack'] ) ? $defaults['body-type-stack']   : '';
		$defaults['enews-widget-field-input-size']                 = '14';
		$defaults['enews-widget-field-input-weight']               = '300';
		$defaults['enews-widget-field-input-transform']            = 'none';
		$defaults['enews-widget-field-input-border-color']         = '#dddddd';
		$defaults['enews-widget-field-input-border-type']          = 'solid';
		$defaults['enews-widget-field-input-border-width']         = '1';
		$defaults['enews-widget-field-input-border-radius']        = '3';
		$defaults['enews-widget-field-input-border-color-focus']   = '#dddddd';
		$defaults['enews-widget-field-input-border-type-focus']    = 'solid';
		$defaults['enews-widget-field-input-border-width-focus']   = '1';
		$defaults['enews-widget-field-input-pad-top']              = '16';
		$defaults['enews-widget-field-input-pad-bottom']           = '16';
		$defaults['enews-widget-field-input-pad-left']             = '16';
		$defaults['enews-widget-field-input-pad-right']            = '16';
		$defaults['enews-widget-field-input-margin-bottom']        = '16';
		$defaults['enews-widget-field-input-box-shadow']           = 'inherit';

		// Submit Button
		$defaults['enews-widget-button-back']                      = isset( $defaults['enews-widget-button-back'] ) ? $defaults['enews-widget-button-back'] : '';
		$defaults['enews-widget-button-back-hov']                  = '#ffffff';
		$defaults['enews-widget-button-text-color']                = '#ffffff';
		$defaults['enews-widget-button-text-color-hov']            = '#333333';
		$defaults['enews-widget-button-transform']                 = 'uppercase';
		$defaults['enews-widget-button-stack']                     = 'helvetica';
		$defaults['enews-widget-button-size']                      = '14';
		$defaults['enews-widget-button-weight']                    = '300';
		$defaults['enews-widget-button-pad-top']                   = '16';
		$defaults['enews-widget-button-pad-bottom']                = '16';
		$defaults['enews-widget-button-pad-left']                  = '24';
		$defaults['enews-widget-button-pad-right']                 = '24';
		$defaults['enews-widget-button-margin-bottom']             = '0';

		// Allow child theme add-ons to override eNews defaults
		$defaults   = apply_filters( 'gppro_enews_set_defaults', $defaults );

		return $defaults;

	}

	/**
	 * Set our field override.
	 *
	 * @param  [type] $field [description]
	 * @return [type]        [description]
	 */
	public function override_default_check( $field = '' ) {

		// First check for Minimum Pro.
		if ( get_stylesheet() !== 'minimum-pro' ) {
			return true;
		}

		// Check the specific fields.
		if ( ! empty( $field ) && 'enews-widget-back' == $field || ! empty( $field ) && 'enews-widget-title-color' == $field ) {
			return false;
		}

		// Now return true.
		return true;
	}

	/**
	 * Set the child theme default values.
	 *
	 * @param  [type] $defaults [description]
	 * @return [type]           [description]
	 */
	public function enews_defaults_child_themes( $defaults ) {

		// Set defaults based on child theme (if known)
		switch ( get_stylesheet() ) {

			// Minimum Pro Child theme
			case 'minimum-pro':
				$defaults['enews-widget-button-back']                      = '#0ebfe9';
				// Minimum doesn't have padding on sidebar widgets, which appears to be unique in child themes
				// Minimum's defaults make the enews default look terrible. Resetting the background color and widget title color
				// make it look a bit better. This only works because we override the default check above for these two
				// attributes as well.
				$defaults['enews-widget-back']                             = isset( $defaults['sidebar-widget-back'] ) ? $defaults['sidebar-widget-back'] : '#ffffff';
				$defaults['enews-widget-title-color']                      = isset( $defaults['home-widget-single-title-text'] ) ? $defaults['home-widget-single-title-text'] : '#333333'; // A similar title as the default, otherwise just choose dark
				break;

			// Metro Pro Child theme
			case 'metro-pro':
				$defaults['enews-widget-button-back']                      = '#f96e5b';
				$defaults['enews-widget-field-input-box-shadow']           = 'none';
				$defaults['enews-widget-field-input-margin-bottom']        = '12';
				break;

			// Expose Pro Child theme
			case 'expose-pro':
				$defaults['enews-widget-button-back']                      = '#ffffff';
				$defaults['enews-widget-button-back-hov']                  = '#000000';
				$defaults['enews-widget-button-text-color']                = '#000000';
				$defaults['enews-widget-button-text-color-hov']            = '#ffffff';
				$defaults['enews-widget-button-pad-top']                   = '16';
				$defaults['enews-widget-button-pad-bottom']                = '15';
				$defaults['enews-widget-button-pad-left']                  = '24';
				$defaults['enews-widget-button-pad-right']                 = '24';

				$defaults['enews-widget-field-input-border-type']          = 'none';
				$defaults['enews-widget-field-input-border-type-focus']    = 'none';
				$defaults['enews-widget-field-input-box-shadow']           = 'none';
				$defaults['enews-widget-field-input-margin-bottom']        = '10';
				$defaults['enews-widget-field-input-pad-top']              = '16';
				$defaults['enews-widget-field-input-pad-bottom']           = '15';
				$defaults['enews-widget-field-input-pad-left']             = '24';
				$defaults['enews-widget-field-input-pad-right']            = '24';
				break;

			// Beautiful Pro Child theme
			case 'beautiful-pro':
				// General
				$defaults['enews-widget-back']                             = '';
				$defaults['enews-widget-title-color']                      = '#333333';
				$defaults['enews-widget-gen-stack']                        = isset( $defaults['sidebar-widget-content-stack'] ) ? $defaults['sidebar-widget-content-stack'] : '';
				$defaults['enews-widget-field-input-text-color']           = '#666666';
				$defaults['enews-widget-field-input-stack']                = isset( $defaults['sidebar-widget-content-stack'] ) ? $defaults['sidebar-widget-content-stack'] : '';
				$defaults['enews-widget-field-input-size']                 = '18';
				$defaults['enews-widget-field-input-border-radius']        = '0';
				$defaults['enews-widget-field-input-pad-top']              = '12';
				$defaults['enews-widget-field-input-pad-bottom']           = '15';
				$defaults['enews-widget-field-input-pad-left']             = '20';
				$defaults['enews-widget-field-input-pad-right']            = '20';
				$defaults['enews-widget-field-input-margin-bottom']        = '0';
				$defaults['enews-widget-field-input-box-shadow']           = 'none';

				// Submit Button
				$defaults['enews-widget-button-back']                      = '#e5554e';
				$defaults['enews-widget-button-back-hov']                  = '#d04943';
				$defaults['enews-widget-button-text-color']                = '#ffffff';
				$defaults['enews-widget-button-text-color-hov']            = '#ffffff';
				$defaults['enews-widget-button-transform']                 = 'uppercase';
				$defaults['enews-widget-button-stack']                     = 'raleway';
				$defaults['enews-widget-button-size']                      = '16';
				$defaults['enews-widget-button-weight']                    = '300';
				$defaults['enews-widget-button-pad-top']                   = '16';
				$defaults['enews-widget-button-pad-bottom']                = '15';
				$defaults['enews-widget-button-pad-left']                  = '24';
				$defaults['enews-widget-button-pad-right']                 = '24';
				$defaults['enews-widget-button-margin-bottom']             = '0';
				break;

			// Daily Dish Pro Child theme.
			case 'daily-dish-pro':
				// General.
				$defaults['enews-widget-back']                             = '';
				$defaults['enews-widget-title-color']                      = '#ffffff'; // Check default.
				$defaults['enews-widget-text-color']                       = '#000000';

				// General Typography.
				$defaults['enews-widget-gen-stack']                        = isset( $defaults['sidebar-widget-content-stack'] ) ? $defaults['sidebar-widget-content-stack'] : '';
				$defaults['enews-widget-gen-size']                         = '18';
				$defaults['enews-widget-gen-weight']                       = '400';
				$defaults['enews-widget-gen-transform']                    = 'none';
				$defaults['enews-widget-gen-text-margin-bottom']           = '20';

				// Field Inputs.
				$defaults['enews-widget-field-input-back']                 = '#ffffff';
				$defaults['enews-widget-field-input-text-color']           = '#999999';
				$defaults['enews-widget-field-input-stack']                = isset( $defaults['sidebar-widget-content-stack'] ) ? $defaults['sidebar-widget-content-stack'] : '';
				$defaults['enews-widget-field-input-size']                 = '16';
				$defaults['enews-widget-field-input-weight']               = '400';
				$defaults['enews-widget-field-input-border-radius']        = '0';
				$defaults['enews-widget-field-input-border-color-focus']   = '#999999';
				$defaults['enews-widget-field-input-pad-top']              = '16';
				$defaults['enews-widget-field-input-pad-bottom']           = '16';
				$defaults['enews-widget-field-input-pad-left']             = '16';
				$defaults['enews-widget-field-input-pad-right']            = '16';
				$defaults['enews-widget-field-input-margin-bottom']        = '16';
				$defaults['enews-widget-field-input-box-shadow']           = 'none';

				// Submit Button.
				$defaults['enews-widget-button-back']                      = '#f5f5f5';
				$defaults['enews-widget-button-back-hov']                  = '#e14d43';
				$defaults['enews-widget-button-text-color']                = '#000000';
				$defaults['enews-widget-button-text-color-hov']            = '#ffffff';
				$defaults['enews-widget-button-transform']                 = 'uppercase';
				$defaults['enews-widget-button-stack']                     = 'lato';
				$defaults['enews-widget-button-size']                      = '14';
				$defaults['enews-widget-button-weight']                    = '400';
				$defaults['enews-widget-button-pad-top']                   = '20';
				$defaults['enews-widget-button-pad-bottom']                = '20';
				$defaults['enews-widget-button-pad-left']                  = '24';
				$defaults['enews-widget-button-pad-right']                 = '24';
				$defaults['enews-widget-button-margin-bottom']             = '0';
				break;

			// Lifestyle Pro Child theme.
			case 'lifestyle-pro':

				// Set a default color.
				$base = '';

				// Check for class and proceed.
				if ( class_exists( 'GP_Pro_Lifestyle_Pro' ) ) {

					// Fetch the variable color choice array.
					$colors = GP_Pro_Lifestyle_Pro::theme_color_choice();

					// Now use base.
					$base   = ! empty( $colors['base'] ) ? $colors['base'] : '';
				}

				// General.
				$defaults['enews-widget-back']                             = '';
				$defaults['enews-widget-title-color']                      = '#222222';
				$defaults['enews-widget-text-color']                       = '#a5a5a3';

				// General Typography.
				$defaults['enews-widget-gen-stack']                        = 'droid-sans';
				$defaults['enews-widget-gen-size']                         = '15';
				$defaults['enews-widget-gen-weight']                       = '300';
				$defaults['enews-widget-gen-text-margin-bottom']           = '16';

				// Field Inputs.
				$defaults['enews-widget-field-input-stack']                = 'droid-sans';
				$defaults['enews-widget-field-input-size']                 = '14';
				$defaults['enews-widget-field-input-weight']               = '400';
				$defaults['enews-widget-field-input-border-color']         = '#eeeee8';
				$defaults['enews-widget-field-input-border-radius']        = '0';
				$defaults['enews-widget-field-input-border-color-focus']   = '#999999';

				// Submit Button.
				$defaults['enews-widget-button-back']                      = $base;
				$defaults['enews-widget-button-back-hov']                  = '#eeeee8';
				$defaults['enews-widget-button-text-color']                = '#ffffff';
				$defaults['enews-widget-button-text-color-hov']            = '#a5a5a3';
				$defaults['enews-widget-button-transform']                 = 'none';
				$defaults['enews-widget-button-stack']                     = 'droid-sans';
				$defaults['enews-widget-button-size']                      = '14';
				$defaults['enews-widget-button-weight']                    = '300';
				$defaults['enews-widget-button-pad-top']                   = '16';
				$defaults['enews-widget-button-pad-bottom']                = '16';
				$defaults['enews-widget-button-pad-left']                  = '24';
				$defaults['enews-widget-button-pad-right']                 = '24';
				$defaults['enews-widget-button-margin-bottom']             = '0';

				break;

			default:
				break;
		}

		return $defaults;
	}

	/**
	 * Clear warning check setting.
	 *
	 * @return void
	 */
	public function enews_clear_check() {

		// Delete the dismissed setting.
		if ( false !== $file = $this->plugin_info( 'file' ) ) {
			delete_option( 'gppro-warning-' . $file );
		}
	}

	/**
	 * Checks the settings for field input placeholder text color.
	 *
	 * @param  [type] $setup [description]
	 * @param  [type] $data  [description]
	 * @param  [type] $class [description]
	 * @return [type]        [description]
	 */
	public function placeholder_text_filter( $setup, $data, $class ) {

		// Check for a change in the placeholder text color.
		if ( GP_Pro_Builder::build_check( $data, 'enews-widget-field-place-text-color' ) ) {

			// Pull my color variable out of the data array.
			$color   = esc_attr( $data['enews-widget-field-place-text-color'] );

			// CSS entries for webkit.
			$setup  .= $class . ' .enews-widget input[type="text"]::-webkit-input-placeholder { color: ' . $color . ' }' . "\n";
			$setup  .= $class . ' .enews-widget input[type="email"]::-webkit-input-placeholder { color: ' . $color . ' }' . "\n";

			// CSS entries for Firefox 18 and below.
			$setup  .= $class . ' .enews-widget input[type="text"]:-moz-placeholder { color: ' . $color . ' }' . "\n";
			$setup  .= $class . ' .enews-widget input[type="email"]:-moz-placeholder { color: ' . $color . ' }' . "\n";

			// CSS entries for Firefox 19 and above.
			$setup  .= $class . ' .enews-widget input[type="text"]::-moz-placeholder { color: ' . $color . ' }' . "\n";
			$setup  .= $class . ' .enews-widget input[type="email"]::-moz-placeholder { color: ' . $color . ' }' . "\n";

			// CSS entries for IE.
			$setup  .= $class . ' .enews-widget input[type="text"]:-ms-input-placeholder { color: ' . $color . ' }' . "\n";
			$setup  .= $class . ' .enews-widget input[type="email"]:-ms-input-placeholder { color: ' . $color . ' }' . "\n";
		}

		// Return the CSS values.
		return $setup;
	}

} // End class.

// Instantiate our class.
$GP_Pro_Widget_Enews = GP_Pro_Widget_Enews::getInstance();
