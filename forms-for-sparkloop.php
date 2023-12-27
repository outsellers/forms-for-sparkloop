<?php
/**
 * Plugin Name: Forms for Sparkloop
 * Plugin URI: https://github.com/outsellers/forms-for-sparkloop
 * Description: A simple newsletter signup and Sendgrid/Sparkloop integration.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 5.6.20
 * Author: Philip Rudy
 * Author URI: https://philiparudy.org/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: forms-for-sparkloop
 * Domain Path: /languages/
 */

if(!defined('ABSPATH')) {
    exit;
}

define('FFSL_PLUGIN', __FILE__);

if ( ! defined( 'FFSL_PATH' ) ) {
    define( 'FFSL_PATH', plugin_dir_path( FFSL_PLUGIN ) );
}

if ( ! defined( 'FFSL_PLUGIN_URL' ) ) {
    define( 'FFSL_PLUGIN_URL', plugin_dir_url( FFSL_PLUGIN ) );
}

require 'vendor/autoload.php';
require_once 'class-options.php';
require_once 'admin-menu.php';

$options = new FFSL_Options();
$admin_menu = new FFSL_AdminMenu();

class FFSL_FormsForSparkloop {
    /**
     * @see https://developers.google.com/recaptcha/docs/v3
     *
     * @var string
     */
    private $recaptcha_key = '';

    /**
     * @see https://docs.sendgrid.com/api-reference/how-to-use-the-sendgrid-v3-api/authentication
     *
     * @var string
     */
    private $sendgrid_api_key = '';

    /**
     * @see https://docs.sendgrid.com/api-reference/contacts/add-or-update-a-contact
     *
     * @var array
     */
    private $sendgrid_list_ids = [];

    /**
     * @var string
     */
    private $sparkloop_id = '';

    /**
     * FFSL_FormsForSparkloop constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init() {
        $this->set_keys();
        add_action('init', [$this, 'sparkloop_add_shortcode']);
        add_action('rest_api_init', [$this, 'register_rest_route']);
        add_action('wpforms_process_complete', [$this, 'on_wpforms_submission'], 10, 4);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_sparkloop_wp_head']);
    }

    /**
     * Set basic keys from options
     *
     * @return void
     */
    public function set_keys() {
        $this->recaptcha_key = FFSL_Options::get_option('recaptcha_key');
        $this->sendgrid_api_key = FFSL_Options::get_option('remote_key');
        $list_ids = FFSL_Options::get_option('list_ids');
        if(!is_array($list_ids)) {
            $list_ids = [$list_ids];
        }
        $this->sendgrid_list_ids = array_merge($this->sendgrid_list_ids, $list_ids);
        $this->sparkloop_id = FFSL_Options::get_option('sparkloop_id');
    }

    /**
     * Add the sparkloop script
     *
     * @return void
     */
    public function enqueue_sparkloop_wp_head() {
        if ($this->sparkloop_id && $this->recaptcha_key) {
            wp_enqueue_script(
                'sparkloop-script',
                'https://js.sparkloop.app/team_' . esc_attr($this->sparkloop_id) . '.js',
                array(),
                null,
                false
            );

            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr($this->recaptcha_key),
                array(),
                null,
                false
            );

        } else {
            wp_add_inline_script('jquery', 'console.log("Missing at least one of the required keys.");');
        }
    }


    /**
     * Enqueue our scripts and styles
     *
     * @return void
     */
    public function enqueue_scripts() {
        wp_enqueue_style('sparkloop-form-css', plugin_dir_url(FFSL_PLUGIN) . 'assets/sparkloopforms.css', [], null, 'all');

        wp_enqueue_script('sparkloop-form-js', plugin_dir_url(FFSL_PLUGIN) . 'assets/sparkloopforms.js', [], null, true);

        $data_array = array(
            'nonce' => wp_create_nonce('wp_rest'),
            'site_url' => home_url(),
            'assets_url' => FFSL_PLUGIN_URL . 'assets/',
            'recaptcha_key' => $this->recaptcha_key,
        );

        wp_localize_script('sparkloop-form-js', 'spl_site_data', $data_array);
    }

    /**
     *
     * Shortcode functionality
     *
     *
     */

    /**
     * Add the sparkloop_form shortcode
     *
     * @return void
     */
    public function sparkloop_add_shortcode() {
        add_shortcode('ffsl_sparkloop_form', [$this, 'parse_shortcode']);
    }

    /**
     * The html for the shortcode
     *
     * @TODO: create templates folder
     * @return string
     */
    public function parse_shortcode($atts = [], $content = null) {
        static $count = 1;
        $form_wrapper_id =  "sparkLoopForm-".$count;

        $output = "<div id=\"".esc_attr($form_wrapper_id)."\" class=\"sparkloop-forms--form-wrapper\">";
        if($this->recaptcha_key) {
            $output .= "
                <form id=\"sparkLoopForm\" class=\"sparkloop-forms--form\" data-count=\"".esc_html($count)."\">
                    <div class=\"sparkloop-form--input-element\">
                        <label>Email <span class=\"required\">*</span></label>
                        <input
                        required
                        id=\"email\"
                        name=\"email\"
                        type=\"email\"
                        placeholder=\"Email\"
                        value=\"\" />

                        <button
                        type=\"submit\"
                        data-recaptcha-key=". esc_html($this->recaptcha_key) .">
                            Sign up
                        </button>
                    </div>
                </form>
        ";
        } else {
            $output .= "<p>A v3 reCAPTCHCA is required.</p>";
        }
        $output .= "</div>";

        $count++;

        return $output;
    }

    /**
     *
     * Rest api and callbacks
     * @TODO: add more email automation platforms
     *
     *
     */

    /**
     * Register our routes
     *
     * @return void
     */
    public function register_rest_route() {
        $namespace = 'sparkloopforms';
        $route = 'sendgrid/add-contact';

        register_rest_route($namespace, $route, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'process_sparkloop_form'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Process adding a subscriber to WP & Sendgrid
     *
     * @param WP_REST_Request $request
     * @return void|WP_Error
     */
    public function process_sparkloop_form(\WP_REST_Request $request) {

        $params = $request->get_params();
        $nonce = $params['_wpnonce'] ?? null;
        $email = sanitize_email($params['email']) ?? null;

        if(!$email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new WP_Error(
                'forms_for_sparkloop', 'The email is not valid.'
            );
        }

       if(!$added = $this->add_subscriber($email)) {
            return new WP_Error(
                'forms_for_sparkloop', 'Failed to add to our own list.'
            );
        }

        $response_data = $this->add_email_to_sendgrid_list($email);

        if(is_wp_error($response_data)) {
            return $response_data;
        }

        return rest_ensure_response(new \WP_REST_Response(
            [
                "successfully_added_subscriber" => $added,
            ]
        ));
    }

    /**
     * Add an email to Sendgrid List.
     *
     * @param string $email The email to be added.
     */
    public function add_email_to_sendgrid_list($email = null) {
        if (! $email ||!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return; // Invalid email
        }

        $api_key = $this->sendgrid_api_key;
        $list_ids = $this->sendgrid_list_ids;
        $sg = new \SendGrid($api_key);
        $request_body = [
            'list_ids' => $list_ids,
            'contacts' => [
                [
                    'email' => $email,
                ]
            ]
        ];

        try {
            $response = $sg->client->marketing()->contacts()->put($request_body);;
        } catch (Exception $ex) {
            error_log(print_r($ex->getMessage(), true));

            return \WP_Error(
                'forms_for_sparkloop',
                'Unknown error while attempting to add the email to the Sendgrid List.'
            );
        }

        return $response;
    }

    /**
     * Add sub to WP database in options table (meta_kay = forms_for_sparkloop__subscribers)
     *
     * @param $email
     * @return bool
     */
    public function add_subscriber($email = null) {
        if( ! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            return false;
        }

        $email_list = FFSL_Options::get_option('subscribers', []);

        if(!is_array($email_list)) {
            $email_list = [];
        }

        if(in_array($email, $email_list)) {
            return true;
        }

        $email_list[] = $email;

        return FFSL_Options::update_option('subscribers', $email_list);
    }
}

$sparkLoopForms = new FFSL_FormsForSparkloop();
