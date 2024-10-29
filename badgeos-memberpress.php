<?php
/**
 * Plugin Name: BadgeOS MemberPress Integration
 * Description: The BadgeOS MemberPress add-on allows you to award the BadgeOS achievements on subscribing/renewing the membership levels (any, free, or paid) and more!
 * Author: BadgeOS
 * Plugin URI: https://badgeos.org/
 * Version: 1.0.0
 * Text Domain: bosmepr
 * License: GNU AGPL v3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( __FILE__, ['BadgeOS_Memberpress_Integration', 'activation' ] );
register_deactivation_hook( __FILE__, ['BadgeOS_Memberpress_Integration', 'deactivation']  );


define( 'BOSMEPR_LANG', 'bosmepr' );

/**
 * Class BadgeOS_Memberpress_Integration
 */
final class BadgeOS_Memberpress_Integration {
	const VERSION = '1.0';

	/**
	 * BadgeOS Memberpress Triggers
	 *
	 * @var array
	 */
	public $triggers = array();

	/**
	 * Actions to forward for splitting an action up
	 *
	 * @var array
	 */
	public $actions = array();

	/**
	 * @var self
	 */
	private static $instance = null;

	/**
	 * @return BadgeOS_Memberpress_Integration
	 */
	public static function instance() {
		if ( is_null( self::$instance ) && ! ( self::$instance instanceof BadgeOS_Memberpress_Integration ) ) {
			self::$instance = new self();
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
			self::$instance->setup_triggers();
		}
		return self::$instance;
	}


	/**
	 * Activation function hook
	 *
	 * @return void
	 */
	public static function activation() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$general_values = get_option( 'bosmepr_options' );
        
        if( false == $general_values ) {

            $general_form_data = array();

            update_option('bosmepr_options', $general_form_data);
        }
	}

	/**
	 * Deactivation function hook
	 *
	 * @return void
	 */
	public static function deactivation() {
		delete_option( 'bosmepr_version' );
        delete_option( 'bosmepr_options' );

		return false;
	}

	/**
	 * Setup Constants
	 */
	private function setup_constants() {

		/**
		 * Plugin Text Domain
		 */
		define( 'BOSDB_TEXT_DOMAIN', 'BOSMEPR_LANG' );

		/**
		 * Plugin Directory
		 */
        define( 'BOSMEPR_FILE', plugin_basename(__FILE__) );
		define( 'BOSMEPR_DIR', plugin_dir_path( __FILE__ ) );
		define( 'BOSMEPR_DIR_FILE', BOSMEPR_DIR . basename( __FILE__ ) );
		define( 'BOSMEPR_INCLUDES_DIR', trailingslashit( BOSMEPR_DIR . 'includes' ) );
		define( 'BOSMEPR_INTEGRATION_DIR', trailingslashit( BOSMEPR_DIR . 'includes/integration' ) );
		define( 'BOSMEPR_BASE_DIR', plugin_basename( __FILE__ ) );

		/**
		 * Plugin URLS
		 */
		define( 'BOSMEPR_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );
		define( 'BOSMEPR_ASSETS_URL', trailingslashit( BOSMEPR_URL . 'assets' ) );
	}

	/**
	 * Pugin Include Required Files
	 */
	private function includes() {
		
		if( file_exists( BOSMEPR_DIR . '/includes/rules-engine.php' ) ) {
            require_once( BOSMEPR_DIR . '/includes/rules-engine.php' );
        }

        if( file_exists( BOSMEPR_DIR . '/includes/steps-ui.php' ) ) {
            require_once( BOSMEPR_DIR . '/includes/steps-ui.php' );
        }

        // if( file_exists( BOSMEPR_DIR . '/includes/admin-settings.php' ) ) {
        //     require_once( BOSMEPR_DIR . '/includes/admin-settings.php' );
        // }
	}
    
	private function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
		add_action( 'plugins_loaded', add_filter( 'gettext', array( $this, 'activation_message' ), 99, 3 ) );

        //check if background conversion process is running
        $non_ob_conversion_progress = get_transient('non_ob_conversion_progress'); 
        $ob_setting_page = false;
        if( isset($_GET['page']) ) {
            if($_GET['page'] == 'bos-mepr-options') {
                $ob_setting_page = true;
            }
        }

        if( current_user_can('manage_options') && !$ob_setting_page && $non_ob_conversion_progress ) { //Display notification background conversion process is running

            add_action('admin_notices', function () {
                $class = 'notice is-dismissible success';
                $message = __('Non open standards achievements conversion is processing in background, you will receive an email after conversion is completed.');

                printf('<div id="message" class="%s"> <p>%s</p></div>', $class, $message);

            });
        }
 
	}

	/**
	 * Translate the "Plugin activated." string
	 *
	 * @param [type] $translated_text
	 * @param [type] $untranslated_text
	 * @param [type] $domain
	 * @return void
	 */

	public function activation_message( $translated_text, $untranslated_text, $domain ) {
		$old = array(
			'Plugin <strong>activated</strong>.',
			'Selected plugins <strong>activated</strong>.',
		);

		$new = 'The Core is stable and the Plugin is <strong>deactivated</strong>';

		return $translated_text;
	}
	/**
	 * Enqueue scripts on admin
	 *
	 * @param string $hook
	 */
	public function admin_enqueue_scripts( $hook ) {
	}

	/**
	 * Enqueue scripts on frontend
	 */
	public function frontend_enqueue_scripts() {
	}

	public function setup_triggers() {

		$this->triggers = apply_filters( 'badgeos_mepr_triggers' , array(
			'badgeos_mepr_subscribed_any_membership' 		=> __( 'Subscribe Any Membership', BOSMEPR_LANG ),
			'badgeos_mepr_subscribed_free_membership' 		=> __( 'Subscribe Any Free Membership', BOSMEPR_LANG ),
			'badgeos_mepr_subscribed_paid_membership' 		=> __( 'Subscribe Any Paid Membership', BOSMEPR_LANG ),
			'badgeos_mepr_cancelled_membership' 			=> __( 'Cancel Any Membership', BOSMEPR_LANG ),
			'badgeos_mepr_expired_membership' 				=> __( 'Expired Any Membership', BOSMEPR_LANG ),
			// 'badgeos_mepr_renewed_membership' 				=> __( 'Renewed Any Membership', BOSMEPR_LANG), 'not needed now'
		));

		return $this->triggers;

    }
}

/**
 * @return bool
 */
function BadgeOS_Memberpress_Integration() {
	
	if ( ! class_exists( 'BadgeOS' ) ) {
		add_action( 'admin_notices', 'bosdb_ready' );
		return false;
	}

	$GLOBALS['BadgeOS_Memberpress_Integration'] = BadgeOS_Memberpress_Integration::instance();
}
add_action( 'plugins_loaded', 'BadgeOS_Memberpress_Integration' );
