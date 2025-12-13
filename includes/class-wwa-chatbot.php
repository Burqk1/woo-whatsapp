<?php
/**
 * WhatsApp Chatbot (Auto-Reply) Sınıfı
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_Chatbot {

    /**
     * Tablo adı
     */
    private $table_rules;
    private $table_conversations;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_rules = $wpdb->prefix . 'wwa_chatbot_rules';
        $this->table_conversations = $wpdb->prefix . 'wwa_chatbot_conversations';

        // Tablolar yoksa oluştur
        $this->maybe_create_tables();

        $this->init_hooks();
    }

    /**
     * Tablolar yoksa oluştur
     */
    private function maybe_create_tables() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_rules}'") !== $this->table_rules) {
            self::create_tables();
        }
    }

    /**
     * Tabloları oluştur
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Chatbot rules tablosu
        $table_rules = $wpdb->prefix . 'wwa_chatbot_rules';
        $sql_rules = "CREATE TABLE IF NOT EXISTS $table_rules (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            keywords text NOT NULL,
            match_type varchar(20) DEFAULT 'contains',
            response_type varchar(20) DEFAULT 'text',
            response_content longtext NOT NULL,
            action_type varchar(50) DEFAULT NULL,
            action_config longtext DEFAULT NULL,
            priority int(11) DEFAULT 10,
            status varchar(20) DEFAULT 'active',
            usage_count bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY priority (priority)
        ) $charset_collate;";

        // Conversations tablosu
        $table_conversations = $wpdb->prefix . 'wwa_chatbot_conversations';
        $sql_conversations = "CREATE TABLE IF NOT EXISTS $table_conversations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(50) NOT NULL,
            customer_name varchar(255) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            message_in text NOT NULL,
            message_out text DEFAULT NULL,
            rule_id bigint(20) DEFAULT NULL,
            status varchar(20) DEFAULT 'processed',
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY order_id (order_id),
            KEY rule_id (rule_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_rules);
        dbDelta($sql_conversations);
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        // Webhook endpoint for incoming messages
        add_action('rest_api_init', [$this, 'register_webhook_endpoints']);

        // Varsayılan kuralları ekle
        add_action('wwa_activated', [$this, 'add_default_rules']);
    }

    /**
     * Webhook endpointlerini kaydet
     */
    public function register_webhook_endpoints() {
        // WhatsApp Business API webhook
        register_rest_route('wwa/v1', '/webhook/whatsapp', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'verify_webhook'],
                'permission_callback' => '__return_true'
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_incoming_message'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Twilio webhook
        register_rest_route('wwa/v1', '/webhook/twilio', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_twilio_message'],
            'permission_callback' => '__return_true'
        ]);

        // Ultramsg webhook
        register_rest_route('wwa/v1', '/webhook/ultramsg', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ultramsg_message'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Meta webhook doğrulama
     */
    public function verify_webhook($request) {
        $mode = $request->get_param('hub_mode');
        $token = $request->get_param('hub_verify_token');
        $challenge = $request->get_param('hub_challenge');

        $verify_token = get_option('wwa_webhook_verify_token', '');

        if ($mode === 'subscribe' && $token === $verify_token) {
            return new WP_REST_Response($challenge, 200);
        }

        return new WP_REST_Response('Forbidden', 403);
    }

    /**
     * Gelen WhatsApp mesajını işle (Meta API)
     */
    public function handle_incoming_message($request) {
        $body = $request->get_json_params();

        WWA()->logger->debug_log('Incoming WhatsApp message', $body);

        // Meta API format
        if (isset($body['entry'][0]['changes'][0]['value']['messages'][0])) {
            $message_data = $body['entry'][0]['changes'][0]['value']['messages'][0];
            $contact = $body['entry'][0]['changes'][0]['value']['contacts'][0] ?? [];

            $phone = $message_data['from'] ?? '';
            $text = $message_data['text']['body'] ?? '';
            $name = $contact['profile']['name'] ?? '';
            $message_id = $message_data['id'] ?? '';

            if ($phone && $text) {
                $this->process_message($phone, $text, $name, [
                    'message_id' => $message_id,
                    'source' => 'meta'
                ]);
            }
        }

        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Twilio webhook handler
     */
    public function handle_twilio_message($request) {
        $from = $request->get_param('From') ?? '';
        $body = $request->get_param('Body') ?? '';
        $name = $request->get_param('ProfileName') ?? '';

        // WhatsApp prefix'ini kaldır
        $phone = str_replace('whatsapp:', '', $from);
        $phone = ltrim($phone, '+');

        if ($phone && $body) {
            $this->process_message($phone, $body, $name, ['source' => 'twilio']);
        }

        // TwiML response
        header('Content-Type: text/xml');
        return new WP_REST_Response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200);
    }

    /**
     * Ultramsg webhook handler
     */
    public function handle_ultramsg_message($request) {
        $body = $request->get_json_params();

        // Eğer JSON boşsa, form data olarak dene
        if (empty($body)) {
            $body = $request->get_params();
        }

        // Debug log - her zaman logla
        error_log('WWA Ultramsg Webhook Raw: ' . print_r($body, true));

        // Ultramsg farklı formatlar gönderebilir
        // Format 1: { "id": "...", "from": "905..@c.us", "to": "...", "body": "merhaba", ... }
        // Format 2: { "data": { "from": "...", "body": "..." } }
        // Format 3: { "event_type": "message_received", "data": { ... } }

        $phone = '';
        $text = '';
        $name = '';
        $message_id = '';

        // Doğrudan from/body varsa (Format 1)
        if (isset($body['from']) && isset($body['body'])) {
            $phone = $body['from'];
            $text = $body['body'];
            $name = $body['pushname'] ?? $body['notifyName'] ?? '';
            $message_id = $body['id'] ?? '';
        }
        // Data içinde varsa (Format 2 & 3)
        elseif (isset($body['data'])) {
            $data = $body['data'];
            $phone = $data['from'] ?? '';
            $text = $data['body'] ?? '';
            $name = $data['pushname'] ?? $data['notifyName'] ?? '';
            $message_id = $data['id'] ?? '';
        }

        // Kendi gönderdiğimiz mesajları atla (fromMe = true)
        $fromMe = $body['fromMe'] ?? $body['data']['fromMe'] ?? false;
        if ($fromMe === true || $fromMe === 'true' || $fromMe === '1') {
            error_log('WWA: Skipping outgoing message');
            return new WP_REST_Response(['status' => 'ok', 'skipped' => 'outgoing'], 200);
        }

        // Telefon numarasından @c.us kısmını kaldır
        $phone = str_replace('@c.us', '', $phone);
        $phone = str_replace('@s.whatsapp.net', '', $phone);
        $phone = preg_replace('/[^0-9]/', '', $phone);

        error_log("WWA Ultramsg Parsed: phone={$phone}, text={$text}, name={$name}");

        if ($phone && $text) {
            $result = $this->process_message($phone, $text, $name, [
                'message_id' => $message_id,
                'source' => 'ultramsg'
            ]);
            error_log('WWA Process Result: ' . print_r($result, true));
        } else {
            error_log('WWA: No phone or text found in webhook');
        }

        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Mesajı işle ve yanıtla
     */
    public function process_message($phone, $message, $customer_name = '', $metadata = []) {
        // Mesajı temizle ve küçük harfe çevir
        $message_lower = mb_strtolower(trim($message), 'UTF-8');

        // Eşleşen kuralı bul
        $matched_rule = $this->find_matching_rule($message_lower);

        $response = null;
        $action_result = null;

        if ($matched_rule) {
            // Yanıt hazırla
            $response = $this->prepare_response($matched_rule, $phone, $message, $customer_name);

            // Action varsa çalıştır
            if (!empty($matched_rule['action_type'])) {
                $action_result = $this->execute_action($matched_rule, $phone, $message, $customer_name);
            }

            // Yanıtı gönder
            if ($response) {
                WWA()->api->send_message($phone, $response);
            }

            // Kullanım sayacını artır
            $this->increment_rule_usage($matched_rule['id']);
        } else {
            // Varsayılan yanıt
            $default_response = get_option('wwa_chatbot_default_response', '');
            if ($default_response) {
                $response = $this->parse_placeholders($default_response, $phone, $customer_name);
                WWA()->api->send_message($phone, $response);
            }
        }

        // Konuşmayı kaydet
        $this->log_conversation($phone, $message, $response, $matched_rule, $customer_name, $metadata);

        return [
            'matched_rule' => $matched_rule ? $matched_rule['id'] : null,
            'response' => $response,
            'action_result' => $action_result
        ];
    }

    /**
     * Eşleşen kuralı bul
     */
    private function find_matching_rule($message) {
        $rules = $this->get_active_rules();

        foreach ($rules as $rule) {
            $keywords = json_decode($rule['keywords'], true) ?: explode(',', $rule['keywords']);
            $match_type = $rule['match_type'];

            foreach ($keywords as $keyword) {
                $keyword = mb_strtolower(trim($keyword), 'UTF-8');

                if (empty($keyword)) continue;

                $matched = false;

                switch ($match_type) {
                    case 'exact':
                        $matched = ($message === $keyword);
                        break;

                    case 'contains':
                        $matched = (strpos($message, $keyword) !== false);
                        break;

                    case 'starts_with':
                        $matched = (strpos($message, $keyword) === 0);
                        break;

                    case 'ends_with':
                        $matched = (substr($message, -strlen($keyword)) === $keyword);
                        break;

                    case 'regex':
                        $matched = (preg_match('/' . $keyword . '/ui', $message) === 1);
                        break;
                }

                if ($matched) {
                    return $rule;
                }
            }
        }

        return null;
    }

    /**
     * Yanıt hazırla
     */
    private function prepare_response($rule, $phone, $message, $customer_name) {
        $response_type = $rule['response_type'];
        $content = $rule['response_content'];

        switch ($response_type) {
            case 'text':
                return $this->parse_placeholders($content, $phone, $customer_name, $message);

            case 'template':
                // Önceden tanımlı şablondan
                $templates = json_decode($content, true);
                if (is_array($templates) && !empty($templates)) {
                    // Rastgele bir şablon seç (çeşitlilik için)
                    $template = $templates[array_rand($templates)];
                    return $this->parse_placeholders($template, $phone, $customer_name, $message);
                }
                return $content;

            case 'dynamic':
                // Dinamik içerik (sipariş durumu vb.)
                return $this->generate_dynamic_response($content, $phone, $message);

            default:
                return $content;
        }
    }

    /**
     * Placeholder'ları değiştir
     */
    private function parse_placeholders($text, $phone, $customer_name, $original_message = '') {
        $replacements = [
            '{müşteri_adı}' => $customer_name ?: __('Değerli Müşterimiz', 'woo-whatsapp'),
            '{mağaza_adı}' => get_bloginfo('name'),
            '{telefon}' => $phone,
            '{tarih}' => date_i18n(get_option('date_format')),
            '{saat}' => date_i18n(get_option('time_format')),
            '{mesaj}' => $original_message
        ];

        // Sipariş bilgisi varsa ekle
        $order = $this->find_customer_order($phone);
        if ($order) {
            $replacements['{sipariş_no}'] = $order->get_order_number();
            $replacements['{sipariş_durumu}'] = wc_get_order_status_name($order->get_status());
            $replacements['{toplam_tutar}'] = $this->format_price_plain($order->get_total(), $order);
        }

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Dinamik yanıt oluştur
     */
    private function generate_dynamic_response($type, $phone, $message) {
        switch ($type) {
            case 'order_status':
                return $this->get_order_status_response($phone, $message);

            case 'tracking_info':
                return $this->get_tracking_info_response($phone, $message);

            case 'order_list':
                return $this->get_order_list_response($phone);

            default:
                return null;
        }
    }

    /**
     * Sipariş durumu yanıtı
     */
    private function get_order_status_response($phone, $message) {
        // Mesajdan sipariş numarası çıkar
        preg_match('/\d{4,}/', $message, $matches);
        $order_number = $matches[0] ?? null;

        if ($order_number) {
            // Sipariş numarasıyla ara
            $orders = wc_get_orders([
                'limit' => 1,
                'meta_key' => '_order_number',
                'meta_value' => $order_number
            ]);

            if (empty($orders)) {
                // ID ile dene
                $order = wc_get_order($order_number);
            } else {
                $order = $orders[0];
            }
        } else {
            // Telefon numarasıyla son siparişi bul
            $order = $this->find_customer_order($phone);
        }

        if (!$order) {
            return __("Üzgünüm, belirttiğiniz sipariş bulunamadı. Lütfen sipariş numaranızı kontrol edin veya bize ulaşın.", 'woo-whatsapp');
        }

        $status_labels = [
            'pending' => '⏳ Ödeme Bekleniyor',
            'processing' => '📦 Hazırlanıyor',
            'on-hold' => '⏸️ Beklemede',
            'completed' => '✅ Tamamlandı',
            'cancelled' => '❌ İptal Edildi',
            'refunded' => '💰 İade Edildi',
            'failed' => '❗ Başarısız'
        ];

        $status = $status_labels[$order->get_status()] ?? $order->get_status();

        return sprintf(
            __("📋 *Sipariş Durumu*\n\nSipariş No: #%s\nDurum: %s\nTutar: %s\nTarih: %s\n\nSorularınız için bize ulaşabilirsiniz.", 'woo-whatsapp'),
            $order->get_order_number(),
            $status,
            $this->format_price_plain($order->get_total(), $order),
            wc_format_datetime($order->get_date_created())
        );
    }

    /**
     * Kargo takip yanıtı
     */
    private function get_tracking_info_response($phone, $message) {
        $order = $this->find_customer_order($phone);

        if (!$order) {
            return __("Sipariş bulunamadı. Lütfen sipariş numaranızı belirtin.", 'woo-whatsapp');
        }

        $tracking_number = $order->get_meta('_wwa_tracking_number') ?: $order->get_meta('_tracking_number');
        $tracking_company = $order->get_meta('_wwa_tracking_company') ?: $order->get_meta('_tracking_company') ?: 'Kargo Firması';

        if (!$tracking_number) {
            return sprintf(
                __("📦 Sipariş #%s için henüz kargo bilgisi girilmemiş.\n\nSipariş durumu: %s\n\nKargoya verildiğinde size bilgi vereceğiz.", 'woo-whatsapp'),
                $order->get_order_number(),
                wc_get_order_status_name($order->get_status())
            );
        }

        return sprintf(
            __("🚚 *Kargo Bilgisi*\n\nSipariş No: #%s\nKargo Firması: %s\nTakip No: %s\n\nKargonuzu bu numara ile takip edebilirsiniz.", 'woo-whatsapp'),
            $order->get_order_number(),
            $tracking_company,
            $tracking_number
        );
    }

    /**
     * Sipariş listesi yanıtı
     */
    private function get_order_list_response($phone) {
        $customer = $this->find_customer_by_phone($phone);

        if (!$customer) {
            return __("Telefon numaranıza ait sipariş bulunamadı.", 'woo-whatsapp');
        }

        $orders = wc_get_orders([
            'customer_id' => $customer->ID,
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        if (empty($orders)) {
            return __("Henüz siparişiniz bulunmuyor.", 'woo-whatsapp');
        }

        $response = __("📋 *Son Siparişleriniz*\n\n", 'woo-whatsapp');

        foreach ($orders as $order) {
            $response .= sprintf(
                "#%s - %s - %s\n",
                $order->get_order_number(),
                wc_get_order_status_name($order->get_status()),
                $this->format_price_plain($order->get_total(), $order)
            );
        }

        $response .= __("\nDetay için sipariş numarasını yazabilirsiniz.", 'woo-whatsapp');

        return $response;
    }

    /**
     * Action çalıştır
     */
    private function execute_action($rule, $phone, $message, $customer_name) {
        $action_type = $rule['action_type'];
        $action_config = json_decode($rule['action_config'], true) ?: [];

        switch ($action_type) {
            case 'notify_admin':
                // Admin'e bildirim gönder
                $admin_phone = WWA()->settings->get('admin_phone');
                if ($admin_phone) {
                    $notification = sprintf(
                        __("📩 Yeni müşteri mesajı!\n\nMüşteri: %s\nTelefon: %s\nMesaj: %s\n\nEşleşen Kural: %s", 'woo-whatsapp'),
                        $customer_name ?: 'Bilinmiyor',
                        $phone,
                        $message,
                        $rule['name']
                    );
                    WWA()->api->send_message($admin_phone, $notification);
                }
                break;

            case 'create_ticket':
                // Destek talebi oluştur (eğer entegrasyon varsa)
                // Bu özellik ileride eklenebilir
                break;

            case 'tag_customer':
                // Müşteriye etiket ekle
                $user = $this->find_customer_by_phone($phone);
                if ($user && !empty($action_config['tag'])) {
                    $tags = get_user_meta($user->ID, '_wwa_tags', true) ?: [];
                    $tags[] = sanitize_text_field($action_config['tag']);
                    update_user_meta($user->ID, '_wwa_tags', array_unique($tags));
                }
                break;

            case 'trigger_flow':
                // Flow tetikle
                if (!empty($action_config['flow_id']) && class_exists('WWA_Flow_Builder')) {
                    $flow_builder = new WWA_Flow_Builder();
                    $flow = $flow_builder->get_flow($action_config['flow_id']);
                    if ($flow) {
                        $flow_builder->execute_flow($flow, [
                            'phone' => $phone,
                            'message' => $message,
                            'customer_name' => $customer_name
                        ]);
                    }
                }
                break;
        }

        return true;
    }

    /**
     * Fiyatı düz metin olarak formatla (HTML olmadan)
     *
     * @param float $price Fiyat
     * @param WC_Order|null $order Sipariş objesi (para birimi için)
     * @return string Formatlanmış fiyat
     */
    private function format_price_plain($price, $order = null) {
        // Siparişin para birimini al (müşteri hangi para birimiyle ödediyse o)
        $currency = $order ? $order->get_currency() : get_woocommerce_currency();

        // HTML entity'leri decode et (&#36; -> $)
        $currency_symbol = html_entity_decode(get_woocommerce_currency_symbol($currency), ENT_QUOTES, 'UTF-8');
        $decimals = wc_get_price_decimals();
        $decimal_separator = wc_get_price_decimal_separator();
        $thousand_separator = wc_get_price_thousand_separator();
        $currency_position = get_option('woocommerce_currency_pos', 'left');

        $formatted_price = number_format(
            (float) $price,
            $decimals,
            $decimal_separator,
            $thousand_separator
        );

        switch ($currency_position) {
            case 'left':
                return $currency_symbol . $formatted_price;
            case 'right':
                return $formatted_price . $currency_symbol;
            case 'left_space':
                return $currency_symbol . ' ' . $formatted_price;
            case 'right_space':
                return $formatted_price . ' ' . $currency_symbol;
            default:
                return $currency_symbol . $formatted_price;
        }
    }

    /**
     * Telefon numarasıyla müşteri bul
     */
    private function find_customer_by_phone($phone) {
        // Formatı temizle
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);

        // User meta'dan ara
        $users = get_users([
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'billing_phone',
                    'value' => $phone_clean,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'billing_phone',
                    'value' => $phone,
                    'compare' => 'LIKE'
                ]
            ],
            'number' => 1
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Telefon numarasıyla son siparişi bul
     */
    private function find_customer_order($phone) {
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);

        // Son 10 hanesi ile ara (ülke kodu olmadan)
        $phone_short = substr($phone_clean, -10);

        $orders = wc_get_orders([
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'billing_phone' => $phone_short
        ]);

        return !empty($orders) ? $orders[0] : null;
    }

    /**
     * Konuşmayı kaydet
     */
    private function log_conversation($phone, $message_in, $message_out, $rule, $customer_name, $metadata) {
        global $wpdb;

        $order = $this->find_customer_order($phone);
        $user = $this->find_customer_by_phone($phone);

        $wpdb->insert($this->table_conversations, [
            'phone' => $phone,
            'customer_name' => $customer_name,
            'user_id' => $user ? $user->ID : null,
            'order_id' => $order ? $order->get_id() : null,
            'message_in' => $message_in,
            'message_out' => $message_out,
            'rule_id' => $rule ? $rule['id'] : null,
            'status' => $message_out ? 'responded' : 'no_match',
            'metadata' => wp_json_encode($metadata)
        ], ['%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s']);
    }

    /**
     * Kural kullanım sayacını artır
     */
    private function increment_rule_usage($rule_id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_rules} SET usage_count = usage_count + 1 WHERE id = %d",
            $rule_id
        ));
    }

    /**
     * Aktif kuralları getir (önceliğe göre sıralı)
     */
    public function get_active_rules() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_rules} WHERE status = 'active' ORDER BY priority ASC",
            ARRAY_A
        );
    }

    /**
     * Tüm kuralları getir
     */
    public function get_rules($args = []) {
        global $wpdb;

        $defaults = [
            'status' => null,
            'orderby' => 'priority',
            'order' => 'ASC',
            'limit' => 100
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'priority ASC';

        $sql = "SELECT * FROM {$this->table_rules} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d";
        $values[] = $args['limit'];

        $rules = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        // keywords JSON'ını decode et ve response alanını ekle
        foreach ($rules as &$rule) {
            if (isset($rule['keywords'])) {
                $rule['keywords'] = json_decode($rule['keywords'], true);
            }
            // response_content'i response olarak da ekle (UI uyumluluğu için)
            if (isset($rule['response_content'])) {
                $rule['response'] = $rule['response_content'];
            }
        }

        return $rules;
    }

    /**
     * Kural kaydet
     */
    public function save_rule($data) {
        global $wpdb;

        $rule_data = [
            'name' => sanitize_text_field($data['name']),
            'keywords' => wp_json_encode($data['keywords']),
            'match_type' => sanitize_text_field($data['match_type'] ?? 'contains'),
            'response_type' => sanitize_text_field($data['response_type'] ?? 'text'),
            'response_content' => sanitize_textarea_field($data['response_content']),
            'action_type' => sanitize_text_field($data['action_type'] ?? ''),
            'action_config' => wp_json_encode($data['action_config'] ?? []),
            'priority' => absint($data['priority'] ?? 10),
            'status' => sanitize_text_field($data['status'] ?? 'active')
        ];

        if (!empty($data['id'])) {
            $wpdb->update($this->table_rules, $rule_data, ['id' => absint($data['id'])]);
            return absint($data['id']);
        } else {
            $wpdb->insert($this->table_rules, $rule_data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Yeni kural oluştur (REST API için alias)
     */
    public function create_rule($data) {
        // is_active değerini status'a çevir
        if (isset($data['is_active'])) {
            $data['status'] = ($data['is_active'] === true || $data['is_active'] === '1' || $data['is_active'] === 1) ? 'active' : 'inactive';
        }
        // response alanını response_content'e map et
        if (isset($data['response']) && !isset($data['response_content'])) {
            $data['response_content'] = $data['response'];
        }
        return $this->save_rule($data);
    }

    /**
     * Kural güncelle (REST API için alias)
     */
    public function update_rule($id, $data) {
        // is_active değerini status'a çevir
        if (isset($data['is_active'])) {
            $data['status'] = ($data['is_active'] === true || $data['is_active'] === '1' || $data['is_active'] === 1) ? 'active' : 'inactive';
        }
        // response alanını response_content'e map et
        if (isset($data['response']) && !isset($data['response_content'])) {
            $data['response_content'] = $data['response'];
        }
        $data['id'] = $id;
        return $this->save_rule($data);
    }

    /**
     * Tek kural getir
     */
    public function get_rule($id) {
        global $wpdb;
        $rule = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_rules} WHERE id = %d", $id),
            ARRAY_A
        );
        if ($rule && isset($rule['keywords'])) {
            $rule['keywords'] = json_decode($rule['keywords'], true);
        }
        return $rule;
    }

    /**
     * Kural durumunu değiştir
     */
    public function toggle_rule($id) {
        global $wpdb;

        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$this->table_rules} WHERE id = %d",
            $id
        ));

        $new_status = ($current === 'active') ? 'inactive' : 'active';

        $wpdb->update(
            $this->table_rules,
            ['status' => $new_status],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $new_status;
    }

    /**
     * Kural sil
     */
    public function delete_rule($id) {
        global $wpdb;
        return $wpdb->delete($this->table_rules, ['id' => $id], ['%d']);
    }

    /**
     * Varsayılan kuralları ekle
     */
    public function add_default_rules() {
        $defaults = [
            [
                'name' => 'Sipariş Durumu Sorgulama',
                'keywords' => ['sipariş', 'siparişim', 'siparis', 'kargom', 'nerede', 'durum', 'ne zaman'],
                'match_type' => 'contains',
                'response_type' => 'dynamic',
                'response_content' => 'order_status',
                'response_message' => "📦 Sipariş bilgilerinizi kontrol ediyorum...\n\nSipariş bulunamadıysa lütfen sipariş numaranızı yazın.",
                'priority' => 1
            ],
            [
                'name' => 'Kargo Takip',
                'keywords' => ['kargo', 'takip', 'tracking', 'teslimat'],
                'match_type' => 'contains',
                'response_type' => 'dynamic',
                'response_content' => 'tracking_info',
                'response_message' => "🚚 Kargo bilgilerinizi sorguluyorum...\n\nTakip numaranız henüz tanımlanmadıysa en kısa sürede güncellenecektir.",
                'priority' => 2
            ],
            [
                'name' => 'Selamlama',
                'keywords' => ['merhaba', 'selam', 'günaydın', 'iyi günler', 'iyi akşamlar'],
                'match_type' => 'contains',
                'response_type' => 'text',
                'response_content' => "Merhaba {müşteri_adı}! 👋\n\n{mağaza_adı}'a hoş geldiniz.\n\nSize nasıl yardımcı olabilirim?\n\n📦 Sipariş durumu için: \"siparişim\"\n🚚 Kargo takibi için: \"kargom\"\n❓ Diğer sorular için: \"yardım\"",
                'response_message' => "Merhaba {müşteri_adı}! 👋\n\n{mağaza_adı}'a hoş geldiniz.\n\nSize nasıl yardımcı olabilirim?\n\n📦 Sipariş durumu için: \"siparişim\"\n🚚 Kargo takibi için: \"kargom\"\n❓ Diğer sorular için: \"yardım\"",
                'priority' => 3
            ],
            [
                'name' => 'Yardım',
                'keywords' => ['yardım', 'help', 'destek', 'iletişim'],
                'match_type' => 'contains',
                'response_type' => 'text',
                'response_content' => "Size yardımcı olmaktan mutluluk duyarız! 🤝\n\n📦 Sipariş sorgulamak için sipariş numaranızı yazın\n🚚 Kargo takibi için \"kargom\" yazın\n📞 Canlı destek için çalışma saatlerimizde bize ulaşın\n\n{mağaza_adı} ekibi olarak her zaman yanınızdayız!",
                'response_message' => "Size yardımcı olmaktan mutluluk duyarız! 🤝\n\n📦 Sipariş sorgulamak için sipariş numaranızı yazın\n🚚 Kargo takibi için \"kargom\" yazın\n📞 Canlı destek için çalışma saatlerimizde bize ulaşın\n\n{mağaza_adı} ekibi olarak her zaman yanınızdayız!",
                'priority' => 4
            ],
            [
                'name' => 'İade Talebi',
                'keywords' => ['iade', 'geri', 'değişim', 'iptal'],
                'match_type' => 'contains',
                'response_type' => 'text',
                'response_content' => "İade/değişim talebinizi aldık. 📝\n\nİade işlemleri için:\n1. Sipariş numaranızı belirtin\n2. İade sebebinizi yazın\n3. Ürün fotoğrafı gönderin\n\nEn kısa sürede size dönüş yapacağız.\n\nYardımcı olmak için buradayız! 🙏",
                'response_message' => "İade/değişim talebinizi aldık. 📝\n\nİade işlemleri için:\n1. Sipariş numaranızı belirtin\n2. İade sebebinizi yazın\n3. Ürün fotoğrafı gönderin\n\nEn kısa sürede size dönüş yapacağız.\n\nYardımcı olmak için buradayız! 🙏",
                'priority' => 5,
                'action_type' => 'notify_admin'
            ],
            [
                'name' => 'Teşekkür',
                'keywords' => ['teşekkür', 'sağol', 'eyvallah', 'thanks'],
                'match_type' => 'contains',
                'response_type' => 'text',
                'response_content' => "Rica ederim! 😊\n\nBaşka bir konuda yardıma ihtiyacınız olursa yazmanız yeterli.\n\nİyi günler dileriz! 🌟",
                'response_message' => "Rica ederim! 😊\n\nBaşka bir konuda yardıma ihtiyacınız olursa yazmanız yeterli.\n\nİyi günler dileriz! 🌟",
                'priority' => 10
            ]
        ];

        foreach ($defaults as $rule) {
            if (!$this->rule_exists_by_name($rule['name'])) {
                $this->save_rule($rule);
            } else {
                // Mevcut kural varsa ve response_content boşsa, varsayılan değerle güncelle
                $this->update_empty_response($rule['name'], $rule['response_content'] ?? $rule['response_message']);
            }
        }
    }

    /**
     * Boş response_content alanını güncelle
     */
    private function update_empty_response($name, $default_content) {
        global $wpdb;

        // Mevcut kuralı kontrol et
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, response_content FROM {$this->table_rules} WHERE name = %s",
            $name
        ), ARRAY_A);

        // Eğer response_content boşsa veya sadece tip bilgisi varsa güncelle
        if ($existing && (empty($existing['response_content']) || in_array($existing['response_content'], ['order_status', 'tracking_info', 'order_list']))) {
            $wpdb->update(
                $this->table_rules,
                ['response_content' => $default_content],
                ['id' => $existing['id']]
            );
        }
    }

    /**
     * Kural adıyla var mı kontrol et
     */
    private function rule_exists_by_name($name) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_rules} WHERE name = %s",
            $name
        ));
    }

    /**
     * Konuşmaları getir
     */
    public function get_conversations($args = []) {
        global $wpdb;

        $defaults = [
            'phone' => null,
            'status' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['phone']) {
            $where[] = 'phone LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['phone']) . '%';
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'created_at DESC';

        $sql = "SELECT * FROM {$this->table_conversations} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    /**
     * İstatistikler
     */
    public function get_stats() {
        global $wpdb;

        return [
            'total_rules' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_rules}"),
            'active_rules' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_rules} WHERE status = 'active'"),
            'total_conversations' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_conversations}"),
            'responded' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_conversations} WHERE status = 'responded'"),
            'no_match' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_conversations} WHERE status = 'no_match'"),
            'today' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_conversations} WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )),
            'most_used_rule' => $wpdb->get_row(
                "SELECT id, name, usage_count FROM {$this->table_rules} ORDER BY usage_count DESC LIMIT 1",
                ARRAY_A
            )
        ];
    }
}
