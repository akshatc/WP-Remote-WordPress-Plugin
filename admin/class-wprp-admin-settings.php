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
	public function default_input_options( $type ) {

	    $config = WPRP_Remote_Backup::basic_config( $type );

        return $config ?: [];

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
				<a href="?page=wprp_options&tab=wprp_s3_backup" class="nav-tab <?php echo $active_tab == 'wprp_s3_backup' ? 'nav-tab-active' : ''; ?>"><?php _e( 'S3', 'wprp' ); ?></a>
                <a href="?page=wprp_options&tab=wprp_dropbox_backup" class="nav-tab <?php echo $active_tab == 'wprp_dropbox_backup' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Dropbox', 'wprp' ); ?></a>
                <a href="?page=wprp_options&tab=wprp_sftp_backup" class="nav-tab <?php echo $active_tab == 'wprp_sftp_backup' ? 'nav-tab-active' : ''; ?>"><?php _e( 'SFTP', 'wprp' ); ?></a>
			</h2>

			<form method="post" action="options.php">
				<?php

				if( $active_tab == 'display_options' ) {

					settings_fields( 'wprp_display_options' );
					do_settings_sections( 'wprp_display_options' );

				} elseif( $active_tab == 'wprp_s3_backup' ) {

                    settings_fields( 'wprp_s3_backup' );
                    do_settings_sections( 'wprp_s3_backup' );

                } elseif( $active_tab == 'wprp_dropbox_backup' ) {

                    settings_fields( 'wprp_dropbox_backup' );
                    do_settings_sections( 'wprp_dropbox_backup' );

                } elseif( $active_tab == 'wprp_sftp_backup' ) {

                    settings_fields( 'wprp_sftp_backup' );
                    do_settings_sections( 'wprp_sftp_backup' );

                } else {

					settings_fields( 'wprp_primary_backup' );
					do_settings_sections( 'wprp_primary_backup' );

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
//		delete_option('wprp_backup');
//        get_option( 'wprp_backup' );

		if( false == get_option( 'wprp_s3_backup' ) ) {
			$default_array = $this->default_input_options('s3');
			update_option( 'wprp_s3_backup', $default_array );
		}

        if( false == get_option( 'wprp_dropbox_backup' ) ) {
            $default_array = $this->default_input_options('dropbox');
            update_option( 'wprp_dropbox_backup', $default_array );
        }

        if( false == get_option( 'wprp_sftp_backup' ) ) {
            $default_array = $this->default_input_options('sftp');
            update_option( 'wprp_sftp_backup', $default_array );
        }


        include_once ABSPATH . 'wp-admin/includes/template.php';

		add_settings_section(
			'wprp_s3_backup',
			__( 'S3 Backup', 'wprp' ),
			function(){},
			'wprp_s3_backup'
		);

        add_settings_field(
            's3_key',
            __( 'Access Key', 'wprp' ),
            array( $this, 'input_s3_key_callback'),
            'wprp_s3_backup',
            'wprp_s3_backup'
        );

        add_settings_field(
            's3_secret',
            __( 'Secret Key', 'wprp' ),
            array( $this, 'input_s3_secret_callback'),
            'wprp_s3_backup',
            'wprp_s3_backup'
        );

        add_settings_field(
            's3_bucket',
            __( 'Bucket Name', 'wprp' ),
            array( $this, 'input_s3_bucket_callback'),
            'wprp_s3_backup',
            'wprp_s3_backup'
        );


        add_settings_section(
            'wprp_dropbox_backup',
            __( 'Dropbox Backup', 'wprp' ),
            function(){},
            'wprp_dropbox_backup'
        );

        add_settings_field(
            'dropbox_authorizationToken',
            __( 'Authorization Token', 'wprp' ),
            array( $this, 'input_dropbox_authorizationToken_callback'),
            'wprp_dropbox_backup',
            'wprp_dropbox_backup'
        );


        add_settings_section(
            'wprp_sftp_backup',
            __( 'SFTP Backup', 'wprp' ),
            function(){},
            'wprp_sftp_backup'
        );


        add_settings_field(
            'sftp_host',
            __( 'Hostname', 'wprp' ),
            array( $this, 'input_sftp_host_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        add_settings_field(
            'sftp_port',
            __( 'Port', 'wprp' ),
            array( $this, 'input_sftp_port_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        add_settings_field(
            'sftp_username',
            __( 'Username', 'wprp' ),
            array( $this, 'input_sftp_username_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );
        add_settings_field(
            'sftp_password',
            __( 'Password', 'wprp' ),
            array( $this, 'input_sftp_password_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );
        add_settings_field(
            'sftp_root',
            __( 'Root', 'wprp' ),
            array( $this, 'input_sftp_root_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );
        add_settings_field(
            'sftp_timeout',
            __( 'Timeout', 'wprp' ),
            array( $this, 'input_sftp_timeout_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        register_setting(
            'wprp_s3_backup',
            'wprp_s3_backup',
            array( $this, 'validate_input_examples')
        );

        register_setting(
            'wprp_dropbox_backup',
            'wprp_dropbox_backup',
            array( $this, 'validate_input_examples')
        );

        register_setting(
            'wprp_sftp_backup',
            'wprp_sftp_backup',
            array( $this, 'validate_input_examples')
        );

	}

	public function input_element_callback($name, $type) {
		$options = get_option( 'wprp_' . $type . '_backup' );

		$input_type = 'text';
		if ($name == 'password') {
		    $input_type = 'password';
        }

		// Render the output
		echo '<input type="' . $input_type . '" id="wprp_backup_' . $name . '" name="wprp_' . $type . '_backup[' . $name . ']" value="' . $options[$name] . '" />';

	}

    /**
     * Magic method to call input elements
     * @param $name
     * @param $arguments
     * @return bool|mixed
     */
    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        if (method_exists($this, $name)) {
            return call_user_func_array($name, $arguments);
        }
        if (strpos('x'  . $name, 'input')) {
            $name = str_replace(['input_', '_callback'], '', $name);
            list($type, $name) = explode('_', $name);
            $this->input_element_callback($name, $type);
            return true;
        }
        if (method_exists($this, $name)) {
            return call_user_func_array($name, $arguments);
        }
    }

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

	public function backup_config( $type ) {

		$bacic_config = WPRP_Remote_Backup::basic_config();
		$options = array_keys($bacic_config);

		$html = '<select id="' . $type . '_backup_type" name="wprp_backup_' . $type . '_config[type]">';
        $html .= '<option value="">' . __( 'Select a backup location...', 'wprp' ) . '</option>';
        foreach ($options as $option) {
            $html .= '<option value="' . $option . '">' . __( $option, 'wprp' ) . '</option>';
        }
		$html .= '</select>';

		echo $html;

	}

	public function primary_backup_select()
    {
        $this->backup_config('primary');
    }


    public function secondary_backup_select()
    {
        $this->backup_config('primary');
    }


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