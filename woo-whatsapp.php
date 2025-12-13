<?php
/**
 * Plugin Name: Woo WhatsApp Bildirimcisi
 * Plugin URI: https://yourwebsite.com/woo-whatsapp
 * Description: WooCommerce sipariş bildirimlerini WhatsApp üzerinden gönderen profesyonel eklenti.
 * Version: 3.0.2
 * Author: Burak
 * Author URI: https://yourwebsite.com
 * Text Domain: woo-whatsapp
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WWA_VERSION', '3.0.2');
define('WWA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WWA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WWA_PLUGIN_BASENAME', plugin_basename(__FILE__));

if (!defined('WWA_ENVATO_TOKEN')) {
}
if (!defined('WWA_ENVATO_ITEM_ID')) {
}

/**
 * Ana Plugin Sınıfı
 */
final class Woo_WhatsApp {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Plugin bileşenleri
     */
    public $settings;
    public $api;
    public $woocommerce;
    public $logger;
    public $flow_builder;
    public $abandoned_cart;
    public $waitlist;
    public $chatbot;
    public $i18n;

    /**
     * Singleton pattern
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Gerekli dosyaları dahil et
     */
    private function includes() {
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-logger.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-api-handler.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-settings.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-woocommerce.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-rest-api.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-template-parser.php';

        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-flow-builder.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-abandoned-cart.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-waitlist.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-chatbot.php';
        require_once WWA_PLUGIN_DIR . 'includes/class-wwa-i18n.php';

        if (is_admin()) {
            require_once WWA_PLUGIN_DIR . 'admin/class-wwa-admin.php';
        }
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init'], 0);
        add_action('init', [$this, 'load_textdomain']);

        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
    }

    /**
     * Plugin başlatma
     */
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        $this->maybe_upgrade();

        $this->logger = new WWA_Logger();
        $this->settings = new WWA_Settings();
        $this->api = new WWA_API_Handler();
        $this->woocommerce = new WWA_WooCommerce();

        $this->flow_builder = new WWA_Flow_Builder();
        $this->abandoned_cart = new WWA_Abandoned_Cart();
        $this->waitlist = new WWA_Waitlist();
        $this->chatbot = new WWA_Chatbot();
        $this->i18n = new WWA_I18n();

        new WWA_REST_API();

        if (is_admin()) {
            new WWA_Admin();
        }

        // Aktivasyon hook'u çalıştır (chatbot varsayılan kuralları için)
        do_action('wwa_activated');
    }

    /**
     * Versiyon kontrolü ve güncelleme
     */
    private function maybe_upgrade() {
        $installed_version = get_option('wwa_version', '0');

        // Versiyon değişmişse veya tablolar eksikse
        if (version_compare($installed_version, WWA_VERSION, '<') || $this->tables_missing()) {
            // Eski transient'leri temizle
            delete_transient('wwa_tables_checked_' . $installed_version);
            delete_transient('wwa_tables_checked_' . WWA_VERSION);

            $this->create_tables();
            update_option('wwa_version', WWA_VERSION);
        }
    }

    /**
     * Tabloların eksik olup olmadığını kontrol et
     */
    private function tables_missing() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'wwa_message_logs',
            $wpdb->prefix . 'wwa_flows',
            $wpdb->prefix . 'wwa_flow_logs',
            $wpdb->prefix . 'wwa_abandoned_carts',
            $wpdb->prefix . 'wwa_waitlist',
            $wpdb->prefix . 'wwa_chatbot_rules',
            $wpdb->prefix . 'wwa_chatbot_conversations'
        ];

        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dil dosyalarını yükle
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'woo-whatsapp',
            false,
            dirname(WWA_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Aktivasyon
     */
    public function activate() {
        // Veritabanı tablosu oluştur
        $this->create_tables();

        // Varsayılan ayarları kaydet
        $this->set_default_options();

        // Versiyon kaydet
        update_option('wwa_version', WWA_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Deaktivasyon
     */
    public function deactivate() {
        // Zamanlanmış görevleri temizle
        wp_clear_scheduled_hook('wwa_cleanup_logs');

        flush_rewrite_rules();
    }

    /**
     * Veritabanı tabloları
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Message logs tablosu
        $table_name = $wpdb->prefix . 'wwa_message_logs';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) DEFAULT NULL,
            phone varchar(20) NOT NULL,
            message text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            status_type varchar(50) DEFAULT NULL,
            api_response text DEFAULT NULL,
            message_id varchar(100) DEFAULT NULL,
            recipient_type varchar(20) DEFAULT 'admin',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Pro özellik tabloları
        WWA_Flow_Builder::create_tables();
        WWA_Abandoned_Cart::create_table();
        WWA_Waitlist::create_table();
        WWA_Chatbot::create_tables();

        // Scheduled flows tablosu
        $table_scheduled = $wpdb->prefix . 'wwa_scheduled_flows';
        $sql_scheduled = "CREATE TABLE IF NOT EXISTS $table_scheduled (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            flow_id bigint(20) DEFAULT NULL,
            context longtext NOT NULL,
            scheduled_at datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            executed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scheduled_at (scheduled_at),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_scheduled);
    }

    /**
     * Varsayılan ayarlar
     */
    private function set_default_options() {
        $defaults = [
            'wwa_enabled' => 'yes',
            'wwa_api_provider' => 'whatsapp_business',
            'wwa_admin_phone' => '',
            'wwa_api_token' => '',
            'wwa_phone_number_id' => '',
            'wwa_send_to_customer' => 'yes',
            'wwa_send_to_admin' => 'yes',
            'wwa_order_statuses' => ['processing', 'completed', 'cancelled', 'refunded'],
            'wwa_templates' => [
                'processing' => "Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz alındı ve işleme alındı.\n\nToplam: {toplam_tutar}\n\nTeşekkürler!",
                'completed' => "Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz tamamlandı!\n\nBizi tercih ettiğiniz için teşekkürler.",
                'on-hold' => "Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz ödeme bekleniyor.\n\nLütfen ödemenizi tamamlayın.",
                'cancelled' => "Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz iptal edildi.",
                'refunded' => "Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişinizin iadesi yapıldı.\n\nİade Tutarı: {toplam_tutar}",
                'shipped' => "Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz kargoya verildi!\n\nKargo Takip: {kargo_takip}",
                'admin_new_order' => "🛒 Yeni Sipariş!\n\nSipariş No: #{sipariş_no}\nMüşteri: {müşteri_adı}\nTelefon: {müşteri_telefon}\nTutar: {toplam_tutar}\n\nÜrünler:\n{ürün_listesi}"
            ],
            'wwa_debug_mode' => 'no',
            'wwa_log_retention_days' => 30
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    /**
     * WooCommerce eksik uyarısı
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Woo WhatsApp Bildirimcisi', 'woo-whatsapp'); ?></strong>
                <?php esc_html_e('eklentisi çalışmak için WooCommerce gerektirir. Lütfen WooCommerce\'i yükleyin ve etkinleştirin.', 'woo-whatsapp'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * HPOS uyumluluk bildirimi
     */
    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
}

/**
 * Ana fonksiyon
 */
function WWA() {
    return Woo_WhatsApp::instance();
}

// Plugin'i başlat
WWA();
