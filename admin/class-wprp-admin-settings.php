<?php

/**
 * The settings of the plugin.
 *
 * @link       http://devinvinson.com
 * @since      1.0.0
 *
 * @package    Wppb_Demo_Plugin
 * @subpackage Wppb_Demo_Plugin/admin
 */

/**
 * Class WordPress_Plugin_Template_Settings
 *
 */
class WPRP_Admin_Settings {

	/**
	 * The ID of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    3.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * This function introduces the theme options into the 'Appearance' menu and into a top-level
	 * 'WPPB Demo' menu.
	 */
	public function setup_plugin_options_menu() {

		//Add the menu to the Plugins set of menu items
		add_plugins_page(
			'WP Remote', 					// The title to be displayed in the browser window for this page.
			'WP Remote',					// The text to be displayed for this menu item
			'manage_options',					// Which type of users can see this menu item
			'wprp_options',			// The unique ID - that is, the slug - for this menu item
			array( $this, 'render_settings_page_content')				// The name of the function to call when rendering this menu's page
		);

	}

	/**
	 * Provides default values for the Input Options.
	 *
	 * @return array
	 */
	public function default_input_options() {

		$defaults = array(
			'input_example'		=>	'default input example',
			'textarea_example'	=>	'',
			'checkbox_example'	=>	'',
			'radio_example'		=>	'2',
			'time_options'		=>	'default'
		);

		return $defaults;

	}

	/**
	 * Renders a simple page to display for the theme menu defined above.
	 */
	public function render_settings_page_content( $active_tab = '' ) {
		?>
		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap">

			<h2><?php _e( 'WP Remote', 'wprp' ); ?></h2>
			<?php settings_errors(); ?>

			<?php if( isset( $_GET[ 'tab' ] ) ) {
				$active_tab = $_GET[ 'tab' ];
			} else if( $active_tab == 'input_examples' ) {
				$active_tab = 'input_examples';
			} else {
				$active_tab = 'display_options';
			} // end if/else ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=wprp_options&tab=display_options" class="nav-tab <?php echo $active_tab == 'display_options' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Welcome', 'wprp' ); ?></a>
				<a href="?page=wprp_options&tab=input_examples" class="nav-tab <?php echo $active_tab == 'input_examples' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Backup Settings', 'wprp' ); ?></a>
			</h2>

			<form method="post" action="options.php">
				<?php

				if( $active_tab == 'display_options' ) {

					settings_fields( 'wprp_display_options' );
					do_settings_sections( 'wprp_display_options' );

				} else {

					settings_fields( 'wprp_input_examples' );
					do_settings_sections( 'wprp_input_examples' );

				} // end if/else

				submit_button();

				?>
			</form>

		</div><!-- /.wrap -->
		<?php
	}


	/**
	 * This function provides a simple description for the General Options page.
	 *
	 * It's called from the 'wppb-demo_initialize_theme_options' function by being passed as a parameter
	 * in the add_settings_section function.
	 */
	public function general_options_callback() {
		$options = get_option('wprp_display_options');
		var_dump($options);
		echo '<p>' . __( 'Select which areas of content you wish to display.', 'wppb-demo-plugin' ) . '</p>';
	} // end general_options_callback


	/**
	 * This function provides a simple description for the Input Examples page.
	 *
	 * It's called from the 'wppb-demo_theme_initialize_input_examples_options' function by being passed as a parameter
	 * in the add_settings_section function.
	 */
	public function input_examples_callback() {
		$options = get_option('wprp_input_examples');
		var_dump($options);
		echo '<p>' . __( 'Provides examples of the five basic element types.', 'wppb-demo-plugin' ) . '</p>';
	} // end general_options_callback


	/**
	 * Initializes the theme's input example by registering the Sections,
	 * Fields, and Settings. This particular group of options is used to demonstration
	 * validation and sanitization.
	 *
	 * This function is registered with the 'admin_init' hook.
	 */
	public function initialize_input_examples() {
		//delete_option('wprp_input_examples');
		if( false == get_option( 'wprp_input_examples' ) ) {
			$default_array = $this->default_input_options();
			update_option( 'wprp_input_examples', $default_array );
		} // end if


        include_once ABSPATH . 'wp-admin/includes/template.php';

		add_settings_section(
			'input_examples_section',
			__( 'Input Examples', 'wppb-demo-plugin' ),
			array( $this, 'input_examples_callback'),
			'wprp_input_examples'
		);

        add_settings_field(
            'Input Element',
            __( 'Input Element', 'wppb-demo-plugin' ),
            array( $this, 'input_element_callback'),
            'wprp_input_examples',
            'input_examples_section'
        );

        add_settings_field(
            'Textarea Element',
            __( 'Textarea Element', 'wppb-demo-plugin' ),
            array( $this, 'textarea_element_callback'),
            'wprp_input_examples',
            'input_examples_section'
        );

        add_settings_field(
            'Checkbox Element',
            __( 'Checkbox Element', 'wppb-demo-plugin' ),
            array( $this, 'checkbox_element_callback'),
            'wprp_input_examples',
            'input_examples_section'
        );

        add_settings_field(
            'Radio Button Elements',
            __( 'Radio Button Elements', 'wppb-demo-plugin' ),
            array( $this, 'radio_element_callback'),
            'wprp_input_examples',
            'input_examples_section'
        );

        add_settings_field(
            'Select Element',
            __( 'Select Element', 'wppb-demo-plugin' ),
            array( $this, 'select_element_callback'),
            'wprp_input_examples',
            'input_examples_section'
        );

        register_setting(
            'wprp_input_examples',
            'wprp_input_examples',
            array( $this, 'validate_input_examples')
        );

	}


	public function input_element_callback() {

		$options = get_option( 'wprp_input_examples' );

		// Render the output
		echo '<input type="text" id="input_example" name="wprp_input_examples[input_example]" value="' . $options['input_example'] . '" />';

	} // end input_element_callback

	public function textarea_element_callback() {

		$options = get_option( 'wprp_input_examples' );

		// Render the output
		echo '<textarea id="textarea_example" name="wprp_input_examples[textarea_example]" rows="5" cols="50">' . $options['textarea_example'] . '</textarea>';

	} // end textarea_element_callback

	public function checkbox_element_callback() {

		$options = get_option( 'wprp_input_examples' );

		$html = '<input type="checkbox" id="checkbox_example" name="wprp_input_examples[checkbox_example]" value="1"' . checked( 1, $options['checkbox_example'], false ) . '/>';
		$html .= '&nbsp;';
		$html .= '<label for="checkbox_example">This is an example of a checkbox</label>';

		echo $html;

	} // end checkbox_element_callback

	public function radio_element_callback() {

		$options = get_option( 'wprp_input_examples' );

		$html = '<input type="radio" id="radio_example_one" name="wprp_input_examples[radio_example]" value="1"' . checked( 1, $options['radio_example'], false ) . '/>';
		$html .= '&nbsp;';
		$html .= '<label for="radio_example_one">Option One</label>';
		$html .= '&nbsp;';
		$html .= '<input type="radio" id="radio_example_two" name="wprp_input_examples[radio_example]" value="2"' . checked( 2, $options['radio_example'], false ) . '/>';
		$html .= '&nbsp;';
		$html .= '<label for="radio_example_two">Option Two</label>';

		echo $html;

	} // end radio_element_callback

	public function select_element_callback() {

		$options = get_option( 'wprp_input_examples' );

		$html = '<select id="time_options" name="wprp_input_examples[time_options]">';
		$html .= '<option value="default">' . __( 'Select a time option...', 'wppb-demo-plugin' ) . '</option>';
		$html .= '<option value="never"' . selected( $options['time_options'], 'never', false) . '>' . __( 'Never', 'wppb-demo-plugin' ) . '</option>';
		$html .= '<option value="sometimes"' . selected( $options['time_options'], 'sometimes', false) . '>' . __( 'Sometimes', 'wppb-demo-plugin' ) . '</option>';
		$html .= '<option value="always"' . selected( $options['time_options'], 'always', false) . '>' . __( 'Always', 'wppb-demo-plugin' ) . '</option>';	$html .= '</select>';

		echo $html;

	} // end select_element_callback


	public function validate_input_examples( $input ) {

		// Create our array for storing the validated options
		$output = array();

		// Loop through each of the incoming options
		foreach( $input as $key => $value ) {

			// Check to see if the current option has a value. If so, process it.
			if( isset( $input[$key] ) ) {

				// Strip all HTML and PHP tags and properly handle quoted strings
				$output[$key] = strip_tags( stripslashes( $input[ $key ] ) );

			} // end if

		} // end foreach

		// Return the array processing any additional functions filtered by this action
		return apply_filters( 'validate_input_examples', $output, $input );

	} // end validate_input_examples




}