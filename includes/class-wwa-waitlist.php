<?php
/**
 * Waitlist (Stok Bildirimi) Sınıfı
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_Waitlist {

    /**
     * Tablo adı
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wwa_waitlist';

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
        $table_name = $wpdb->prefix . 'wwa_waitlist';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            customer_name varchar(255) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            notified tinyint(1) DEFAULT 0,
            notified_at datetime DEFAULT NULL,
            notification_method varchar(20) DEFAULT 'email',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY email (email),
            KEY notified (notified),
            UNIQUE KEY unique_subscription (product_id, variation_id, email)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        // Ürün sayfasında waitlist formu göster
        add_action('woocommerce_single_product_summary', [$this, 'display_waitlist_form'], 35);

        // Form submit handler
        add_action('wp_ajax_wwa_join_waitlist', [$this, 'ajax_join_waitlist']);
        add_action('wp_ajax_nopriv_wwa_join_waitlist', [$this, 'ajax_join_waitlist']);

        // Stok değişikliğinde bildirim gönder
        add_action('woocommerce_product_set_stock_status', [$this, 'check_and_notify'], 10, 3);
        add_action('woocommerce_variation_set_stock_status', [$this, 'check_and_notify'], 10, 3);

        // Admin ürün sayfasında waitlist bilgisi göster
        add_action('woocommerce_product_options_inventory_product_data', [$this, 'admin_waitlist_info']);

        // Frontend scriptler
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Frontend scriptler
     */
    public function enqueue_scripts() {
        if (!is_product()) {
            return;
        }

        wp_enqueue_style(
            'wwa-waitlist',
            WWA_PLUGIN_URL . 'assets/css/waitlist.css',
            [],
            WWA_VERSION
        );

        wp_enqueue_script(
            'wwa-waitlist',
            WWA_PLUGIN_URL . 'assets/js/waitlist.js',
            ['jquery'],
            WWA_VERSION,
            true
        );

        wp_localize_script('wwa-waitlist', 'wwaWaitlist', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wwa_waitlist'),
            'i18n' => [
                'joining' => __('Kaydediliyor...', 'woo-whatsapp'),
                'success' => __('Başarıyla listeye eklendiniz! Ürün stoğa girdiğinde size haber vereceğiz.', 'woo-whatsapp'),
                'error' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'woo-whatsapp'),
                'already_joined' => __('Bu ürün için zaten kayıtlısınız.', 'woo-whatsapp')
            ]
        ]);
    }

    /**
     * Waitlist formunu göster
     */
    public function display_waitlist_form() {
        global $product;

        if (!$product || $product->is_in_stock()) {
            return;
        }

        // Waitlist aktif mi kontrol et
        if (get_option('wwa_waitlist_enabled', 'yes') !== 'yes') {
            return;
        }

        $user = wp_get_current_user();
        $email = $user->ID ? $user->user_email : '';
        $phone = $user->ID ? get_user_meta($user->ID, 'billing_phone', true) : '';
        $name = $user->ID ? $user->display_name : '';

        // Zaten kayıtlı mı kontrol et
        $already_subscribed = false;
        if ($email) {
            $already_subscribed = $this->is_subscribed($product->get_id(), null, $email);
        }
        ?>
        <div class="wwa-waitlist-container" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
            <div class="wwa-waitlist-header">
                <span class="wwa-waitlist-icon">🔔</span>
                <h4><?php esc_html_e('Stoğa Girince Haber Ver', 'woo-whatsapp'); ?></h4>
            </div>

            <?php if ($already_subscribed): ?>
                <div class="wwa-waitlist-message wwa-waitlist-success">
                    <span>✓</span>
                    <?php esc_html_e('Bu ürün için bildirim almak üzere kayıtlısınız.', 'woo-whatsapp'); ?>
                </div>
            <?php else: ?>
                <p class="wwa-waitlist-description">
                    <?php esc_html_e('Bu ürün şu anda stokta yok. Bilgilerinizi bırakın, ürün stoğa girdiğinde size haber verelim.', 'woo-whatsapp'); ?>
                </p>

                <form class="wwa-waitlist-form">
                    <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">

                    <div class="wwa-waitlist-field">
                        <label for="wwa-waitlist-name"><?php esc_html_e('Adınız', 'woo-whatsapp'); ?></label>
                        <input type="text" id="wwa-waitlist-name" name="name" value="<?php echo esc_attr($name); ?>" required>
                    </div>

                    <div class="wwa-waitlist-field">
                        <label for="wwa-waitlist-email"><?php esc_html_e('E-posta', 'woo-whatsapp'); ?></label>
                        <input type="email" id="wwa-waitlist-email" name="email" value="<?php echo esc_attr($email); ?>" required>
                    </div>

                    <div class="wwa-waitlist-field">
                        <label for="wwa-waitlist-phone">
                            <?php esc_html_e('Telefon (WhatsApp)', 'woo-whatsapp'); ?>
                            <span class="wwa-optional"><?php esc_html_e('(Opsiyonel)', 'woo-whatsapp'); ?></span>
                        </label>
                        <input type="tel" id="wwa-waitlist-phone" name="phone" value="<?php echo esc_attr($phone); ?>" placeholder="+90 5XX XXX XXXX">
                    </div>

                    <div class="wwa-waitlist-options">
                        <label class="wwa-waitlist-checkbox">
                            <input type="checkbox" name="notify_email" checked>
                            <span><?php esc_html_e('E-posta ile bilgilendir', 'woo-whatsapp'); ?></span>
                        </label>
                        <label class="wwa-waitlist-checkbox">
                            <input type="checkbox" name="notify_whatsapp">
                            <span><?php esc_html_e('WhatsApp ile bilgilendir', 'woo-whatsapp'); ?></span>
                        </label>
                    </div>

                    <button type="submit" class="wwa-waitlist-button button">
                        <span class="wwa-button-text"><?php esc_html_e('Beni Haberdar Et', 'woo-whatsapp'); ?></span>
                        <span class="wwa-button-loading" style="display:none;">⏳</span>
                    </button>
                </form>

                <div class="wwa-waitlist-message" style="display:none;"></div>
            <?php endif; ?>

            <?php
            // Bekleyen kişi sayısını göster
            $count = $this->get_waitlist_count($product->get_id());
            if ($count > 0):
            ?>
                <p class="wwa-waitlist-count">
                    <?php printf(
                        _n(
                            '%d kişi bu ürünü bekliyor',
                            '%d kişi bu ürünü bekliyor',
                            $count,
                            'woo-whatsapp'
                        ),
                        $count
                    ); ?>
                </p>
            <?php endif; ?>
        </div>

        <style>
        .wwa-waitlist-container {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .wwa-waitlist-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .wwa-waitlist-header h4 {
            margin: 0;
            font-size: 16px;
        }
        .wwa-waitlist-icon {
            font-size: 24px;
        }
        .wwa-waitlist-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .wwa-waitlist-field {
            margin-bottom: 12px;
        }
        .wwa-waitlist-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
        }
        .wwa-optional {
            font-weight: normal;
            color: #999;
        }
        .wwa-waitlist-field input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .wwa-waitlist-options {
            margin: 15px 0;
        }
        .wwa-waitlist-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        .wwa-waitlist-button {
            width: 100%;
            padding: 12px !important;
            background: #25D366 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 4px !important;
            font-weight: 600 !important;
            cursor: pointer;
        }
        .wwa-waitlist-button:hover {
            background: #128C7E !important;
        }
        .wwa-waitlist-message {
            margin-top: 15px;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        .wwa-waitlist-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .wwa-waitlist-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .wwa-waitlist-count {
            margin-top: 15px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        </style>
        <?php
    }

    /**
     * AJAX: Waitlist'e katıl
     */
    public function ajax_join_waitlist() {
        check_ajax_referer('wwa_waitlist', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : null;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $notify_email = isset($_POST['notify_email']) && $_POST['notify_email'] === 'true';
        $notify_whatsapp = isset($_POST['notify_whatsapp']) && $_POST['notify_whatsapp'] === 'true';

        if (!$product_id || !$email) {
            wp_send_json_error(['message' => __('Gerekli alanları doldurun', 'woo-whatsapp')]);
        }

        // Zaten kayıtlı mı
        if ($this->is_subscribed($product_id, $variation_id, $email)) {
            wp_send_json_error(['message' => __('Bu ürün için zaten kayıtlısınız', 'woo-whatsapp'), 'code' => 'already_subscribed']);
        }

        // Bildirim yöntemi
        $notification_method = 'email';
        if ($notify_whatsapp && $phone) {
            $notification_method = $notify_email ? 'both' : 'whatsapp';
        }

        $result = $this->add_to_waitlist([
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'email' => $email,
            'phone' => $phone,
            'customer_name' => $name,
            'user_id' => get_current_user_id() ?: null,
            'notification_method' => $notification_method
        ]);

        if ($result) {
            wp_send_json_success(['message' => __('Başarıyla kaydoldunuz!', 'woo-whatsapp')]);
        } else {
            wp_send_json_error(['message' => __('Bir hata oluştu', 'woo-whatsapp')]);
        }
    }

    /**
     * Waitlist'e ekle
     */
    public function add_to_waitlist($data) {
        global $wpdb;

        return $wpdb->insert($this->table_name, [
            'product_id' => $data['product_id'],
            'variation_id' => $data['variation_id'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'notification_method' => $data['notification_method'] ?? 'email'
        ], ['%d', '%d', '%s', '%s', '%s', '%d', '%s']);
    }

    /**
     * Zaten kayıtlı mı kontrol et
     */
    public function is_subscribed($product_id, $variation_id, $email) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE product_id = %d AND email = %s AND notified = 0",
            $product_id,
            $email
        );

        if ($variation_id) {
            $query = $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE product_id = %d AND variation_id = %d AND email = %s AND notified = 0",
                $product_id,
                $variation_id,
                $email
            );
        }

        return (bool) $wpdb->get_var($query);
    }

    /**
     * Stok değişikliğinde kontrol et ve bildir
     */
    public function check_and_notify($product_id, $stock_status, $product) {
        if ($stock_status !== 'instock') {
            return;
        }

        $subscribers = $this->get_subscribers($product_id);

        if (empty($subscribers)) {
            return;
        }

        foreach ($subscribers as $subscriber) {
            $this->send_notification($subscriber, $product);
        }
    }

    /**
     * Bildirim gönder
     */
    private function send_notification($subscriber, $product) {
        global $wpdb;

        $method = $subscriber['notification_method'];

        // E-posta bildirimi
        if (in_array($method, ['email', 'both'])) {
            $this->send_email_notification($subscriber, $product);
        }

        // WhatsApp bildirimi
        if (in_array($method, ['whatsapp', 'both']) && !empty($subscriber['phone'])) {
            $this->send_whatsapp_notification($subscriber, $product);
        }

        // Bildirildi olarak işaretle
        $wpdb->update(
            $this->table_name,
            [
                'notified' => 1,
                'notified_at' => current_time('mysql')
            ],
            ['id' => $subscriber['id']],
            ['%d', '%s'],
            ['%d']
        );
    }

    /**
     * E-posta bildirimi gönder
     */
    private function send_email_notification($subscriber, $product) {
        $to = $subscriber['email'];
        $subject = sprintf(
            __('%s Tekrar Stokta!', 'woo-whatsapp'),
            $product->get_name()
        );

        $message = sprintf(
            __("Merhaba %s,\n\nBeklediğiniz ürün tekrar stokta!\n\n%s\n\nHemen satın almak için: %s\n\nTeşekkürler,\n%s", 'woo-whatsapp'),
            $subscriber['customer_name'] ?: __('Değerli Müşterimiz', 'woo-whatsapp'),
            $product->get_name(),
            $product->get_permalink(),
            get_bloginfo('name')
        );

        wp_mail($to, $subject, $message);
    }

    /**
     * WhatsApp bildirimi gönder
     */
    private function send_whatsapp_notification($subscriber, $product) {
        $template = get_option('wwa_waitlist_template',
            "Merhaba {müşteri_adı}!\n\n🎉 Beklediğiniz ürün tekrar stokta!\n\n📦 {ürün_adı}\n💰 {ürün_fiyat}\n\n🛒 Hemen satın al: {ürün_url}"
        );

        $message = str_replace(
            ['{müşteri_adı}', '{ürün_adı}', '{ürün_fiyat}', '{ürün_url}'],
            [
                $subscriber['customer_name'] ?: __('Değerli Müşterimiz', 'woo-whatsapp'),
                $product->get_name(),
                wc_price($product->get_price()),
                $product->get_permalink()
            ],
            $template
        );

        WWA()->api->send_message($subscriber['phone'], $message);
    }

    /**
     * Ürün için aboneleri getir
     */
    public function get_subscribers($product_id, $variation_id = null) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE product_id = %d AND notified = 0",
            $product_id
        );

        if ($variation_id) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE product_id = %d AND variation_id = %d AND notified = 0",
                $product_id,
                $variation_id
            );
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Waitlist sayısını getir
     */
    public function get_waitlist_count($product_id, $variation_id = null) {
        global $wpdb;

        if ($variation_id) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE product_id = %d AND variation_id = %d AND notified = 0",
                $product_id,
                $variation_id
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE product_id = %d AND notified = 0",
            $product_id
        ));
    }

    /**
     * Admin ürün sayfasında waitlist bilgisi
     */
    public function admin_waitlist_info() {
        global $post;

        if (!$post) {
            return;
        }

        $count = $this->get_waitlist_count($post->ID);
        ?>
        <div class="options_group">
            <p class="form-field">
                <label><?php esc_html_e('Waitlist', 'woo-whatsapp'); ?></label>
                <span class="description">
                    <?php printf(
                        _n(
                            '%d kişi bu ürünü bekliyor',
                            '%d kişi bu ürünü bekliyor',
                            $count,
                            'woo-whatsapp'
                        ),
                        $count
                    ); ?>
                    <?php if ($count > 0): ?>
                        <a href="<?php echo admin_url('admin.php?page=woo-whatsapp#/waitlist?product=' . $post->ID); ?>">
                            <?php esc_html_e('Listeyi Gör', 'woo-whatsapp'); ?>
                        </a>
                    <?php endif; ?>
                </span>
            </p>
        </div>
        <?php
    }

    /**
     * Tüm waitlist kayıtlarını getir (admin için)
     */
    public function get_all_waitlist($args = []) {
        global $wpdb;

        $defaults = [
            'product_id' => null,
            'notified' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['product_id']) {
            $where[] = 'w.product_id = %d';
            $values[] = $args['product_id'];
        }

        if ($args['notified'] !== null) {
            $where[] = 'w.notified = %d';
            $values[] = $args['notified'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby('w.' . $args['orderby'] . ' ' . $args['order']) ?: 'w.created_at DESC';

        $sql = "SELECT w.*, p.post_title as product_name
                FROM {$this->table_name} w
                LEFT JOIN {$wpdb->posts} p ON w.product_id = p.ID
                WHERE {$where_clause}
                ORDER BY {$orderby}
                LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    /**
     * Waitlist kayıtlarını getir (REST API için)
     */
    public function get_entries($args = []) {
        $defaults = [
            'product_id' => null,
            'status' => null,
            'per_page' => 20,
            'page' => 1
        ];

        $args = wp_parse_args($args, $defaults);

        // per_page ve page'i limit/offset'e çevir
        $limit = absint($args['per_page']);
        $offset = (absint($args['page']) - 1) * $limit;

        // status'u notified'a çevir
        $notified = null;
        if ($args['status'] === 'waiting') {
            $notified = 0;
        } elseif ($args['status'] === 'notified') {
            $notified = 1;
        }

        return $this->get_all_waitlist([
            'product_id' => $args['product_id'],
            'notified' => $notified,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Waitlist kayıt sayısını getir
     */
    public function get_entries_count($args = []) {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (!empty($args['product_id'])) {
            $where[] = 'product_id = %d';
            $values[] = $args['product_id'];
        }

        if (!empty($args['status'])) {
            if ($args['status'] === 'waiting') {
                $where[] = 'notified = 0';
            } elseif ($args['status'] === 'notified') {
                $where[] = 'notified = 1';
            }
        }

        $where_clause = implode(' AND ', $where);

        if (empty($values)) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}");
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}",
            $values
        ));
    }

    /**
     * İstatistikler
     */
    public function get_stats() {
        global $wpdb;

        return [
            'total_subscribers' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name}"
            ),
            'pending_notifications' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE notified = 0"
            ),
            'notifications_sent' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE notified = 1"
            ),
            'unique_products' => (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT product_id) FROM {$this->table_name} WHERE notified = 0"
            ),
            'today_signups' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            ))
        ];
    }

    /**
     * Kaydı sil
     */
    public function delete_subscription($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, ['id' => $id], ['%d']);
    }
}
