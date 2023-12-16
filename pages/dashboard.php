<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wrap" id="sparkloop_forms-admin">
<h1>Settings - forms for sparkloop</h1>

<?php
settings_errors();
?>

<div class="pluginbuilder_content_wrapper">
    <?php
    $default_tab = 'sparkloopforms_settings';
    $active_tab = isset( $_GET[ 'tab' ] ) ? sanitize_text_field(wp_unslash($_GET[ 'tab' ])) : $default_tab;
    $allowed_tabs = ['sparkloopforms_settings'];

    if (!in_array($active_tab, $allowed_tabs)) {
        $active_tab = $default_tab;
    }
    ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=forms_for_sparkloop-settings&tab=sparkloopforms_settings" class="nav-tab <?php echo $active_tab == 'sparkloopforms_settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
    </h2>

    <form method="post" action="options.php">
        <?php
            if( $active_tab == 'sparkloopforms_settings' ) {
                settings_fields( 'forms_for_sparkloop_settings' );
                do_settings_sections('forms_for_sparkloop-settings');

                submit_button();
            }
        ?>
    </form>
</div>
</div>
