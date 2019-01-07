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

        //Add the menu to the Tools set of menu items
        add_management_page(
			'WPRemote', 					// The title to be displayed in the browser window for this page.
			'WPRemote',					// The text to be displayed for this menu item
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

			<h2><?php _e( 'WPRemote', 'wprp' ); ?></h2>
			<?php settings_errors(); ?>

			<?php if( isset( $_GET[ 'tab' ] ) ) {
				$active_tab = $_GET[ 'tab' ];
			} else if( $active_tab == 'input_examples' ) {
				$active_tab = 'input_examples';
			} else {
				$active_tab = 'display_options';
			} // end if/else ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=wprp_options&tab=display_options" class="nav-tab <?php echo $active_tab == 'display_options' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Basic Settings', 'wprp' ); ?></a>
                <a href="?page=wprp_options&tab=wprp_schedule" class="nav-tab <?php echo $active_tab == 'wprp_schedule' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Schedule Backup', 'wprp' ); ?></a>
				<a href="?page=wprp_options&tab=wprp_s3_backup" class="nav-tab <?php echo $active_tab == 'wprp_s3_backup' ? 'nav-tab-active' : ''; ?>"><?php _e( 'S3', 'wprp' ); ?></a>
                <a href="?page=wprp_options&tab=wprp_dropbox_backup" class="nav-tab <?php echo $active_tab == 'wprp_dropbox_backup' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Dropbox', 'wprp' ); ?></a>
                <a href="?page=wprp_options&tab=wprp_sftp_backup" class="nav-tab <?php echo $active_tab == 'wprp_sftp_backup' ? 'nav-tab-active' : ''; ?>"><?php _e( 'SFTP', 'wprp' ); ?></a>
			</h2>

			<form method="post" action="options.php">
				<?php

				if( $active_tab == 'display_options' ) {
					settings_fields( 'wprp_basic_settings' );
					do_settings_sections( 'wprp_basic_settings' );

                } elseif( $active_tab == 'wprp_schedule' ) {

                    settings_fields( WPRP_Schedule::$hook );
                    do_settings_sections( WPRP_Schedule::$hook );

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
                    echo 'ACCESS DENIED';
				}
                if (empty($no_submit)) submit_button();
				?>
			</form>
		</div><!-- /.wrap -->
		<?php
	}

	/**
	 * Initializes the theme's input example by registering the Sections,
	 * Fields, and Settings. This particular group of options is used to demonstration
	 * validation and sanitization.
	 *
	 * This function is registered with the 'admin_init' hook.
	 */
	public function setup_tabs() {
        include_once ABSPATH . 'wp-admin/includes/template.php';

        $this->setMainTab();
        $this->setScheduleTab();
        $this->setS3Tab();
        $this->setDropboxTab();
        $this->addSftpTab();

	}

	public function setMainTab()
    {
        add_settings_section(
            'wprp_basic_settings',
            __('Basic Settings', 'wprp'),
            function () {},
            'wprp_basic_settings'
        );

        add_settings_field(
            'wprp_basic_settings',
            __('API Key', 'wprp'),
            function() {
                $info = get_option( 'wpr_api_key', '' );
                $options = get_option( 'wprp_basic_settings', ['api_key' => ''] );
                if (empty($options['api_key']) && !empty($info)) {
                    $key = $info;
                } else {
                    $key = $options['api_key'];
                }
                echo '<input type="text" name="wprp_basic_settings[api_key]" value="' . $key . '" />';
            },
            'wprp_basic_settings',
            'wprp_basic_settings'
        );

        register_setting(
            'wprp_basic_settings',
            'wprp_basic_settings',
            array($this, 'validate_input_examples')
        );
    }

	public function input_element_callback($name, $type, $postfix = 'backup') {
		$options = get_option( 'wprp_' . $type . '_' . $postfix );

		$input_type = 'text';
		if ($name == 'password') {
		    $input_type = 'password';
        }

		// Render the output
		echo '<input type="' . $input_type . '" id="wprp_' . $postfix . '_' . $name . '" name="wprp_' . $type . '_' . $postfix . '[' . $name . ']" value="' . $options[$name] . '" />';

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

        if (strpos('x'  . $name, 'callback') === false) {
            return false;
        }

        $function = 'input_element_callback';
        if (strpos('x'  . $name, 'checkbox')) {
            $function = 'checkbox_element_callback';
        }

        $postfix = 'backup';
        if (strpos('x'  . $name, 'schedule') || strpos('x'  . $name, 'settings')) {
            $postfix = 'settings';
        }

        $name = str_replace(['checkbox_', 'input_', '_callback', 'settings_'], '', $name);
        list($type, $name) = explode('_', $name);

        return $this->{$function}($name, $type, $postfix);
    }

	public function checkbox_element_callback($name, $type, $postfix = 'backup')
    {
        $options = get_option( 'wprp_' . $type . '_' . $postfix );

		$html = '<input type="checkbox" id="wprp_' . $postfix . '_' . $name . '" name="wprp_' . $type . '_' . $postfix . '[' . $name . ']" value="1"' . checked( 1, $options[$name], false ) . '/>';

		echo $html;
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

	}

    /**
     * Set the S3 Tab
     */
    protected function setS3Tab()
    {
        if( false == get_option( 'wprp_s3_backup' ) ) {
            $default_array = $this->default_input_options('s3');
            update_option( 'wprp_s3_backup', $default_array );
        }

        add_settings_section(
            'wprp_s3_backup',
            __('S3 Backup', 'wprp'),
            function () {
            },
            'wprp_s3_backup'
        );

        add_settings_field(
            's3_key',
            __('Access Key', 'wprp'),
            array($this, 'input_s3_key_callback'),
            'wprp_s3_backup',
            'wprp_s3_backup'
        );

        add_settings_field(
            's3_secret',
            __('Secret Key', 'wprp'),
            array($this, 'input_s3_secret_callback'),
            'wprp_s3_backup',
            'wprp_s3_backup'
        );

        add_settings_field(
            's3_bucket',
            __('Bucket Name', 'wprp'),
            array($this, 'input_s3_bucket_callback'),
            'wprp_s3_backup',
            'wprp_s3_backup'
        );

        add_settings_field(
            's3_enabled',
            __('Enabled', 'wprp'),
            array($this, 'checkbox_s3_enabled_callback'),
            'wprp_s3_backup',
            'wprp_s3_backup'
        );

        register_setting(
            'wprp_s3_backup',
            'wprp_s3_backup',
            array( $this, 'validate_input_examples')
        );
    }

    /**
     * Set Dropbox tab
     */
    protected function setDropboxTab()
    {
        if( false == get_option( 'wprp_dropbox_backup' ) ) {
            $default_array = $this->default_input_options('dropbox');
            update_option( 'wprp_dropbox_backup', $default_array );
        }

        add_settings_section(
            'wprp_dropbox_backup',
            __('Dropbox Backup', 'wprp'),
            function () {
            },
            'wprp_dropbox_backup'
        );

        add_settings_field(
            'dropbox_authorizationToken',
            __('Authorization Token', 'wprp'),
            array($this, 'input_dropbox_authorizationToken_callback'),
            'wprp_dropbox_backup',
            'wprp_dropbox_backup'
        );

        add_settings_field(
            'dropbox_enabled',
            __('Enabled', 'wprp'),
            array($this, 'checkbox_dropbox_enabled_callback'),
            'wprp_dropbox_backup',
            'wprp_dropbox_backup'
        );

        register_setting(
            'wprp_dropbox_backup',
            'wprp_dropbox_backup',
            array( $this, 'validate_input_examples')
        );
    }

    /**
     * Set SFTP Tab
     */
    protected function addSftpTab()
    {
        if( false == get_option( 'wprp_sftp_backup' ) ) {
            $default_array = $this->default_input_options('sftp');
            update_option( 'wprp_sftp_backup', $default_array );
        }

        add_settings_section(
            'wprp_sftp_backup',
            __('SFTP Backup', 'wprp'),
            function () {
            },
            'wprp_sftp_backup'
        );


        add_settings_field(
            'sftp_host',
            __('Hostname', 'wprp'),
            array($this, 'input_sftp_host_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        add_settings_field(
            'sftp_port',
            __('Port', 'wprp'),
            array($this, 'input_sftp_port_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        add_settings_field(
            'sftp_username',
            __('Username', 'wprp'),
            array($this, 'input_sftp_username_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        add_settings_field(
            'sftp_password',
            __('Password', 'wprp'),
            array($this, 'input_sftp_password_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        add_settings_field(
            'sftp_root',
            __('Root', 'wprp'),
            array($this, 'input_sftp_root_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        add_settings_field(
            'sftp_timeout',
            __('Timeout', 'wprp'),
            array($this, 'input_sftp_timeout_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        add_settings_field(
            'sftp_enabled',
            __('Enabled', 'wprp'),
            array($this, 'checkbox_sftp_enabled_callback'),
            'wprp_sftp_backup',
            'wprp_sftp_backup'
        );

        register_setting(
            'wprp_sftp_backup',
            'wprp_sftp_backup',
            array( $this, 'validate_input_examples')
        );
    }

    /**
     * Schedule Tab Settings
     */
    protected function setScheduleTab()
    {
        if (false == get_option(WPRP_Schedule::$hook)) {
            update_option(WPRP_Schedule::$hook, ['recurrence' => 'daily', 'enabled' => 'false']);
        }

        add_settings_section(
            'wprp_schedule_settings',
            __('Scheduling', 'wprp'),
            function () {
                echo 'Scheduling currently runs the backup and uploads it to all configured cloud locations.<br><br>';
                echo '<strong>Valid scheduling options: ‘hourly’, ‘twicedaily’, or ‘daily’.</strong>';
            },
            WPRP_Schedule::$hook
        );

        add_settings_field(
            'wprp_schedule',
            __('Schedule Recurrence', 'wprp'),
            array($this, 'input_schedule_recurrence_callback'),
            WPRP_Schedule::$hook,
            WPRP_Schedule::$hook
        );

        add_settings_field(
            'wprp_schedule_enabled',
            __('Enabled', 'wprp'),
            array($this, 'checkbox_schedule_enabled_callback'),
            WPRP_Schedule::$hook,
            WPRP_Schedule::$hook
        );

        register_setting(
            WPRP_Schedule::$hook,
            WPRP_Schedule::$hook,
            array($this, 'validate_input_examples')
        );
    }

}