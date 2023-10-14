<?php
/**
 * Plugin Name: forms for sparkloop
 * Plugin URI: https://github.com/outsellers/forms-for-sparkloop
 * Description: A simple newsletter signup and Sendgrid/Sparkloop integration.
 * Version: 1.0
 * Requires at least: 5.0
 * Requires PHP: 5.6.20
 * Author: Philip Rudy
 * Author URI: https://philiparudy.com/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: sparkloop-forms
 * Domain Path: /languages/
 */

if(!defined('ABSPATH')) {
    exit;
}

define('SPARKLOOP_FORMS', __FILE__);

if ( ! defined( 'SPARKLOOP_FORMS_PATH' ) ) {
    define( 'SPARKLOOP_FORMS_PATH', plugin_dir_path( SPARKLOOP_FORMS ) );
}

if ( ! defined( 'PLUGIN_URL' ) ) {
    define( 'PLUGIN_URL', plugin_dir_url( SPARKLOOP_FORMS ) );
}

require 'vendor/autoload.php';
require_once 'class-options.php';
require_once 'admin-menu.php';
require_once 'email-test.php';

$options = new Options();
$admin_menu = new AdminMenu();

class SparkLoopForms {
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
     * SparkLoopForms constructor
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
        add_action('wp_head', [$this, 'enqueue_sparkloop_wp_head']);
    }

    /**
     * Set basic keys from options
     *
     * @return void
     */
    public function set_keys() {
        $this->recaptcha_key = Options::get_option('recaptcha_key');
        $this->sendgrid_api_key = Options::get_option('remote_key');
        $list_ids = Options::get_option('list_ids');
        if(!is_array($list_ids)) {
            $list_ids = [$list_ids];
        }
        $this->sendgrid_list_ids = array_merge($this->sendgrid_list_ids, $list_ids);
        $this->sparkloop_id = Options::get_option('sparkloop_id');
    }

    /**
     * Add the reCAPTCHA and sparkloop script
     *
     * @return void
     */
    public function enqueue_sparkloop_wp_head() {
        if($this->recaptcha_key) {
            echo '
                <script src="https://www.google.com/recaptcha/api.js?render='.$this->recaptcha_key.'"></script>
            ';
        } else {
            echo '
            <script type="text/javascript">
                console.log("Missing the required reCAPTCHA script.");
            </script>
            ';
        }

        if($this->sparkloop_id) {
            echo '
            <script async src="https://js.sparkloop.app/team_'.$this->sparkloop_id.'.js" data-sparkloop></script>
            ';
        }
    }

    /**
     * Enqueue our scripts and styles
     *
     * @return void
     */
    public function enqueue_scripts() {
        wp_enqueue_style('sparkloop-form-css', plugin_dir_url(SPARKLOOP_FORMS) . 'assets/sparkloopforms.css', [], null, 'all');

        wp_enqueue_script('sparkloop-form-js', plugin_dir_url(SPARKLOOP_FORMS) . 'assets/sparkloopforms.js', [], null, true);

        $data_array = array(
            'site_url' => home_url(),
            'assets_url' => PLUGIN_URL . 'assets/',
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
        add_shortcode('sparkloop_form', [$this, 'parse_shortcode']);
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
        $nonce = wp_create_nonce('forms-for-sparkloop');

        $output = "<div id=\"".$form_wrapper_id."\" class=\"sparkloop-forms--form-wrapper\">";
        if($this->recaptcha_key) {
            $output .= "
                <form id=\"sparkLoopForm\" class=\"sparkloop-forms--form\" data-count=\"".$count."\">
                    <div class=\"sparkloop-form--input-element\">
                        <label>Email <span class=\"required\">*</span></label>
                        <input
                        required
                        id=\"email\"
                        name=\"email\"
                        type=\"email\"
                        placeholder=\"Email\"
                        value=\"\" />

                        <input
                        id=\"_wpnonce\"
                        type=\"hidden\"
                        name=\"_wpnonce\"
                        value=\"".$nonce."\"
                        >
                        <button
                        type=\"submit\"
                        data-recaptcha-key=". $this->recaptcha_key .">
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

        $namespace = 'sparkloopforms';
        $route = 'sendgrid/scopes';

        register_rest_route($namespace, $route, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'get_scopes'],
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
        $nonce = $params['_wpnonce'];
        $email = $params['email'] ?? null;

        if(!wp_verify_nonce($nonce, 'forms-for-sparkloop')) {
            return new WP_REST_Response(
                [
                    "nonce"  => $nonce,
                    "nonce_verify" => wp_verify_nonce($nonce, 'forms-for-sparkloop')
                ]
            );
            die('Security check failed.');
        }

        if(!$email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new WP_Error(
                'sparkloop_forms', 'The email is not valid.'
            );
        }

       if(!$added = $this->add_subscriber($email)) {
            return new WP_Error(
                'sparkloop_forms', 'Failed to add to our own list.'
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
    public function add_email_to_sendgrid_list($email) {
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
            echo 'Caught exception: '.  $ex->getMessage();

            return new WP_Error(
                'sparkloop_forms', $ex->getMessage()
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return; // Invalid email
        }

        return $response;
    }

    /**
     * Add sub to WP database in options table (meta_kay = sparkloop_forms__subscribers)
     *
     * @param $email
     * @return bool
     */
    public function add_subscriber($email) {
        if( ! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            return false;
        }

        $email_list = Options::get_option('subscribers', []);

        if(!is_array($email_list)) {
            $email_list = [];
        }

        if(in_array($email, $email_list)) {
            return true;
        }

        $email_list[] = $email;

        return Options::update_option('subscribers', $email_list);
    }

    /**
     * Check scopes for users
     *
     * @return void
     */
    public function get_scopes() {
        $apiKey = $this->sendgrid_api_key;
        $sg = new \SendGrid($apiKey);

        try {
            $response = $sg->client->scopes()->get();
        } catch (Exception $ex) {
            echo 'Caught exception: '.  $ex->getMessage();
        }

        return rest_ensure_response(
            new \WP_REST_Response([
                $response,
            ])
        );
    }
}

$sparkLoopForms = new SparkLoopForms();
