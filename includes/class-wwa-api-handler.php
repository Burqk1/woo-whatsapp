<?php
/**
 * WhatsApp API Handler Sınıfı
 *
 * Farklı WhatsApp API sağlayıcılarını destekler:
 * - WhatsApp Business API (Meta)
 * - Twilio WhatsApp
 * - WATI
 * - Ultramsg
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_API_Handler {

    /**
     * API sağlayıcısı
     */
    private $provider;

    /**
     * API ayarları
     */
    private $settings = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->provider = get_option('wwa_api_provider', 'whatsapp_business');
        $this->load_settings();
    }

    /**
     * API ayarlarını yükle
     */
    private function load_settings() {
        $this->settings = [
            'api_token' => get_option('wwa_api_token', ''),
            'phone_number_id' => get_option('wwa_phone_number_id', ''),
            'business_account_id' => get_option('wwa_business_account_id', ''),
            'twilio_account_sid' => get_option('wwa_twilio_account_sid', ''),
            'twilio_auth_token' => get_option('wwa_twilio_auth_token', ''),
            'twilio_phone_number' => get_option('wwa_twilio_phone_number', ''),
            'ultramsg_instance_id' => get_option('wwa_ultramsg_instance_id', ''),
            'ultramsg_token' => get_option('wwa_ultramsg_token', ''),
            'wati_api_url' => get_option('wwa_wati_api_url', ''),
            'wati_api_token' => get_option('wwa_wati_api_token', '')
        ];
    }

    /**
     * Mesaj gönder
     *
     * @param string $phone Telefon numarası
     * @param string $message Mesaj içeriği
     * @param array $options Ekstra seçenekler
     * @return array ['success' => bool, 'message_id' => string, 'error' => string]
     */
    public function send_message($phone, $message, $options = []) {
        $phone = $this->format_phone_number($phone);

        if (empty($phone)) {
            return [
                'success' => false,
                'error' => __('Geçersiz telefon numarası', 'woo-whatsapp')
            ];
        }

        if (get_option('wwa_debug_mode') === 'yes' && !isset($options['force_send'])) {
            WWA()->logger->debug_log("Simülasyon mesaj gönderimi: {$phone} - {$message}");
            return [
                'success' => true,
                'message_id' => 'debug_' . uniqid(),
                'simulated' => true
            ];
        }

        $provider = get_option('wwa_api_provider', 'whatsapp_business');

        switch ($provider) {
            case 'whatsapp_business':
                return $this->send_via_whatsapp_business($phone, $message, $options);

            case 'twilio':
                return $this->send_via_twilio($phone, $message, $options);

            case 'ultramsg':
                return $this->send_via_ultramsg($phone, $message, $options);

            case 'wati':
                return $this->send_via_wati($phone, $message, $options);

            default:
                return [
                    'success' => false,
                    'error' => __('Geçersiz API sağlayıcısı', 'woo-whatsapp')
                ];
        }
    }

    /**
     * WhatsApp Business API (Meta) ile gönder
     */
    private function send_via_whatsapp_business($phone, $message, $options = []) {
        $api_token = get_option('wwa_api_token', '');
        $phone_number_id = get_option('wwa_phone_number_id', '');

        if (empty($api_token) || empty($phone_number_id)) {
            return [
                'success' => false,
                'error' => __('WhatsApp Business API ayarları eksik', 'woo-whatsapp')
            ];
        }

        $url = "https://graph.facebook.com/v18.0/{$phone_number_id}/messages";

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message
            ]
        ];

        if (!empty($options['template_name'])) {
            $body['type'] = 'template';
            $body['template'] = [
                'name' => $options['template_name'],
                'language' => ['code' => $options['language'] ?? 'tr']
            ];

            if (!empty($options['template_components'])) {
                $body['template']['components'] = $options['template_components'];
            }

            unset($body['text']);
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30
        ]);

        return $this->handle_api_response($response, 'whatsapp_business');
    }

    /**
     * Twilio WhatsApp API ile gönder
     */
    private function send_via_twilio($phone, $message, $options = []) {
        $account_sid = get_option('wwa_twilio_account_sid', '');
        $auth_token = get_option('wwa_twilio_auth_token', '');
        $from_number = get_option('wwa_twilio_phone_number', '');

        if (empty($account_sid) || empty($auth_token) || empty($from_number)) {
            return [
                'success' => false,
                'error' => __('Twilio API ayarları eksik', 'woo-whatsapp')
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

        $body = [
            'From' => 'whatsapp:' . $from_number,
            'To' => 'whatsapp:+' . $phone,
            'Body' => $message
        ];

        if (!empty($options['media_url'])) {
            $body['MediaUrl'] = $options['media_url'];
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => $body,
            'timeout' => 30
        ]);

        return $this->handle_api_response($response, 'twilio');
    }

    /**
     * Ultramsg API ile gönder
     */
    private function send_via_ultramsg($phone, $message, $options = []) {
        $instance_id = get_option('wwa_ultramsg_instance_id', '');
        $token = get_option('wwa_ultramsg_token', '');

        if (empty($instance_id) || empty($token)) {
            return [
                'success' => false,
                'error' => __('Ultramsg API ayarları eksik', 'woo-whatsapp')
            ];
        }

        $url = "https://api.ultramsg.com/{$instance_id}/messages/chat";

        $body = [
            'token' => $token,
            'to' => '+' . $phone,
            'body' => $message
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => $body,
            'timeout' => 30
        ]);

        return $this->handle_api_response($response, 'ultramsg');
    }

    /**
     * WATI API ile gönder
     */
    private function send_via_wati($phone, $message, $options = []) {
        $api_url = get_option('wwa_wati_api_url', '');
        $api_token = get_option('wwa_wati_api_token', '');

        if (empty($api_url) || empty($api_token)) {
            return [
                'success' => false,
                'error' => __('WATI API ayarları eksik', 'woo-whatsapp')
            ];
        }

        $url = rtrim($api_url, '/') . '/api/v1/sendSessionMessage/' . $phone;

        $body = [
            'messageText' => $message
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30
        ]);

        return $this->handle_api_response($response, 'wati');
    }

    /**
     * API yanıtını işle
     */
    private function handle_api_response($response, $provider) {
        if (is_wp_error($response)) {
            WWA()->logger->debug_log("API Hatası ({$provider}): " . $response->get_error_message());
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        WWA()->logger->debug_log("API Yanıtı ({$provider})", [
            'status_code' => $status_code,
            'body' => $data
        ]);

        switch ($provider) {
            case 'whatsapp_business':
                if ($status_code === 200 && isset($data['messages'][0]['id'])) {
                    return [
                        'success' => true,
                        'message_id' => $data['messages'][0]['id'],
                        'response' => $data
                    ];
                }
                break;

            case 'twilio':
                if ($status_code === 201 && isset($data['sid'])) {
                    return [
                        'success' => true,
                        'message_id' => $data['sid'],
                        'response' => $data
                    ];
                }
                break;

            case 'ultramsg':
                if (isset($data['sent']) && $data['sent'] === 'true') {
                    return [
                        'success' => true,
                        'message_id' => $data['id'] ?? uniqid(),
                        'response' => $data
                    ];
                }
                break;

            case 'wati':
                if ($status_code === 200 && isset($data['result'])) {
                    return [
                        'success' => true,
                        'message_id' => $data['result'] ?? uniqid(),
                        'response' => $data
                    ];
                }
                break;
        }

        $error_message = $this->extract_error_message($data, $provider);
        return [
            'success' => false,
            'error' => $error_message,
            'response' => $data
        ];
    }

    /**
     * API hata mesajını çıkar
     */
    private function extract_error_message($data, $provider) {
        switch ($provider) {
            case 'whatsapp_business':
                return $data['error']['message'] ?? __('WhatsApp Business API hatası', 'woo-whatsapp');

            case 'twilio':
                return $data['message'] ?? __('Twilio API hatası', 'woo-whatsapp');

            case 'ultramsg':
                return $data['error'] ?? __('Ultramsg API hatası', 'woo-whatsapp');

            case 'wati':
                return $data['message'] ?? __('WATI API hatası', 'woo-whatsapp');

            default:
                return __('Bilinmeyen API hatası', 'woo-whatsapp');
        }
    }

    /**
     * Telefon numarasını formatla
     */
    public function format_phone_number($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (empty($phone)) {
            return '';
        }

        if (strlen($phone) === 10 && $phone[0] === '5') {
            $phone = '90' . $phone;
        }

        if (strlen($phone) === 11 && $phone[0] === '0') {
            $phone = '90' . substr($phone, 1);
        }

        if (strlen($phone) < 10) {
            return '';
        }

        return $phone;
    }

    /**
     * Telefon numarasını doğrula
     */
    public function validate_phone_number($phone) {
        $formatted = $this->format_phone_number($phone);

        if (empty($formatted)) {
            return [
                'valid' => false,
                'error' => __('Telefon numarası boş olamaz', 'woo-whatsapp')
            ];
        }

        if (strlen($formatted) < 10 || strlen($formatted) > 15) {
            return [
                'valid' => false,
                'error' => __('Geçersiz telefon numarası uzunluğu', 'woo-whatsapp')
            ];
        }

        return [
            'valid' => true,
            'formatted' => $formatted
        ];
    }

    /**
     * API bağlantısını test et
     */
    public function test_connection() {
        $test_phone = get_option('wwa_admin_phone', '');

        if (empty($test_phone)) {
            return [
                'success' => false,
                'error' => __('Test için yönetici telefon numarası gerekli', 'woo-whatsapp')
            ];
        }

        $test_message = __('Bu bir test mesajıdır. Woo WhatsApp Bildirimcisi başarıyla yapılandırıldı!', 'woo-whatsapp');

        return $this->send_message($test_phone, $test_message, ['force_send' => true]);
    }

    /**
     * API sağlayıcılarını getir
     */
    public static function get_providers() {
        return [
            'whatsapp_business' => [
                'name' => 'WhatsApp Business API (Meta)',
                'description' => __('Resmi Meta WhatsApp Business API', 'woo-whatsapp'),
                'fields' => ['api_token', 'phone_number_id'],
                'docs_url' => 'https://developers.facebook.com/docs/whatsapp/cloud-api/'
            ],
            'twilio' => [
                'name' => 'Twilio WhatsApp',
                'description' => __('Twilio WhatsApp Messaging API', 'woo-whatsapp'),
                'fields' => ['twilio_account_sid', 'twilio_auth_token', 'twilio_phone_number'],
                'docs_url' => 'https://www.twilio.com/docs/whatsapp'
            ],
            'ultramsg' => [
                'name' => 'Ultramsg',
                'description' => __('Ultramsg WhatsApp API Gateway', 'woo-whatsapp'),
                'fields' => ['ultramsg_instance_id', 'ultramsg_token'],
                'docs_url' => 'https://docs.ultramsg.com/'
            ],
            'wati' => [
                'name' => 'WATI',
                'description' => __('WATI WhatsApp Business API', 'woo-whatsapp'),
                'fields' => ['wati_api_url', 'wati_api_token'],
                'docs_url' => 'https://docs.wati.io/'
            ]
        ];
    }

    /**
     * Mevcut sağlayıcının ayarlanıp ayarlanmadığını kontrol et
     * Her çağrıda veritabanından taze değerleri okur
     */
    public function is_configured() {
        $provider = get_option('wwa_api_provider', 'whatsapp_business');

        switch ($provider) {
            case 'whatsapp_business':
                $api_token = get_option('wwa_api_token', '');
                $phone_number_id = get_option('wwa_phone_number_id', '');
                return !empty($api_token) && !empty($phone_number_id);

            case 'twilio':
                $account_sid = get_option('wwa_twilio_account_sid', '');
                $auth_token = get_option('wwa_twilio_auth_token', '');
                $phone_number = get_option('wwa_twilio_phone_number', '');
                return !empty($account_sid) && !empty($auth_token) && !empty($phone_number);

            case 'ultramsg':
                $instance_id = get_option('wwa_ultramsg_instance_id', '');
                $token = get_option('wwa_ultramsg_token', '');
                return !empty($instance_id) && !empty($token);

            case 'wati':
                $api_url = get_option('wwa_wati_api_url', '');
                $api_token = get_option('wwa_wati_api_token', '');
                return !empty($api_url) && !empty($api_token);

            default:
                return false;
        }
    }

    /**
     * Ayarları yeniden yükle
     */
    public function reload_settings() {
        $this->provider = get_option('wwa_api_provider', 'whatsapp_business');
        $this->load_settings();
    }
}
