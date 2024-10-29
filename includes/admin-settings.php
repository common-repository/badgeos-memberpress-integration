<?php
/**
 * BadgeOS Memberpress Settings
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Include the License Class
 */

if ( file_exists( BOSMEPR_INCLUDES_DIR . 'BOSRCP_License.php' ) ) {
    require_once BOSRCP_INCLUDES_DIR . 'BOSRCP_License.php';
}

/**
 * Class BOSMEPR_Admin_Settings
 */
class BOSMEPR_Admin_Settings {

    // private $license_class;
    public $page_tab;

    public function __construct() {

        $this->page_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

        add_filter( "plugin_action_links_" . BOSMEPR_FILE , [ $this, "admin_settings_link" ] );

        add_filter( 'admin_footer_text', [ $this, 'remove_footer_admin' ] );
        add_action( 'admin_menu', [ $this, 'bosmepr_admin_settings_page'] );
        add_action( 'admin_notices', [ $this, 'bosmepr_admin_notices'] );
        // $this->license_class = new BOSRCP_License();
    }

    /**
     * Return License Class
     *
     * @return WBLG_License
     */
    public function get_license_class() {
        return $this->license_class;
    }

    public function admin_settings_link( $links ) {
        $settings_url = add_query_arg( array( 'page' => 'badgeos_mepr_settings' ), admin_url( 'admin.php' ) );
        $settings_link = '<a href=" ' . $settings_url . ' "> ' . __( 'Settings' , BOSMEPR_LANG ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    
    /**
     * Display Notices
     */
    public function bosmepr_admin_notices() {

        $screen = get_current_screen();
        if( $screen->base != 'badgeos_page_badgeos_rcp_settings' ) {
            return;
        }

        if( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) {
            $class = 'notice notice-success is-dismissible';
            $message = __( 'Settings Saved', BOSMEPR_LANG );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }
    }

    /**
     * Create admin settings page
     */
    public function bosmepr_admin_settings_page() {

        add_submenu_page(
            'badgeos_badgeos',
            __( 'BadgeOS MemberPress', BOSMEPR_LANG ),
            __( 'BadgeOS MemberPress', BOSMEPR_LANG ),
            'manage_options',
            'badgeos_mepr_settings',
            [ $this, 'bosmepr_settings_callback_func' ]
        );
    }

    /**
     * Callback function for Setting Page
     */
    public function bosmepr_settings_callback_func() {
        ?>
        <div class="wrap">
            <div class="icon-options-general icon32"></div>
            <h1><?php echo __( 'BadgeOS MemberPress Settings', BOSMEPR_LANG ); ?></h1>

            <div class="nav-tab-wrapper">
                <?php
                $bosmepr_settings_sections = $this->bosmepr_get_setting_sections();
                foreach( $bosmepr_settings_sections as $key => $bosmepr_settings_section ) {
                    ?>
                    <a href="?page=badgeos_mepr_settings&tab=<?php echo $key; ?>"
                       class="nav-tab <?php echo $this->page_tab == $key ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons <?php echo $bosmepr_settings_section['icon']; ?>"></span>
                        <?php _e( $bosmepr_settings_section['title'], BOSMEPR_LANG ); ?>
                    </a>
                    <?php
                }
                ?>
            </div>

            <?php
            foreach( $bosmepr_settings_sections as $key => $bosmepr_settings_section ) {
                if( $this->page_tab == $key ) {
                    include( 'admin-templates/' . $key . '.php' );
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * WBLG Settings Sections
     *
     * @return mixed|void
     */
    public function bosmepr_get_setting_sections() {

        $bosmepr_settings_sections = array(
            'general' => array(
                'title' => __( 'License Option', BOSMEPR_LANG ),
                'icon' => 'dashicons-admin-network',
            )
        );

        return apply_filters( 'bosmepr_settings_sections', $bosmepr_settings_sections );
    }

    /**
     * Add footer branding
     *
     * @param $footer_text
     * @return mixed
     */
    function remove_footer_admin ( $footer_text ) {
        if( isset( $_GET['page'] ) && ( $_GET['page'] == 'badgeos_mepr_settings' ) ) {
            _e('<span>Fueled by <a href="http://www.wordpress.org" target="_blank">WordPress</a> | developed and designed by <a href="https://wooninjas.com" target="_blank">The WooNinjas</a></span>', BOSMEPR_LANG );
        } else {
            return $footer_text;
        }
    }
}

$GLOBALS['badgeos_mepr_options'] = new BOSMEPR_Admin_Settings();