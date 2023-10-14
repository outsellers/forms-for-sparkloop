<?php

class SendgridTest
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route() {
        register_rest_route('sparkloopforms', 'sendgrid-test', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'send_test'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function send_test() {
        require 'vendor/autoload.php'; // If you're using Composer (recommended)
// Comment out the above line if not using Composer
// require("<PATH TO>/sendgrid-php.php");
// If not using Composer, uncomment the above line and
// download sendgrid-php.zip from the latest release here,
// replacing <PATH TO> with the path to the sendgrid-php.php file,
// which is included in the download:
// https://github.com/sendgrid/sendgrid-php/releases

        $email = new \SendGrid\Mail\Mail();
        $email->setFrom("me@philiparudy.com", "Philip Rudy");
        $email->setSubject("Sending with SendGrid is Fun");
        $email->addTo("info@philiprudy.com", "Philip Info User");
        $email->addContent("text/plain", "and easy to do anywhere, even with PHP");
        $email->addContent(
            "text/html", "<strong>and easy to do anywhere, even with PHP</strong>"
        );
        $sendgrid = new \SendGrid('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');
        try {
            $response = $sendgrid->send($email);
            print $response->statusCode() . "\n";
            print_r($response->headers());
            print $response->body() . "\n";
        } catch (Exception $e) {
            echo 'Caught exception: ' . $e->getMessage() . "\n";
        }
    }

}

$sendgrid_test = new SendgridTest();
