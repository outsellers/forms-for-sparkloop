<div class="wrap" id="sparkloop_forms-admin">
<?php
echo '<h1>Settings - sparkloop forms</h1>';
settings_errors();
?>

<div class="pluginbuilder_content_wrapper">
    <?php
    $active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'sparkloopforms_settings';
    ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=sparkloop_forms-settings&tab=sparkloopforms_settings" class="nav-tab <?php echo $active_tab == 'sparkloopforms_settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
    </h2>

    <form method="post" action="options.php">
        <?php
            if( $active_tab == 'sparkloopforms_settings' ) {
                settings_fields( 'sparkloop_forms_settings' );
                do_settings_sections('sparkloop_forms-settings');

                submit_button();
            }
        ?>
    </form>
</div>
</div>
