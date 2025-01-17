<?php

/**
 * Plugin Name: WP Google Analytics
 * Plugin URI: https://github.com/8ctopus/wp-google-analytics
 * Description: Add Google Analytics 4 tracking to your website
 * Version: 2.0.0
 * Author: 8ctopus and Aaron D. Campbell
 * Author URI: http://github.com/8ctopus
 * License: GPLv2 or later
 * Text Domain: wp-google-analytics
 */

define( 'WGA_VERSION', '2.0.0' );

/*  Copyright 2006  Aaron D. Campbell  (email : wp_plugins@xavisys.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * wpGoogleAnalytics is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */
class wpGoogleAnalytics {

    /**
     * @var wpGoogleAnalytics - Static property to hold our singleton instance
     */
    public static $instance = false;

    public static $page_slug = 'wp-google-analytics';

    /**
     * This is our constructor, which is private to force the use of get_instance()
     */
    private function __construct() {
        add_filter( 'init', [ $this, 'init' ] );
        add_action( 'admin_init', [ $this, 'admin_init' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'get_footer', [ $this, 'insert_code' ] );
        add_filter( 'plugin_action_links', [ $this, 'add_plugin_page_links' ], 10, 2 );
    }

    /**
     * Function to instantiate our class and make it a singleton
     */
    public static function get_instance() {
        if ( !self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init() {
        load_plugin_textdomain( 'wp-google-analytics', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    }

    /**
     * This adds the options page for this plugin to the Options page
     */
    public function admin_menu() {
        add_options_page( __( 'Google Analytics', 'wp-google-analytics' ), __( 'Google Analytics', 'wp-google-analytics' ), 'manage_options', self::$page_slug, [ $this, 'settings_view' ] );
    }

    /**
     * Register our settings
     */
    public function admin_init() {
        register_setting( 'wga', 'wga', [ $this, 'sanitize_general_options' ] );

        add_settings_section( 'wga_general', false, '__return_false', 'wga' );

        add_settings_field( 'code', __( 'Google Analytics 4 tracking ID:', 'wp-google-analytics' ), [ $this, 'field_code' ], 'wga', 'wga_general' );
        add_settings_field( 'do_not_track', __( 'Visits to ignore:', 'wp-google-analytics' ), [ $this, 'field_do_not_track' ], 'wga', 'wga_general' );
    }

    /**
     * Where the user adds their Google Analytics code
     */
    public function field_code() {
        echo '<input name="wga[code]" id="wga-code" type="text" value="' . esc_attr( $this->_get_options( 'code' ) ) . '" />';
        echo '<p class="description">' . __( 'Paste your Google Analytics 4 tracking ID (e.g. "G-XXXXXXXXXX") into the field.', 'wp-google-analytics' ) . '</p>';
    }

    public function field_do_not_track() {
        $do_not_track = [
            'ignore_admin_area' => __( 'Do not log anything in the admin area', 'wp-google-analytics' ),
        ];

        global $wp_roles;

        foreach ( $wp_roles->roles as $role => $role_info ) {
            $do_not_track['ignore_role_' . $role] = sprintf( __( 'Do not log %s when logged in', 'wp-google-analytics' ), rtrim( $role_info['name'], 's' ) );
        }

        foreach ( $do_not_track as $id => $label ) {
            echo '<label for="wga_' . $id . '">';
            echo '<input id="wga_' . $id . '" type="checkbox" name="wga[' . $id . ']" value="true" ' . checked( 'true', $this->_get_options( $id ), false ) . ' />';
            echo '&nbsp;&nbsp;' . $label;
            echo '</label><br />';
        }
    }

    /**
     * Sanitize all of the options associated with the plugin
     */
    public function sanitize_general_options( $in ) {
        $out = [];

        // The actual tracking ID
        if ( preg_match( '/^G-[A-Z0-9]{10}$/', $in['code'], $matches ) ) {
            $out['code'] = $matches[0];
        } else {
            $out['code'] = '';
        }

        $checkbox_items = [
                // Additional items you can track
                'log_404s',
                'log_searches',
                'log_outgoing',
                // Things to ignore
                'ignore_admin_area',
            ];

        global $wp_roles;

        foreach ( array_keys($wp_roles->roles) as $role ) {
            $checkbox_items[] = 'ignore_role_' . $role;
        }

        foreach ( $checkbox_items as $checkbox_item ) {
            if ( isset( $in[$checkbox_item] ) && 'true' == $in[$checkbox_item] ) {
                $out[$checkbox_item] = 'true';
            } else {
                $out[$checkbox_item] = 'false';
            }
        }

        return $out;
    }

    /**
     * This is used to display the options page for this plugin
     */
    public function settings_view() {
?>
        <div class="wrap">
            <h2><?php _e( 'Google Analytics Options', 'wp-google-analytics' ); ?></h2>
            <form action="options.php" method="post" id="wp_google_analytics">
<?php

settings_fields( 'wga' );
do_settings_sections( 'wga' );
submit_button( __( 'Update Options', 'wp-google-analytics' ) );

?>
            </form>
        </div>
<?php
    }

    /**
     * This injects the Google Analytics code into the footer of the page.
     *
     */
    public function insert_code() {
        $tracking_id = $this->_get_options( 'code' );

        if ( empty( $tracking_id ) ) {
            echo '<!-- Your Google Analytics Plugin is missing the tracking ID -->' . PHP_EOL;
        }

        // get our plugin options
        $wga = $this->_get_options();

        // If the user's role has wga_no_track set to true, return without inserting code
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $role         = array_shift( $current_user->roles );

            if ( 'true' == $this->_get_options( 'ignore_role_' . $role ) ) {
                echo '<!-- Google Analytics Plugin is set to ignore your user role -->' . PHP_EOL;
            }
        }

        // If $admin is true (we're in the admin_area), and we've been told to ignore_admin_area, return without inserting code
        if ( is_admin() && ( !isset( $wga['ignore_admin_area'] ) || $wga['ignore_admin_area'] != 'false' ) ) {
            echo '<!-- Your Google Analytics Plugin is set to ignore Admin area -->' . PHP_EOL;
        }

        echo <<<SCRIPT
            <!-- Google tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id={$tracking_id}"></script>
            <script>
              window.dataLayer = window.dataLayer || [];
              function gtag() {
                dataLayer.push(arguments);
              }

              gtag('js', new Date());
              gtag('config', '{$tracking_id}');
            </script>
        SCRIPT;
    }

    /**
     * Used to get one or all of our plugin options
     *
     * @param string[optional] $option - Name of options you want.  Do not use if you want ALL options
     *
     * @return array of options, or option value
     */
    private function _get_options( $option = null, $default = false ) {
        $o = get_option( 'wga' );

        if ( isset( $option ) ) {
            if ( isset( $o[$option] ) ) {
                if ( 'code' == $option ) {
                    if ( preg_match( '/^G-[A-Z0-9]{10}$/', $o[$option], $matches ) ) {
                        return $matches[0];
                    } else {
                        return '';
                    }
                } else {
                    return $o[$option];
                }
            } else {
                if ( 'ignore_role_' == substr( $option, 0, 12 ) ) {
                    global $wp_roles;
                    // Backwards compat for when the tracking information was stored as a cap
                    $maybe_role = str_replace( 'ignore_role_', '', $option );

                    if ( isset( $wp_roles->roles[$maybe_role] ) ) {
                        if ( isset( $wp_roles->roles[$maybe_role]['capabilities']['wga_no_track'] ) && $wp_roles->roles[$maybe_role]['capabilities']['wga_no_track'] ) {
                            return 'true';
                        }
                    }

                    return false;
                }

                return $default;
            }
        } else {
            return $o;
        }
    }

    public function add_plugin_page_links( $links, $file ) {
        if ( plugin_basename( __FILE__ ) == $file ) {
            $link = '<a href="' . admin_url( 'options-general.php?page=' . self::$page_slug ) . '">' . __( 'Settings', 'wp-google-analytics' ) . '</a>';
            array_unshift( $links, $link );
        }

        return $links;
    }
}

global $wp_google_analytics;
$wp_google_analytics = wpGoogleAnalytics::get_instance();
