<?php
/**
 * Abandoned Cart (Terk Edilmiş Sepet) Sınıfı
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_Abandoned_Cart {

    /**
     * Tablo adı
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wwa_abandoned_carts';

        // Tablo yoksa oluştur
        $this->maybe_create_table();

        $this->init_hooks();
    }

    /**
     * Tablo yoksa oluştur
     */
    private function maybe_create_table() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") !== $this->table_name) {
            self::create_table();
        }
    }

    /**
     * Tabloyu oluştur
     */
    public static function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'wwa_abandoned_carts';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            customer_name varchar(255) DEFAULT NULL,
            cart_contents longtext,
            cart_total decimal(10,2) DEFAULT 0,
            currency varchar(10) DEFAULT 'TRY',
            status varchar(20) DEFAULT 'active',
            recovery_sent tinyint(1) DEFAULT 0,
            recovery_count int(11) DEFAULT 0,
            recovered tinyint(1) DEFAULT 0,
            recovered_order_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            abandoned_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY email (email),
            KEY status (status),
            KEY abandoned_at (abandoned_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);

        add_action('woocommerce_add_to_cart', [$this, 'track_cart'], 10);
        add_action('woocommerce_cart_item_removed', [$this, 'track_cart'], 10);
        add_action('woocommerce_cart_item_restored', [$this, 'track_cart'], 10);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'track_cart'], 10);
        add_action('woocommerce_cart_emptied', [$this, 'clear_cart_tracking'], 10);

        add_action('woocommerce_checkout_update_order_review', [$this, 'capture_checkout_data']);

        add_action('wp_ajax_wwa_capture_guest_data', [$this, 'ajax_capture_guest_data']);
        add_action('wp_ajax_nopriv_wwa_capture_guest_data', [$this, 'ajax_capture_guest_data']);

        add_action('woocommerce_thankyou', [$this, 'mark_cart_recovered'], 10);
        add_action('woocommerce_payment_complete', [$this, 'mark_cart_recovered'], 10);

        add_action('init', [$this, 'handle_cart_recovery']);

        add_action('wwa_mark_abandoned_carts', [$this, 'mark_abandoned_carts']);
        if (!wp_next_scheduled('wwa_mark_abandoned_carts')) {
            wp_schedule_event(time(), 'every_five_minutes', 'wwa_mark_abandoned_carts');
        }

        // Otomatik hatırlatma gönderme cron'u
        add_action('wwa_send_abandoned_cart_reminders', [$this, 'send_automatic_reminders']);
        if (!wp_next_scheduled('wwa_send_abandoned_cart_reminders')) {
            wp_schedule_event(time(), 'every_five_minutes', 'wwa_send_abandoned_cart_reminders');
        }

        // Frontend script
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Cron interval'larını ekle
     */
    public function add_cron_intervals($schedules) {
        if (!isset($schedules['every_five_minutes'])) {
            $schedules['every_five_minutes'] = [
                'interval' => 300, // 5 dakika
                'display' => __('Her 5 dakikada bir', 'woo-whatsapp')
            ];
        }
        return $schedules;
    }

    /**
     * Frontend script yükle
     */
    public function enqueue_scripts() {
        if (!is_checkout() && !is_cart()) {
            return;
        }

        wp_enqueue_script(
            'wwa-cart-tracking',
            WWA_PLUGIN_URL . 'assets/js/cart-tracking.js',
            ['jquery'],
            WWA_VERSION,
            true
        );

        wp_localize_script('wwa-cart-tracking', 'wwaCartTracking', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wwa_cart_tracking')
        ]);
    }

    /**
     * Sepeti takip et
     */
    public function track_cart() {
        if (is_admin() || !WC()->cart) {
            return;
        }

        $cart = WC()->cart;

        if ($cart->is_empty()) {
            $this->clear_cart_tracking();
            return;
        }

        $session_id = $this->get_session_id();
        $user_id = get_current_user_id();

        // Sepet içeriğini hazırla
        $cart_contents = [];
        foreach ($cart->get_cart() as $item_key => $item) {
            $product = $item['data'];
            $cart_contents[] = [
                'product_id' => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? 0,
                'name' => $product->get_name(),
                'quantity' => $item['quantity'],
                'price' => $product->get_price(),
                'total' => $item['line_total'],
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')
            ];
        }

        $data = [
            'session_id' => $session_id,
            'user_id' => $user_id ?: null,
            'cart_contents' => wp_json_encode($cart_contents),
            'cart_total' => $cart->get_cart_contents_total(),
            'currency' => get_woocommerce_currency(),
            'status' => 'active',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''
        ];

        // Kullanıcı bilgileri
        if ($user_id) {
            $user = get_userdata($user_id);
            $data['email'] = $user->user_email;
            $data['customer_name'] = $user->display_name;
            $data['phone'] = get_user_meta($user_id, 'billing_phone', true);
        }

        $this->save_cart($data);
    }

    /**
     * Checkout verilerini yakala
     */
    public function capture_checkout_data($posted_data) {
        parse_str($posted_data, $data);

        $session_id = $this->get_session_id();

        $update_data = [];

        if (!empty($data['billing_email'])) {
            $update_data['email'] = sanitize_email($data['billing_email']);
        }

        if (!empty($data['billing_phone'])) {
            $update_data['phone'] = sanitize_text_field($data['billing_phone']);
        }

        if (!empty($data['billing_first_name'])) {
            $name = sanitize_text_field($data['billing_first_name']);
            if (!empty($data['billing_last_name'])) {
                $name .= ' ' . sanitize_text_field($data['billing_last_name']);
            }
            $update_data['customer_name'] = $name;
        }

        if (!empty($update_data)) {
            $this->update_cart($session_id, $update_data);
        }
    }

    /**
     * AJAX ile misafir verisi yakala
     */
    public function ajax_capture_guest_data() {
        check_ajax_referer('wwa_cart_tracking', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

        if (empty($email) && empty($phone)) {
            wp_send_json_error('Veri eksik');
        }

        $session_id = $this->get_session_id();

        $update_data = [];
        if ($email) $update_data['email'] = $email;
        if ($phone) $update_data['phone'] = $phone;
        if ($name) $update_data['customer_name'] = $name;

        $this->update_cart($session_id, $update_data);

        wp_send_json_success();
    }

    /**
     * Sepeti kaydet
     */
    private function save_cart($data) {
        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE session_id = %s",
            $data['session_id']
        ));

        if ($existing) {
            $wpdb->update(
                $this->table_name,
                $data,
                ['session_id' => $data['session_id']]
            );
        } else {
            $wpdb->insert($this->table_name, $data);
        }
    }

    /**
     * Sepeti güncelle
     */
    private function update_cart($session_id, $data) {
        global $wpdb;

        $wpdb->update(
            $this->table_name,
            $data,
            ['session_id' => $session_id]
        );
    }

    /**
     * Sepet takibini temizle
     */
    public function clear_cart_tracking() {
        global $wpdb;

        $session_id = $this->get_session_id();

        $wpdb->update(
            $this->table_name,
            ['status' => 'cleared'],
            ['session_id' => $session_id]
        );
    }

    /**
     * Sepeti kurtarıldı olarak işaretle
     */
    public function mark_cart_recovered($order_id) {
        global $wpdb;

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $email = $order->get_billing_email();
        $user_id = $order->get_customer_id();

        $where = [];
        $values = [];

        if ($email) {
            $where[] = 'email = %s';
            $values[] = $email;
        }

        if ($user_id) {
            $where[] = 'user_id = %d';
            $values[] = $user_id;
        }

        if (empty($where)) {
            return;
        }

        $where_clause = implode(' OR ', $where);

        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE ({$where_clause}) AND status IN ('active', 'abandoned') ORDER BY updated_at DESC LIMIT 1",
            ...$values
        ));

        if ($cart) {
            $wpdb->update(
                $this->table_name,
                [
                    'status' => 'recovered',
                    'recovered' => 1,
                    'recovered_order_id' => $order_id
                ],
                ['id' => $cart->id]
            );
        }
    }

    /**
     * Sepet kurtarma işlemi
     */
    public function handle_cart_recovery() {
        if (!isset($_GET['wwa_recover']) || !isset($_GET['token'])) {
            return;
        }

        $cart_id = absint($_GET['wwa_recover']);
        $token = sanitize_text_field($_GET['token']);

        if ($token !== wp_hash($cart_id . AUTH_KEY)) {
            return;
        }

        $cart = $this->get_cart($cart_id);

        if (!$cart || $cart['status'] === 'recovered') {
            return;
        }

        WC()->cart->empty_cart();

        $contents = json_decode($cart['cart_contents'], true);

        if (is_array($contents)) {
            foreach ($contents as $item) {
                WC()->cart->add_to_cart(
                    $item['product_id'],
                    $item['quantity'],
                    $item['variation_id'] ?? 0
                );
            }
        }

        if (!empty($cart['email'])) {
            WC()->session->set('billing_email', $cart['email']);
        }
        if (!empty($cart['phone'])) {
            WC()->session->set('billing_phone', $cart['phone']);
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET recovery_count = recovery_count + 1 WHERE id = %d",
            $cart_id
        ));

        wp_redirect(wc_get_checkout_url());
        exit;
    }

    /**
     * Terk edilmiş sepetleri işaretle
     */
    public function mark_abandoned_carts() {
        global $wpdb;

        $threshold = date('Y-m-d H:i:s', strtotime('-15 minutes'));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name}
             SET status = 'abandoned', abandoned_at = %s
             WHERE status = 'active'
             AND updated_at < %s
             AND (email IS NOT NULL OR phone IS NOT NULL)",
            current_time('mysql'),
            $threshold
        ));
    }

    /**
     * Sepet getir
     */
    public function get_cart($cart_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $cart_id
        ), ARRAY_A);
    }

    /**
     * Terk edilmiş sepetleri getir
     */
    public function get_abandoned_carts($args = []) {
        global $wpdb;

        $defaults = [
            'status' => 'abandoned',
            'recovery_sent' => null,
            'min_total' => 0.01,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'abandoned_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['recovery_sent'] !== null) {
            $where[] = 'recovery_sent = %d';
            $values[] = $args['recovery_sent'];
        }

        if ($args['min_total'] > 0) {
            $where[] = 'cart_total >= %f';
            $values[] = $args['min_total'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'abandoned_at DESC';

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    /**
     * Sepetleri getir (get_abandoned_carts alias - REST API için)
     */
    public function get_carts($args = []) {
        $defaults = [
            'status' => 'abandoned',
            'per_page' => 20,
            'page' => 1
        ];

        $args = wp_parse_args($args, $defaults);

        $limit = absint($args['per_page']);
        $offset = (absint($args['page']) - 1) * $limit;

        $status = !empty($args['status']) ? $args['status'] : 'abandoned';

        return $this->get_abandoned_carts([
            'status' => $status,
            'limit' => $limit,
            'offset' => $offset,
            'min_total' => 0.01 
        ]);
    }

    /**
     * Sepet sayısını getir
     */
    public function get_carts_count($args = []) {
        global $wpdb;

        $where = ['1=1', 'cart_total > 0']; 
        $values = [];

        $status = !empty($args['status']) ? $args['status'] : 'abandoned';
        $where[] = 'status = %s';
        $values[] = $status;

        $where_clause = implode(' AND ', $where);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}",
            $values
        ));
    }

    /**
     * İstatistikler
     * Not: cart_total > 0 filtresi eklendi - boş sepetleri hariç tutar
     */
    public function get_stats() {
        global $wpdb;

        return [
            'total_abandoned' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'abandoned' AND cart_total > 0"
            ),
            'total_recovered' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'recovered' AND cart_total > 0"
            ),
            'total_value_abandoned' => (float) $wpdb->get_var(
                "SELECT SUM(cart_total) FROM {$this->table_name} WHERE status = 'abandoned' AND cart_total > 0"
            ),
            'total_value_recovered' => (float) $wpdb->get_var(
                "SELECT SUM(cart_total) FROM {$this->table_name} WHERE status = 'recovered' AND cart_total > 0"
            ),
            'recovery_rate' => 0, // Hesaplanacak
            'pending_recovery' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'abandoned' AND recovery_sent = 0 AND cart_total > 0"
            ),
            'today_abandoned' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'abandoned' AND DATE(abandoned_at) = %s AND cart_total > 0",
                current_time('Y-m-d')
            )),
            'today_recovered' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'recovered' AND DATE(updated_at) = %s AND cart_total > 0",
                current_time('Y-m-d')
            ))
        ];
    }

    /**
     * Session ID getir
     */
    private function get_session_id() {
        if (WC()->session) {
            return WC()->session->get_customer_id();
        }

        if (!isset($_COOKIE['wwa_session_id'])) {
            $session_id = wp_generate_uuid4();
            setcookie('wwa_session_id', $session_id, time() + DAY_IN_SECONDS * 30, COOKIEPATH, COOKIE_DOMAIN);
            return $session_id;
        }

        return sanitize_text_field($_COOKIE['wwa_session_id']);
    }

    /**
     * Client IP getir
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Kurtarma URL'i oluştur
     */
    public function get_recovery_url($cart_id) {
        $token = wp_hash($cart_id . AUTH_KEY);
        return add_query_arg([
            'wwa_recover' => $cart_id,
            'token' => $token
        ], wc_get_cart_url());
    }

    /**
     * Sepeti sil
     */
    public function delete_cart($cart_id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, ['id' => $cart_id], ['%d']);
    }

    /**
     * Eski kayıtları temizle
     */
    public function cleanup_old_carts($days = 90) {
        global $wpdb;

        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s AND status != 'recovered'",
            $threshold
        ));
    }

    /**
     * Manuel hatırlatma gönder
     *
     * @param int $cart_id Sepet ID
     * @return array
     */
    public function send_reminder($cart_id) {
        $cart = $this->get_cart($cart_id);

        if (!$cart) {
            return ['success' => false, 'error' => __('Sepet bulunamadı', 'woo-whatsapp')];
        }

        if (empty($cart['phone'])) {
            return ['success' => false, 'error' => __('Telefon numarası bulunamadı', 'woo-whatsapp')];
        }

        if (floatval($cart['cart_total']) <= 0) {
            return ['success' => false, 'error' => __('Sepet tutarı 0, hatırlatma gönderilemez', 'woo-whatsapp')];
        }

        $recovery_url = $this->get_recovery_url($cart_id);

        $cart_contents = json_decode($cart['cart_contents'], true);
        $items_text = '';
        if (is_array($cart_contents)) {
            foreach ($cart_contents as $item) {
                $items_text .= "• {$item['name']} x {$item['quantity']}\n";
            }
        }

        $template = get_option('wwa_abandoned_cart_template', '');
        if (empty($template)) {
            $template = "Merhaba {müşteri_adı}! 🛒\n\nSepetinizde ürünler bekliyorsunuz:\n\n{ürün_listesi}\n\nToplam: {sepet_tutarı}\n\n🔗 Siparişinizi tamamlayın: {kurtarma_linki}\n\nYardımcı olabileceğimiz bir konu varsa yazabilirsiniz!";
        }

        $message = str_replace(
            [
                '{müşteri_adı}',
                '{müşteri}',
                '{ürün_listesi}',
                '{sepet_tutarı}',
                '{kurtarma_linki}',
                '{mağaza_adı}'
            ],
            [
                $cart['customer_name'] ?: __('Değerli Müşterimiz', 'woo-whatsapp'),
                $cart['customer_name'] ?: __('Değerli Müşterimiz', 'woo-whatsapp'),
                $items_text,
                $this->format_price_plain($cart['cart_total'], $cart['currency']),
                $recovery_url,
                get_bloginfo('name')
            ],
            $template
        );

        $phone = preg_replace('/[^0-9]/', '', $cart['phone']);

        $result = WWA()->api->send_message($phone, $message);

        if ($result['success']) {
            global $wpdb;
            $wpdb->update(
                $this->table_name,
                [
                    'recovery_sent' => 1,
                    'recovery_count' => $cart['recovery_count'] + 1
                ],
                ['id' => $cart_id]
            );

            // Log kaydet
            if (class_exists('WWA_Logger')) {
                WWA_Logger::log([
                    'type' => 'abandoned_cart_reminder',
                    'phone' => $phone,
                    'message' => $message,
                    'status' => 'sent',
                    'cart_id' => $cart_id
                ]);
            }

            return ['success' => true];
        }

        return ['success' => false, 'error' => $result['error'] ?? __('Mesaj gönderilemedi', 'woo-whatsapp')];
    }

    /**
     * Fiyatı düz metin olarak formatla
     *
     * @param float $price Fiyat
     * @param string $currency Para birimi
     * @return string
     */
    private function format_price_plain($price, $currency = null) {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }

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


    public function send_automatic_reminders() {
        if (!WWA()->settings->is_pro_active()) {
            return;
        }

        $auto_enabled = get_option('wwa_abandoned_cart_auto_reminder', 'no');
        if ($auto_enabled !== 'yes') {
            return;
        }

        $delay_minutes = (int) get_option('wwa_abandoned_cart_delay', 30);
        if ($delay_minutes < 5) {
            $delay_minutes = 30;
        }
        $max_reminders = (int) get_option('wwa_abandoned_cart_max_reminders', 1);
        if ($max_reminders < 1) {
            $max_reminders = 1;
        }

        global $wpdb;


        $threshold = date('Y-m-d H:i:s', strtotime("-{$delay_minutes} minutes"));

        $carts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE status = 'abandoned'
             AND phone IS NOT NULL
             AND phone != ''
             AND cart_total > 0
             AND recovery_count < %d
             AND abandoned_at IS NOT NULL
             AND abandoned_at < %s
             ORDER BY abandoned_at ASC
             LIMIT 10",
            $max_reminders,
            $threshold
        ), ARRAY_A);

        if (empty($carts)) {
            return;
        }

        foreach ($carts as $cart) {
            $this->send_reminder($cart['id']);
            sleep(1);
        }
    }
}
