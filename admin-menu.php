<?php
/*
 * AdminMenu Class
 */
class AdminMenu
{
    const PAGE_IDENTIFIER   = 'sparkloop_forms';
    const PAGE_TEMPLATE     = 'dashboard';
    const SETTINGS_PAGE     = 'sparkloop_forms-settings';
    const DASHICON          = 'dashicons-controls-repeat';

    /**
     * @var string
     */
    private $option_group = 'sparkloop_forms_settings';

    /**
     * @var string
     */
    private $option_names = [
        'recaptcha_key',
        'remote_key',
        'list_ids',
        'sparkloop_id',
    ];

    /**
     * Prefix for options
     *
     * @var string
     */
    private $prefix;

    /**
     * Constructor for Bluefield options
     */
    public function __construct() {
        $this->prefix = Options::PREFIX;
        $this->option_group = $this->option_group;

        $this->register_hooks();
    }

    /**
     * Register our hooks
     *
     * @return void
     */
    public function register_hooks()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        //add_action('admin_menu', [$this, 'remove_duplicate_submenu']);
    }

//    public function remove_duplicate_submenu() {
//        remove_submenu_page('admin.php?page=sparkloop_forms', 'sparkloop_forms');
//    }

    /**
     * Create the admin menu item and settings page
     *
     * @return void
     */
    public function register_settings_page()
    {
        $manage_capability = $this->get_manage_capability();
        $page_identifier = $this->get_page_identifier();

        add_menu_page(
            'sparkloop forms: ' . __('Dashboard', 'sparkloop-forms'),
            'sparkloop forms',
            $manage_capability,
            $page_identifier,
            [$this, 'show_page'],
            self::DASHICON,
            98
        );

        add_submenu_page(
            $page_identifier,
            'sparkloop forms: ' . __('Settings', 'sparkloop-forms'),
            __('Settings', 'sparkloop-forms'),
            $manage_capability,
            self::SETTINGS_PAGE,
            [$this, 'show_page'],
            1
        );

        /* Front Page Options Section */
        add_settings_section(
            $this->option_group,
            'API ' . __('Settings', 'sparkloop-forms'),
            null,
            self::SETTINGS_PAGE,
            []
        );

        add_settings_field(
            $this->prefix . $this->option_names[0],
            'Google reCAPTCHA v3 Site Key',
            [$this, 'recaptcha_key_callback'],
            self::SETTINGS_PAGE,
            $this->option_group,
            []
        );

        add_settings_field(
            $this->prefix . $this->option_names[1],
            'Sendgrid API Key',
            [$this, 'remote_key_callback'],
            self::SETTINGS_PAGE,
            $this->option_group,
            []
        );

        add_settings_field(
            $this->prefix . $this->option_names[2],
            'Sendgrid List ID',
            [$this, 'list_id_callback'],
            self::SETTINGS_PAGE,
            $this->option_group,
            []
        );

        add_settings_field(
            $this->prefix . $this->option_names[3],
            'sparkloop ID',
            [$this, 'sparkloop_id'],
            self::SETTINGS_PAGE,
            $this->option_group,
            []
        );

        register_setting($this->option_group, $this->prefix . $this->option_names[0]);
        register_setting($this->option_group, $this->prefix . $this->option_names[1]);
        register_setting($this->option_group, $this->prefix . $this->option_names[2]);
        register_setting($this->option_group, $this->prefix . $this->option_names[3]);
    }

    /**
     * Returns the page identifier, which is maybe the equivalent of the menu slug
     *
     * @return string Page identifier to use
     */
    public function get_page_identifier()
    {
        return self::PAGE_IDENTIFIER;
    }

    /**
     * Returns the capability that is required to manage all options.
     *
     * @return string Capability to check against.
     */
    public function get_manage_capability()
    {
        return 'manage_options';
    }

    /**
     * @return void
     */
    public function show_page()
    {
        require_once SPARKLOOP_FORMS_PATH . 'pages/' . self::PAGE_TEMPLATE . '.php';
    }

    /**
     *
     * The html for the input fields
     *
     *
     */

    /**
     * @return void
     */
    public function recaptcha_key_callback() {
        $option_name = $this->prefix . $this->option_names[0];

        echo "<input style='width: 600px' id='" . $option_name . "' name='" . $option_name . "' type='text' value='" . esc_attr(Options::get_option($option_name)) . "' placeholder='Google reCAPTCHA v3 Site Key' />";
    }

    /**
     * @return void
     */
    public function remote_key_callback() {
        $option_name = $this->prefix . $this->option_names[1];
        echo "<input style='width: 600px' id='" . $option_name . "' name='" . $option_name . "' type='text' value='" . esc_attr(Options::get_option($option_name)) . "' placeholder='Sendgrid API Key' />";
    }

    /**
     * @return void
     */
    public function list_id_callback() {
        $option_name = $this->prefix . $this->option_names[2];

        echo "<input style='width: 600px' id='" . $option_name . "' name='" . $option_name . "' type='text' value='" . esc_attr(Options::get_option($option_name)) . "' placeholder='Sendgrid List ID' />";
    }

    /**
     * @return void
     */
    public function sparkloop_id() {
        $option_name = $this->prefix . $this->option_names[3];

        echo "
        <label style='display: block; width: 600px; margin-bottom: 3px;'>This is the unique alphenumeric ID that you can sometimes find in your Sparkloop tracking link.</label>
        <input style='width: 600px' id='" . $option_name . "' name='" . $option_name . "' type='text' value='" . esc_attr(Options::get_option($option_name)) . "' placeholder='sparkloop ID' />";
    }
}
