<?php

/**
 * @wordpress-plugin
 * Plugin Name:       WP Salesforce Rest Client
 * Plugin URI:        https://github.com/fjborquez/wp-salesforce-rest-client
 * Description:       Plugin que permite el consumo de la API Rest de una instancia de Salesforce
 * Version:           1.0.0
 * Author:            Francisco Bórquez
 * Author URI:        https://www.linkedin.com/in/francisco-b%C3%B3rquez-hern%C3%A1ndez/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define('WP-SALESFORCE-REST-CLIENT_VERSION', '1.0.0');

if (!class_exists('WPSalesforceRestClient')) {
    class WPSalesforceRestClient {
        private $restClientToken;
        private $properties;
        private $url = "https://<instance>.salesforce.com/services/data/v48.0/query/";
        private $headers;

        function __construct() {
            $options = get_option( 'WPSalesforceRestClient_options' );

            $this->properties = [
                'salesforce' => [
                    'instance' => isset($options['instance']) ? $options['instance'] : '',
                    'client_id' => isset($options['client_id']) ? $options['client_id'] : '',
                    'client_secret' => isset($options['client_secret']) ? $options['client_secret'] : '',
                    'username' => isset($options['username']) ? $options['username'] : '',
                    'password' => isset($options['password']) ? $options['password'] : ''
                ]
            ];

            if (isset($options['security_token'])) {
                $this->properties['salesforce']['password'] .= $options['security_token'];
            }

            $this->headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Authorization: Bearer <token>'
            ];

            $this->url = str_replace("<instance>", $this->properties['salesforce']['instance'], $this->url);
            $this->restClientToken = new WPSalesforceRestClientToken($this->properties);
        }

        function executeQuery($query) {
            try {
                $tokenResponse = $this->restClientToken->getTokenResponse();
                $query = '?q=' . $query;
                $this->headers['Authorization'] = str_replace("<token>", $tokenResponse->access_token, $this->headers['Authorization'] );

                $args = [
                    'headers' => $this->headers
                ];
                
                $response = wp_remote_get($this->url . $query, $args);

                if ($response instanceof WP_Error) {
                    throw new Exception("Error trying to connect to instance");
                } else {
                    $responseBody = json_decode($response['body']);

                    if (is_array($responseBody)) {
                        throw new Exception("Error executing query");
                    } else {
                        return $responseBody;
                    }
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }
}

if (!class_exists('WPSalesforceRestClientToken')) {
    class WPSalesforceRestClientToken {
        private $response;
        private $properties;
        private $url = "https://<instance>.salesforce.com/services/oauth2/token";
        private $body;

        function __construct($properties) {
            $this->properties = $properties;
            $this->url = str_replace("<instance>", $this->properties['salesforce']['instance'], $this->url);

            $body = [
                'grant_type' => 'password',
                'client_id' => $this->properties['salesforce']['client_id'],
                'client_secret' => $this->properties['salesforce']['client_secret'],
                'username' => $this->properties['salesforce']['username'],
                'password' => $this->properties['salesforce']['password']
            ];

            $this->body = $body;
        }
        
        // TODO: retornar token, si ya no es valido volver a generarlo
        // TODO: Determinar si es necesario solicitar token de actualizacion
        public function getTokenResponse() {
            return $this->generateToken();
            
        }

        // TODO: obtener token desde salesforce
        private function generateToken() {
            $response = null;
            $args = [
                'body'        => $this->body,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => [],
                'cookies'     => [],
            ];
        
            $response = wp_remote_post($this->url, $args);
            
            if ($response instanceof WP_Error) {
                throw new Exception("Error trying to connect to instance");
            } else {
                $responseBody = json_decode($response['body']);

                if (isset($responseBody->error)) {
                    throw new Exception("Error with Salesforce login credentials");
                } else {
                    return $responseBody;
                }
            }
        }

        // TODO: Comprobar que token siga vigente
        private function checkExpirated() {

        }
    }
}

function WPSalesforceRestClient_settings_page() {
    ?>
    <h2>WP Salesforce Rest Client</h2>
    <form action="options.php" method="post">
        <?php 
        settings_fields('WPSalesforceRestClient_options');
        do_settings_sections( 'WPSalesforceRestClient' ); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
    </form>
    <?php
}


function WPSalesforceRestClient_register_settings() {
    register_setting( 'WPSalesforceRestClient_options', 'WPSalesforceRestClient_options', 'WPSalesforceRestClient_options_validate' );
    add_settings_section( 'WPSalesforceRestClient_settings', 'Settings', 'WPSalesforceRestClient_section_text', 'WPSalesforceRestClient' );

    add_settings_field( 'WPSalesforceRestClient_setting_instance', 'Instance', 'WPSalesforceRestClient_setting_instance', 'WPSalesforceRestClient', 'WPSalesforceRestClient_settings' );
    add_settings_field( 'WPSalesforceRestClient_setting_client_id', 'Client id', 'WPSalesforceRestClient_setting_client_id', 'WPSalesforceRestClient', 'WPSalesforceRestClient_settings' );
    add_settings_field( 'WPSalesforceRestClient_setting_client_secret', 'Client secret', 'WPSalesforceRestClient_setting_client_secret', 'WPSalesforceRestClient', 'WPSalesforceRestClient_settings' );
    add_settings_field( 'WPSalesforceRestClient_setting_username', 'Username', 'WPSalesforceRestClient_setting_username', 'WPSalesforceRestClient', 'WPSalesforceRestClient_settings' );
    add_settings_field( 'WPSalesforceRestClient_setting_password', 'Password', 'WPSalesforceRestClient_setting_password', 'WPSalesforceRestClient', 'WPSalesforceRestClient_settings' );
    add_settings_field( 'WPSalesforceRestClient_setting_security_token', 'Security token', 'WPSalesforceRestClient_setting_security_token', 'WPSalesforceRestClient', 'WPSalesforceRestClient_settings' );

}

add_action( 'admin_init', 'WPSalesforceRestClient_register_settings' );

function WPSalesforceRestClient_options_validate( $input ) {
    return $input;
}

function WPSalesforceRestClient_section_text() {
    echo '<p>Configure plugin parameters.</p>';
}

function WPSalesforceRestClient_setting_instance() {
    $options = get_option( 'WPSalesforceRestClient_options' );
    $value = '';
    
    if (isset($options['instance'])) {
        $value = esc_attr($options['instance']);
    }

    echo "<input id='WPSalesforceRestClient_setting_instance' name='WPSalesforceRestClient_options[instance]' type='text' value='" .  $value . "' />";
}

function WPSalesforceRestClient_setting_client_id() {
    $options = get_option( 'WPSalesforceRestClient_options' );
    $value = '';
    
    if (isset($options['client_id'])) {
        $value = esc_attr($options['client_id']);
    }

    echo "<input id='WPSalesforceRestClient_setting_client_id' name='WPSalesforceRestClient_options[client_id]' type='text' value='" . $value . "' />";
}

function WPSalesforceRestClient_setting_client_secret() {
    $options = get_option( 'WPSalesforceRestClient_options' );
    $value = '';
    
    if (isset($options['client_secret'])) {
        $value = esc_attr($options['client_secret']);
    }

    echo "<input id='WPSalesforceRestClient_setting_client_secret' name='WPSalesforceRestClient_options[client_secret]' type='text' value='" . $value . "' />";
}

function WPSalesforceRestClient_setting_username() {
    $options = get_option( 'WPSalesforceRestClient_options' );
    $value = '';
    
    if (isset($options['username'])) {
        $value = esc_attr($options['username']);
    }

    echo "<input id='WPSalesforceRestClient_setting_username' name='WPSalesforceRestClient_options[username]' type='text' value='" . $value . "' />";
}

function WPSalesforceRestClient_setting_password() {
    $options = get_option( 'WPSalesforceRestClient_options' );
    $value = '';
    
    if (isset($options['password'])) {
        $value = esc_attr($options['password']);
    }

    echo "<input id='WPSalesforceRestClient_setting_password' name='WPSalesforceRestClient_options[password]' type='text' value='" . $value . "' />";
}

function WPSalesforceRestClient_setting_security_token() {
    $options = get_option( 'WPSalesforceRestClient_options' );
    $value = '';
    
    if (isset($options['security_token'])) {
        $value = esc_attr($options['security_token']);
    }

    echo "<input id='WPSalesforceRestClient_setting_security_token' name='WPSalesforceRestClient_options[security_token]' type='text' value='" .  $value . "' />";
}

function WPSalesforceRestClient_add_settings_page() {
    add_options_page( 'WP Salesforce Rest Client Settings', 'WP Salesforce Rest Client Settings', 'manage_options', 'wp-salesforce-rest-client', 'WPSalesforceRestClient_settings_page' );
}

add_action( 'admin_menu', 'WPSalesforceRestClient_add_settings_page' );

function WPSalesforceRestClient_settings_link($links) { 
    $settings_link = '<a href="options-general.php?page=wp-salesforce-rest-client">Settings</a>'; 
    array_unshift($links, $settings_link);

    return $links; 
  }

$plugin_filename = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin_filename", 'WPSalesforceRestClient_settings_link' );
