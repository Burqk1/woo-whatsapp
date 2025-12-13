<?php
/**
 * REST API Sınıfı
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'wwa/v1';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);

        // Tabloların mevcut olduğundan emin ol
        $this->ensure_tables_exist();
    }

    /**
     * Tabloların mevcut olduğundan emin ol
     */
    private function ensure_tables_exist() {
        global $wpdb;

        // Önce transient kontrolü yap (performans için)
        $check_key = 'wwa_tables_checked_' . WWA_VERSION;
        if (get_transient($check_key)) {
            return;
        }

        // Temel tablolar var mı kontrol et
        $tables_to_check = [
            $wpdb->prefix . 'wwa_flows',
            $wpdb->prefix . 'wwa_abandoned_carts',
            $wpdb->prefix . 'wwa_waitlist'
        ];

        $tables_missing = false;
        foreach ($tables_to_check as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                $tables_missing = true;
                break;
            }
        }

        if ($tables_missing) {
            // Tabloları oluştur
            $this->create_all_tables();
        }

        // 1 saat boyunca tekrar kontrol etme
        set_transient($check_key, true, HOUR_IN_SECONDS);
    }

    /**
     * Tüm tabloları oluştur
     */
    private function create_all_tables() {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

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
        dbDelta($sql);

        // Flows tablosu
        $table_flows = $wpdb->prefix . 'wwa_flows';
        $sql_flows = "CREATE TABLE IF NOT EXISTS $table_flows (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            trigger_type varchar(50) NOT NULL,
            trigger_config longtext DEFAULT NULL,
            nodes longtext DEFAULT NULL,
            edges longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            priority int(11) DEFAULT 10,
            execution_count int(11) DEFAULT 0,
            last_executed datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trigger_type (trigger_type),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_flows);

        // Flow logs tablosu
        $table_flow_logs = $wpdb->prefix . 'wwa_flow_logs';
        $sql_flow_logs = "CREATE TABLE IF NOT EXISTS $table_flow_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            flow_id bigint(20) NOT NULL,
            trigger_data longtext DEFAULT NULL,
            execution_path longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'completed',
            error_message text DEFAULT NULL,
            execution_time float DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY flow_id (flow_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_flow_logs);

        // Abandoned carts tablosu
        $table_abandoned = $wpdb->prefix . 'wwa_abandoned_carts';
        $sql_abandoned = "CREATE TABLE IF NOT EXISTS $table_abandoned (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(20) DEFAULT NULL,
            cart_contents longtext NOT NULL,
            cart_total decimal(10,2) DEFAULT 0,
            status varchar(20) DEFAULT 'abandoned',
            reminder_sent int(11) DEFAULT 0,
            last_reminder datetime DEFAULT NULL,
            recovered_order_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_abandoned);

        // Waitlist tablosu
        $table_waitlist = $wpdb->prefix . 'wwa_waitlist';
        $sql_waitlist = "CREATE TABLE IF NOT EXISTS $table_waitlist (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(20) DEFAULT NULL,
            status varchar(20) DEFAULT 'waiting',
            notified_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_waitlist);

        // Chatbot rules tablosu
        $table_chatbot = $wpdb->prefix . 'wwa_chatbot_rules';
        $sql_chatbot = "CREATE TABLE IF NOT EXISTS $table_chatbot (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            keywords text NOT NULL,
            match_type varchar(20) DEFAULT 'contains',
            response text NOT NULL,
            response_type varchar(20) DEFAULT 'text',
            priority int(11) DEFAULT 10,
            status varchar(20) DEFAULT 'active',
            usage_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY priority (priority)
        ) $charset_collate;";
        dbDelta($sql_chatbot);

        // Chatbot conversations tablosu
        $table_conversations = $wpdb->prefix . 'wwa_chatbot_conversations';
        $sql_conversations = "CREATE TABLE IF NOT EXISTS $table_conversations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            message text NOT NULL,
            direction varchar(10) DEFAULT 'incoming',
            rule_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_conversations);

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

        // Versiyon güncelle
        update_option('wwa_version', WWA_VERSION);
    }

    /**
     * Route'ları kaydet
     */
    public function register_routes() {
        // Ayarlar
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        // Şablonlar
        register_rest_route(self::NAMESPACE, '/templates', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_templates'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_templates'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        // Şablon önizleme
        register_rest_route(self::NAMESPACE, '/templates/preview', [
            'methods' => 'POST',
            'callback' => [$this, 'preview_template'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Test mesajı gönder
        register_rest_route(self::NAMESPACE, '/test', [
            'methods' => 'POST',
            'callback' => [$this, 'send_test_message'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // API bağlantı testi
        register_rest_route(self::NAMESPACE, '/test-connection', [
            'methods' => 'POST',
            'callback' => [$this, 'test_connection'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Veritabanı tablolarını onar
        register_rest_route(self::NAMESPACE, '/repair-tables', [
            'methods' => 'POST',
            'callback' => [$this, 'repair_tables'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Loglar
        register_rest_route(self::NAMESPACE, '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Tek log
        register_rest_route(self::NAMESPACE, '/logs/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_log'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_log'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        // Log istatistikleri
        register_rest_route(self::NAMESPACE, '/logs/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_log_stats'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Tüm logları temizle
        register_rest_route(self::NAMESPACE, '/logs/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_logs'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Mesajı yeniden gönder
        register_rest_route(self::NAMESPACE, '/logs/(?P<id>\d+)/resend', [
            'methods' => 'POST',
            'callback' => [$this, 'resend_message'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Sipariş durumları
        register_rest_route(self::NAMESPACE, '/order-statuses', [
            'methods' => 'GET',
            'callback' => [$this, 'get_order_statuses'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // API sağlayıcıları
        register_rest_route(self::NAMESPACE, '/providers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_providers'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Placeholder'lar
        register_rest_route(self::NAMESPACE, '/placeholders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_placeholders'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Dışa/İçe aktarma
        register_rest_route(self::NAMESPACE, '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_settings'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/import', [
            'methods' => 'POST',
            'callback' => [$this, 'import_settings'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // ========================================
        // Flow Builder Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/flows', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_flows'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_flow'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_flow'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_flow'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_flow'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>\d+)/toggle', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_flow'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>\d+)/duplicate', [
            'methods' => 'POST',
            'callback' => [$this, 'duplicate_flow'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/flows/triggers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_flow_triggers'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/flows/actions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_flow_actions'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/flows/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_flow_logs'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // ========================================
        // Abandoned Cart Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/abandoned-carts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_abandoned_carts'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/abandoned-carts/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_abandoned_cart_stats'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/abandoned-carts/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_abandoned_cart'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_abandoned_cart'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/abandoned-carts/(?P<id>\d+)/send-reminder', [
            'methods' => 'POST',
            'callback' => [$this, 'send_cart_reminder'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/abandoned-carts/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_abandoned_cart_settings'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_abandoned_cart_settings'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        // ========================================
        // Waitlist Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/waitlist', [
            'methods' => 'GET',
            'callback' => [$this, 'get_waitlist'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/waitlist/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_waitlist_stats'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/waitlist/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_waitlist_entry'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/waitlist/(?P<id>\d+)/notify', [
            'methods' => 'POST',
            'callback' => [$this, 'notify_waitlist_entry'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/waitlist/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_waitlist_settings'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_waitlist_settings'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        // Public waitlist subscription (no auth required)
        register_rest_route(self::NAMESPACE, '/waitlist/subscribe', [
            'methods' => 'POST',
            'callback' => [$this, 'subscribe_waitlist'],
            'permission_callback' => '__return_true'
        ]);

        // ========================================
        // Chatbot Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/chatbot/rules', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_chatbot_rules'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_chatbot_rule'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/chatbot/rules/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_chatbot_rule'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_chatbot_rule'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_chatbot_rule'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/chatbot/rules/(?P<id>\d+)/toggle', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_chatbot_rule'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/chatbot/conversations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_chatbot_conversations'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/chatbot/conversations/(?P<phone>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_conversation_history'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/chatbot/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_chatbot_settings'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_chatbot_settings'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/chatbot/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_chatbot_stats'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/chatbot/load-defaults', [
            'methods' => 'POST',
            'callback' => [$this, 'load_default_chatbot_rules'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/chatbot/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_chatbot_message'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // ========================================
        // Analytics Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/analytics/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics_overview'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/analytics/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_message_analytics'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/analytics/conversions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_conversion_analytics'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // ========================================
        // API Test & Webhook Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/test-connection', [
            'methods' => 'POST',
            'callback' => [$this, 'test_api_connection'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/send-test-message', [
            'methods' => 'POST',
            'callback' => [$this, 'send_test_message'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/preview-message', [
            'methods' => 'POST',
            'callback' => [$this, 'preview_message'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // Webhook endpoint (public - API sağlayıcıları çağırır)
        register_rest_route(self::NAMESPACE, '/webhook', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true'
        ]);

        // Setup Wizard
        register_rest_route(self::NAMESPACE, '/setup/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_setup_status'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/setup/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_setup'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/setup/skip', [
            'methods' => 'POST',
            'callback' => [$this, 'skip_setup'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // ========================================
        // Multi-language (i18n) Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/languages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_languages'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/templates/(?P<status>[a-z_-]+)/languages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_template_languages'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/templates/(?P<status>[a-z_-]+)/language/(?P<lang>[a-z_-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_template_for_language'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'save_template_for_language'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        // ========================================
        // Reports & Export Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/reports/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'get_report_summary'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/reports/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_messages_report'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/reports/export/csv', [
            'methods' => 'GET',
            'callback' => [$this, 'export_csv'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        register_rest_route(self::NAMESPACE, '/reports/export/pdf', [
            'methods' => 'GET',
            'callback' => [$this, 'export_pdf'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);

        // ========================================
        // License System Routes
        // ========================================
        register_rest_route(self::NAMESPACE, '/license', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_license_status'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'activate_license'],
                'permission_callback' => [$this, 'admin_permission_check']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deactivate_license'],
                'permission_callback' => [$this, 'admin_permission_check']
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/license/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verify_license'],
            'permission_callback' => [$this, 'admin_permission_check']
        ]);
    }

    /**
     * Admin yetki kontrolü
     */
    public function admin_permission_check() {
        return current_user_can('manage_options');
    }

    /**
     * Pro özellik yetki kontrolü (Admin + Lisans)
     */
    public function pro_permission_check() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        return WWA()->settings->is_pro_active();
    }

    /**
     * Pro özellik için lisans gerekli hatası döndür
     *
     * @param string $feature Özellik adı
     * @return WP_REST_Response
     */
    private function license_required_error($feature = '') {
        return new WP_REST_Response([
            'success' => false,
            'code' => 'license_required',
            'message' => __('Bu özellik Pro lisans gerektirir. Ayarlar > Lisans bölümünden lisansınızı aktifleştirin.', 'woo-whatsapp'),
            'feature' => $feature
        ], 403);
    }

    /**
     * Ayarları getir
     */
    public function get_settings() {
        // Cache'leri temizle
        wp_cache_flush();

        $settings = WWA()->settings->get_all();

        // Hassas verileri maskele (frontend için)
        $sensitive_keys = ['api_token', 'twilio_auth_token', 'ultramsg_token', 'wati_api_token'];
        foreach ($sensitive_keys as $key) {
            if (!empty($settings[$key])) {
                $settings[$key . '_set'] = true;
                $settings[$key] = str_repeat('•', 20);
            } else {
                $settings[$key . '_set'] = false;
            }
        }

        $response = new WP_REST_Response($settings, 200);

        // Cache'lemeyi engelle
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }

    /**
     * Ayarları güncelle
     */
    public function update_settings($request) {
        $params = $request->get_json_params();

        // JSON params boşsa body'den almayı dene
        if (empty($params)) {
            $body = $request->get_body();
            $params = json_decode($body, true);
        }

        // Debug log - gelen veriyi kaydet
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WWA update_settings called with: ' . print_r($params, true));
        }

        if (empty($params)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Geçersiz veri', 'woo-whatsapp')
            ], 400);
        }

        // Maskeli değerleri temizle (değişmemişse)
        $sensitive_keys = ['api_token', 'twilio_auth_token', 'ultramsg_token', 'wati_api_token'];
        foreach ($sensitive_keys as $key) {
            if (isset($params[$key]) && preg_match('/^•+$/', $params[$key])) {
                unset($params[$key]);
            }
        }

        // update_settings sanitize işlemini de yapıyor
        $result = WWA()->settings->update_settings($params);

        // Cache'leri temizle
        wp_cache_flush();

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WWA update_settings result: ' . ($result ? 'success' : 'failed'));
        }

        // Kayıt sonrası doğrulama - veritabanından oku
        $verification = [];
        foreach (array_keys($params) as $key) {
            $verification[$key] = WWA()->settings->get($key);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Ayarlar kaydedildi', 'woo-whatsapp'),
            'keys_saved' => array_keys($params),
            'verification' => $verification
        ], 200);
    }

    /**
     * Şablonları getir
     */
    public function get_templates() {
        $defaults = WWA()->settings->get_default_templates();
        $templates = WWA()->settings->get('templates', []);

        // Kullanıcının şablonları varsayılanların üzerine yazılsın
        $all_templates = array_merge($defaults, $templates);

        return new WP_REST_Response($all_templates, 200);
    }

    /**
     * Şablonları güncelle
     */
    public function update_templates($request) {
        $templates = $request->get_json_params();

        // JSON params boşsa body'den almayı dene
        if (empty($templates)) {
            $body = $request->get_body();
            $templates = json_decode($body, true);
        }

        if (!is_array($templates)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Geçersiz veri', 'woo-whatsapp')
            ], 400);
        }

        // Şablonları sanitize et
        $sanitized = [];
        foreach ($templates as $key => $value) {
            $sanitized[sanitize_text_field($key)] = sanitize_textarea_field($value);
        }

        $result = WWA()->settings->set('templates', $sanitized);

        // Cache'leri temizle
        wp_cache_flush();

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Şablonlar kaydedildi', 'woo-whatsapp')
        ], 200);
    }

    /**
     * Şablon önizleme
     */
    public function preview_template($request) {
        $template = $request->get_param('template');

        if (empty($template)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Şablon boş olamaz', 'woo-whatsapp')
            ], 400);
        }

        $preview = WWA_Template_Parser::preview($template);

        return new WP_REST_Response([
            'success' => true,
            'preview' => $preview
        ], 200);
    }

    /**
     * Test mesajı gönder
     */
    public function send_test_message($request) {
        $phone = $request->get_param('phone');
        $message = $request->get_param('message');

        if (empty($phone)) {
            $phone = WWA()->settings->get('admin_phone');
        }

        if (empty($phone)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Telefon numarası gerekli', 'woo-whatsapp')
            ], 400);
        }

        if (empty($message)) {
            $message = __('Bu bir test mesajıdır. Woo WhatsApp Bildirimcisi başarıyla yapılandırıldı!', 'woo-whatsapp');
        }

        $result = WWA()->api->send_message($phone, $message, ['force_send' => true]);

        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Test mesajı gönderildi', 'woo-whatsapp'),
                'message_id' => $result['message_id'] ?? null,
                'simulated' => $result['simulated'] ?? false
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $result['error'] ?? __('Mesaj gönderilemedi', 'woo-whatsapp')
        ], 400);
    }

    /**
     * API bağlantı testi
     */
    public function test_connection() {
        $result = WWA()->api->test_connection();

        return new WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['success']
                ? __('Bağlantı başarılı', 'woo-whatsapp')
                : ($result['error'] ?? __('Bağlantı başarısız', 'woo-whatsapp')),
            'simulated' => $result['simulated'] ?? false
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Veritabanı tablolarını onar/oluştur
     */
    public function repair_tables() {
        global $wpdb;

        // Transient'i temizle
        delete_transient('wwa_tables_checked_' . WWA_VERSION);

        // Tabloları oluştur
        $this->create_all_tables();

        // Tabloları kontrol et
        $tables = [
            'wwa_message_logs',
            'wwa_flows',
            'wwa_flow_logs',
            'wwa_abandoned_carts',
            'wwa_waitlist',
            'wwa_chatbot_rules',
            'wwa_chatbot_conversations',
            'wwa_scheduled_flows'
        ];

        $created = [];
        $failed = [];

        foreach ($tables as $table) {
            $full_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_name'") === $full_name) {
                $created[] = $table;
            } else {
                $failed[] = $table;
            }
        }

        if (empty($failed)) {
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Tüm tablolar başarıyla oluşturuldu!', 'woo-whatsapp'),
                'tables' => $created
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Bazı tablolar oluşturulamadı', 'woo-whatsapp'),
                'created' => $created,
                'failed' => $failed
            ], 500);
        }
    }

    /**
     * Logları getir
     */
    public function get_logs($request) {
        $args = [
            'per_page' => $request->get_param('per_page') ?: 20,
            'page' => $request->get_param('page') ?: 1,
            'status' => $request->get_param('status'),
            'recipient_type' => $request->get_param('recipient_type'),
            'order_id' => $request->get_param('order_id'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to')
        ];

        $logs = WWA()->logger->get_logs($args);
        $total = WWA()->logger->get_total_count([
            'status' => $args['status'],
            'recipient_type' => $args['recipient_type']
        ]);

        return new WP_REST_Response([
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        ], 200);
    }

    /**
     * Tek log getir
     */
    public function get_log($request) {
        $log_id = $request->get_param('id');
        $log = WWA()->logger->get_log($log_id);

        if (!$log) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Log bulunamadı', 'woo-whatsapp')
            ], 404);
        }

        return new WP_REST_Response($log, 200);
    }

    /**
     * Log sil
     */
    public function delete_log($request) {
        $log_id = $request->get_param('id');
        $result = WWA()->logger->delete($log_id);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('Log silindi', 'woo-whatsapp')
                : __('Log silinemedi', 'woo-whatsapp')
        ], $result ? 200 : 400);
    }

    /**
     * Log istatistikleri
     */
    public function get_log_stats() {
        $stats = WWA()->logger->get_stats();
        return new WP_REST_Response($stats, 200);
    }

    /**
     * Tüm logları temizle
     */
    public function clear_logs() {
        $result = WWA()->logger->clear_all();

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('Tüm loglar silindi', 'woo-whatsapp')
                : __('Loglar silinemedi', 'woo-whatsapp')
        ], $result ? 200 : 400);
    }

    /**
     * Mesajı yeniden gönder
     */
    public function resend_message($request) {
        $log_id = $request->get_param('id');
        $log = WWA()->logger->get_log($log_id);

        if (!$log) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Log bulunamadı', 'woo-whatsapp')
            ], 404);
        }

        // Mesajı yeniden gönder
        $result = WWA()->api->send_message($log['phone'], $log['message']);

        // Log güncelle
        if ($result['success']) {
            WWA()->logger->update_status($log_id, 'sent', [
                'message_id' => $result['message_id'] ?? null,
                'api_response' => $result['response'] ?? null
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Mesaj yeniden gönderildi', 'woo-whatsapp')
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $result['error'] ?? __('Mesaj gönderilemedi', 'woo-whatsapp')
        ], 400);
    }

    /**
     * Sipariş durumlarını getir
     */
    public function get_order_statuses() {
        $statuses = WWA()->settings->get_order_statuses();
        return new WP_REST_Response($statuses, 200);
    }

    /**
     * API sağlayıcılarını getir
     */
    public function get_providers() {
        $providers = WWA_API_Handler::get_providers();
        return new WP_REST_Response($providers, 200);
    }

    /**
     * Placeholder'ları getir
     */
    public function get_placeholders() {
        $placeholders = WWA()->settings->get_available_placeholders();
        return new WP_REST_Response($placeholders, 200);
    }

    /**
     * Ayarları dışa aktar
     */
    public function export_settings() {
        $json = WWA()->settings->export_settings();

        return new WP_REST_Response([
            'success' => true,
            'data' => json_decode($json, true)
        ], 200);
    }

    /**
     * Ayarları içe aktar
     */
    public function import_settings($request) {
        $data = $request->get_param('data');

        if (empty($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Veri boş olamaz', 'woo-whatsapp')
            ], 400);
        }

        $json = is_string($data) ? $data : wp_json_encode($data);
        $result = WWA()->settings->import_settings($json);

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Ayarlar içe aktarıldı', 'woo-whatsapp')
        ], 200);
    }

    // ========================================
    // Flow Builder Callbacks
    // ========================================

    /**
     * Flow listesini getir
     */
    public function get_flows($request) {
        // Pro lisans kontrolü
        if (!WWA()->settings->is_pro_active()) {
            return $this->license_required_error('Flow Builder');
        }

        $args = [
            'status' => $request->get_param('status'),
            'trigger_type' => $request->get_param('trigger_type'),
            'per_page' => $request->get_param('per_page') ?: 20,
            'page' => $request->get_param('page') ?: 1
        ];

        // Önce veritabanından eski flow'ları al
        global $wpdb;
        $table_name = $wpdb->prefix . 'wwa_flows';
        $all_flows = [];

        // Tablo var mı kontrol et
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            $db_results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
            if ($db_results) {
                foreach ($db_results as $flow) {
                    $flow['trigger_config'] = json_decode($flow['trigger_config'], true);
                    $flow['nodes'] = json_decode($flow['nodes'], true);
                    $flow['edges'] = json_decode($flow['edges'], true);
                    $flow['source'] = 'database';
                    $all_flows['db_' . $flow['id']] = $flow;
                }
            }
        }

        // wp_options'dan yeni flow'ları al (cache bypass)
        wp_cache_delete('wwa_flows_data', 'options');
        wp_cache_delete('alloptions', 'options');
        $option_flows = get_option('wwa_flows_data', []);

        // Veritabanındaki flow isimlerini topla (duplicate kontrolü için)
        $db_flow_names = [];
        foreach ($all_flows as $flow) {
            if (!empty($flow['name'])) {
                $db_flow_names[] = $flow['name'];
            }
        }

        foreach ($option_flows as $id => $flow) {
            // Aynı isimde veritabanında varsa ekleme (duplicate önle)
            if (!empty($flow['name']) && in_array($flow['name'], $db_flow_names)) {
                continue;
            }
            $flow['source'] = 'options';
            $all_flows['opt_' . $id] = $flow;
        }

        $flows = array_values($all_flows);

        // Status filtresi
        if (!empty($args['status'])) {
            $flows = array_filter($flows, function($f) use ($args) {
                return isset($f['status']) && $f['status'] === $args['status'];
            });
            $flows = array_values($flows);
        }

        // Trigger type filtresi
        if (!empty($args['trigger_type'])) {
            $flows = array_filter($flows, function($f) use ($args) {
                return isset($f['trigger_type']) && $f['trigger_type'] === $args['trigger_type'];
            });
            $flows = array_values($flows);
        }

        // Tarihe göre sırala (yeniden eskiye)
        usort($flows, function($a, $b) {
            $date_a = $a['created_at'] ?? '';
            $date_b = $b['created_at'] ?? '';
            return strcmp($date_b, $date_a);
        });

        $total = count($flows);

        // Pagination
        $per_page = (int) $args['per_page'];
        $page = (int) $args['page'];
        $offset = ($page - 1) * $per_page;
        $flows = array_slice($flows, $offset, $per_page);

        return new WP_REST_Response([
            'flows' => $flows,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ], 200);
    }

    /**
     * Flow oluştur
     */
    public function create_flow($request) {
        try {
            $data = $request->get_json_params();

            // JSON params boşsa body'den almayı dene
            if (empty($data)) {
                $body = $request->get_body();
                $data = json_decode($body, true);
            }

            if (empty($data)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Geçersiz veri gönderildi', 'woo-whatsapp')
                ], 400);
            }

            if (empty($data['name'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Flow adı gerekli', 'woo-whatsapp')
                ], 400);
            }

            $flow_id = WWA()->flow_builder->create_flow($data);

            if ($flow_id) {
                // Yeni oluşturulan flow'u wp_options'dan al
                wp_cache_delete('wwa_flows_data', 'options');
                $flows = get_option('wwa_flows_data', []);
                $new_flow = isset($flows[$flow_id]) ? $flows[$flow_id] : null;

                return new WP_REST_Response([
                    'success' => true,
                    'flow_id' => $flow_id,
                    'flow' => $new_flow,
                    'message' => __('Flow oluşturuldu', 'woo-whatsapp')
                ], 201);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => __('Flow oluşturulamadı', 'woo-whatsapp')
            ], 500);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tek flow getir
     */
    public function get_flow($request) {
        $flow_id = $request->get_param('id');
        $flow = WWA()->flow_builder->get_flow($flow_id);

        if (!$flow) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Flow bulunamadı', 'woo-whatsapp')
            ], 404);
        }

        return new WP_REST_Response($flow, 200);
    }

    /**
     * Flow güncelle
     */
    public function update_flow($request) {
        try {
            $flow_id = $request->get_param('id');
            $data = $request->get_json_params();

            // JSON params boşsa body'den almayı dene
            if (empty($data)) {
                $body = $request->get_body();
                $data = json_decode($body, true);
            }

            if (empty($data)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Geçersiz veri gönderildi', 'woo-whatsapp')
                ], 400);
            }

            $result = WWA()->flow_builder->update_flow($flow_id, $data);

            if ($result) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('Flow güncellendi', 'woo-whatsapp')
                ], 200);
            }

            global $wpdb;
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Flow güncellenemedi', 'woo-whatsapp'),
                'db_error' => $wpdb->last_error
            ], 400);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Flow sil
     */
    public function delete_flow($request) {
        $flow_id = $request->get_param('id');
        $result = false;

        // wp_options'dan silmeyi dene (hem string hem int key kontrol et)
        wp_cache_delete('wwa_flows_data', 'options');
        $flows = get_option('wwa_flows_data', []);

        // String ve integer key kontrolü
        $key_to_delete = null;
        if (isset($flows[$flow_id])) {
            $key_to_delete = $flow_id;
        } elseif (isset($flows[(int)$flow_id])) {
            $key_to_delete = (int)$flow_id;
        } elseif (isset($flows[(string)$flow_id])) {
            $key_to_delete = (string)$flow_id;
        }

        if ($key_to_delete !== null) {
            unset($flows[$key_to_delete]);
            update_option('wwa_flows_data', $flows, true);
            wp_cache_delete('wwa_flows_data', 'options');
            wp_cache_delete('alloptions', 'options');
            $result = true;
        }

        // Veritabanından da silmeyi dene
        if (!$result) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wwa_flows';
            $deleted = $wpdb->delete($table_name, ['id' => (int)$flow_id], ['%d']);
            $result = ($deleted !== false && $deleted > 0);
        }

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('Flow silindi', 'woo-whatsapp')
                : __('Flow silinemedi', 'woo-whatsapp')
        ], $result ? 200 : 400);
    }

    /**
     * Flow aktif/pasif yap
     */
    public function toggle_flow($request) {
        $flow_id = $request->get_param('id');
        $result = false;
        $new_status = null;

        // Önce wp_options'dan dene
        $flows = get_option('wwa_flows_data', []);
        if (isset($flows[$flow_id])) {
            $current = $flows[$flow_id]['status'] ?? 'inactive';
            $new_status = ($current === 'active') ? 'inactive' : 'active';
            $flows[$flow_id]['status'] = $new_status;
            $flows[$flow_id]['updated_at'] = current_time('mysql');
            update_option('wwa_flows_data', $flows, true);
            wp_cache_delete('wwa_flows_data', 'options');
            wp_cache_delete('alloptions', 'options');
            $result = true;
        }

        // Veritabanından da dene
        if (!$result) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wwa_flows';
            $current = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM $table_name WHERE id = %d",
                $flow_id
            ));
            if ($current !== null) {
                $new_status = ($current === 'active') ? 'inactive' : 'active';
                $wpdb->update(
                    $table_name,
                    ['status' => $new_status],
                    ['id' => $flow_id],
                    ['%s'],
                    ['%d']
                );
                $result = true;
            }
        }

        return new WP_REST_Response([
            'success' => $result,
            'status' => $new_status,
            'message' => $result
                ? __('Flow durumu güncellendi', 'woo-whatsapp')
                : __('Flow durumu güncellenemedi', 'woo-whatsapp')
        ], $result ? 200 : 400);
    }

    /**
     * Flow kopyala
     */
    public function duplicate_flow($request) {
        $flow_id = $request->get_param('id');
        $new_id = WWA()->flow_builder->duplicate_flow($flow_id);

        if ($new_id) {
            return new WP_REST_Response([
                'success' => true,
                'flow_id' => $new_id,
                'message' => __('Flow kopyalandı', 'woo-whatsapp')
            ], 201);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => __('Flow kopyalanamadı', 'woo-whatsapp')
        ], 400);
    }

    /**
     * Flow trigger tiplerini getir
     */
    public function get_flow_triggers() {
        $triggers = WWA_Flow_Builder::get_available_triggers();
        return new WP_REST_Response($triggers, 200);
    }

    /**
     * Flow action tiplerini getir
     */
    public function get_flow_actions() {
        $actions = WWA_Flow_Builder::get_available_actions();
        return new WP_REST_Response($actions, 200);
    }

    /**
     * Flow loglarını getir
     */
    public function get_flow_logs($request) {
        $args = [
            'flow_id' => $request->get_param('flow_id'),
            'per_page' => $request->get_param('per_page') ?: 50,
            'page' => $request->get_param('page') ?: 1
        ];

        $logs = WWA()->flow_builder->get_logs($args);

        return new WP_REST_Response($logs, 200);
    }

    // ========================================
    // Abandoned Cart Callbacks
    // ========================================

    /**
     * Terk edilmiş sepetleri getir
     */
    public function get_abandoned_carts($request) {
        // Pro lisans kontrolü
        if (!WWA()->settings->is_pro_active()) {
            return $this->license_required_error('Sepet Kurtarma');
        }

        $args = [
            'status' => $request->get_param('status'),
            'per_page' => $request->get_param('per_page') ?: 20,
            'page' => $request->get_param('page') ?: 1
        ];

        $carts = WWA()->abandoned_cart->get_carts($args);
        $total = WWA()->abandoned_cart->get_carts_count($args);

        return new WP_REST_Response([
            'carts' => $carts,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        ], 200);
    }

    /**
     * Terk edilmiş sepet istatistikleri
     */
    public function get_abandoned_cart_stats() {
        $stats = WWA()->abandoned_cart->get_stats();
        return new WP_REST_Response($stats, 200);
    }

    /**
     * Tek sepet getir
     */
    public function get_abandoned_cart($request) {
        $cart_id = $request->get_param('id');
        $cart = WWA()->abandoned_cart->get_cart($cart_id);

        if (!$cart) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Sepet bulunamadı', 'woo-whatsapp')
            ], 404);
        }

        return new WP_REST_Response($cart, 200);
    }

    /**
     * Sepet sil
     */
    public function delete_abandoned_cart($request) {
        $cart_id = $request->get_param('id');
        $result = WWA()->abandoned_cart->delete_cart($cart_id);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('Sepet silindi', 'woo-whatsapp')
                : __('Sepet silinemedi', 'woo-whatsapp')
        ], $result ? 200 : 400);
    }

    /**
     * Sepet hatırlatması gönder
     */
    public function send_cart_reminder($request) {
        $cart_id = $request->get_param('id');
        $result = WWA()->abandoned_cart->send_reminder($cart_id);

        return new WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['success']
                ? __('Hatırlatma gönderildi', 'woo-whatsapp')
                : ($result['error'] ?? __('Hatırlatma gönderilemedi', 'woo-whatsapp'))
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Abandoned cart ayarlarını getir
     */
    public function get_abandoned_cart_settings() {
        $settings = [
            'enabled' => get_option('wwa_abandoned_cart_enabled', 'yes'),
            'threshold_minutes' => get_option('wwa_abandoned_cart_threshold', 15),
            'reminder_delays' => get_option('wwa_abandoned_cart_reminders', [60, 1440, 4320]),
            'coupon_enabled' => get_option('wwa_abandoned_cart_coupon', 'no'),
            'coupon_amount' => get_option('wwa_abandoned_cart_coupon_amount', 10),
            'coupon_type' => get_option('wwa_abandoned_cart_coupon_type', 'percent'),
            'template' => get_option('wwa_abandoned_cart_template', '')
        ];

        return new WP_REST_Response($settings, 200);
    }

    /**
     * Abandoned cart ayarlarını güncelle
     */
    public function update_abandoned_cart_settings($request) {
        $params = $request->get_json_params();

        // JSON params boşsa body'den almayı dene
        if (empty($params)) {
            $body = $request->get_body();
            $params = json_decode($body, true);
        }

        $settings_map = [
            'enabled' => 'wwa_abandoned_cart_enabled',
            'threshold_minutes' => 'wwa_abandoned_cart_threshold',
            'reminder_delays' => 'wwa_abandoned_cart_reminders',
            'coupon_enabled' => 'wwa_abandoned_cart_coupon',
            'coupon_amount' => 'wwa_abandoned_cart_coupon_amount',
            'coupon_type' => 'wwa_abandoned_cart_coupon_type',
            'template' => 'wwa_abandoned_cart_template'
        ];

        foreach ($params as $key => $value) {
            if (isset($settings_map[$key])) {
                update_option($settings_map[$key], $value);
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Ayarlar kaydedildi', 'woo-whatsapp')
        ], 200);
    }

    // ========================================
    // Waitlist Callbacks
    // ========================================

    /**
     * Waitlist girişlerini getir
     */
    public function get_waitlist($request) {
        // Pro lisans kontrolü
        if (!WWA()->settings->is_pro_active()) {
            return $this->license_required_error('Stok Bildirimi');
        }

        $args = [
            'product_id' => $request->get_param('product_id'),
            'status' => $request->get_param('status'),
            'per_page' => $request->get_param('per_page') ?: 20,
            'page' => $request->get_param('page') ?: 1
        ];

        $entries = WWA()->waitlist->get_entries($args);
        $total = WWA()->waitlist->get_entries_count($args);

        return new WP_REST_Response([
            'entries' => $entries,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        ], 200);
    }

    /**
     * Waitlist istatistikleri
     */
    public function get_waitlist_stats() {
        $stats = WWA()->waitlist->get_stats();
        return new WP_REST_Response($stats, 200);
    }

    /**
     * Waitlist girişi sil
     */
    public function delete_waitlist_entry($request) {
        $entry_id = $request->get_param('id');
        $result = WWA()->waitlist->delete_entry($entry_id);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('Giriş silindi', 'woo-whatsapp')
                : __('Giriş silinemedi', 'woo-whatsapp')
        ], $result ? 200 : 400);
    }

    /**
     * Waitlist girişine bildirim gönder
     */
    public function notify_waitlist_entry($request) {
        $entry_id = $request->get_param('id');
        $result = WWA()->waitlist->notify_entry($entry_id);

        return new WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['success']
                ? __('Bildirim gönderildi', 'woo-whatsapp')
                : ($result['error'] ?? __('Bildirim gönderilemedi', 'woo-whatsapp'))
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Waitlist ayarlarını getir
     */
    public function get_waitlist_settings() {
        $settings = [
            'enabled' => get_option('wwa_waitlist_enabled', 'yes'),
            'form_title' => get_option('wwa_waitlist_form_title', __('Stok bildirimi al', 'woo-whatsapp')),
            'form_description' => get_option('wwa_waitlist_form_desc', __('Ürün stoğa girdiğinde bilgilendirilmek için kayıt olun.', 'woo-whatsapp')),
            'success_message' => get_option('wwa_waitlist_success_msg', __('Kaydınız alındı! Ürün stoğa girdiğinde size haber vereceğiz.', 'woo-whatsapp')),
            'notification_template' => get_option('wwa_waitlist_template', ''),
            'auto_notify' => get_option('wwa_waitlist_auto_notify', 'yes')
        ];

        return new WP_REST_Response($settings, 200);
    }

    /**
     * Waitlist ayarlarını güncelle
     */
    public function update_waitlist_settings($request) {
        $params = $request->get_json_params();

        // JSON params boşsa body'den almayı dene
        if (empty($params)) {
            $body = $request->get_body();
            $params = json_decode($body, true);
        }

        $settings_map = [
            'enabled' => 'wwa_waitlist_enabled',
            'form_title' => 'wwa_waitlist_form_title',
            'form_description' => 'wwa_waitlist_form_desc',
            'success_message' => 'wwa_waitlist_success_msg',
            'notification_template' => 'wwa_waitlist_template',
            'auto_notify' => 'wwa_waitlist_auto_notify'
        ];

        foreach ($params as $key => $value) {
            if (isset($settings_map[$key])) {
                update_option($settings_map[$key], sanitize_textarea_field($value));
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Ayarlar kaydedildi', 'woo-whatsapp')
        ], 200);
    }

    /**
     * Public waitlist subscription
     */
    public function subscribe_waitlist($request) {
        $product_id = $request->get_param('product_id');
        $phone = $request->get_param('phone');
        $email = $request->get_param('email');

        if (empty($product_id) || (empty($phone) && empty($email))) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Geçersiz veri', 'woo-whatsapp')
            ], 400);
        }

        $result = WWA()->waitlist->subscribe($product_id, $phone, $email);

        return new WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['success']
                ? get_option('wwa_waitlist_success_msg', __('Kaydınız alındı!', 'woo-whatsapp'))
                : ($result['error'] ?? __('Kayıt yapılamadı', 'woo-whatsapp'))
        ], $result['success'] ? 200 : 400);
    }

    // ========================================
    // Chatbot Callbacks
    // ========================================

    /**
     * Chatbot kurallarını getir
     */
    public function get_chatbot_rules($request) {
        // Pro lisans kontrolü
        if (!WWA()->settings->is_pro_active()) {
            return $this->license_required_error('Chatbot');
        }

        $args = [
            'is_active' => $request->get_param('is_active'),
            'per_page' => $request->get_param('per_page') ?: 50,
            'page' => $request->get_param('page') ?: 1
        ];

        $rules = WWA()->chatbot->get_rules($args);

        return new WP_REST_Response($rules, 200);
    }

    /**
     * Chatbot kuralı oluştur
     */
    public function create_chatbot_rule($request) {
        try {
            $data = $request->get_json_params();

            // JSON params boşsa body'den almayı dene
            if (empty($data)) {
                $body = $request->get_body();
                $data = json_decode($body, true);
            }

            if (empty($data['keywords']) || empty($data['response'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Anahtar kelime ve yanıt gerekli', 'woo-whatsapp')
                ], 400);
            }

            $rule_id = WWA()->chatbot->create_rule($data);

            if ($rule_id) {
                return new WP_REST_Response([
                    'success' => true,
                    'rule_id' => $rule_id,
                    'message' => __('Kural oluşturuldu', 'woo-whatsapp')
                ], 201);
            }

            global $wpdb;
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Kural oluşturulamadı', 'woo-whatsapp'),
                'db_error' => $wpdb->last_error
            ], 500);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tek chatbot kuralı getir
     */
    public function get_chatbot_rule($request) {
        $rule_id = $request->get_param('id');
        $rule = WWA()->chatbot->get_rule($rule_id);

        if (!$rule) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Kural bulunamadı', 'woo-whatsapp')
            ], 404);
        }

        return new WP_REST_Response($rule, 200);
    }

    /**
     * Chatbot kuralını güncelle
     */
    public function update_chatbot_rule($request) {
        try {
            $rule_id = $request->get_param('id');
            $data = $request->get_json_params();

            // JSON params boşsa body'den almayı dene
            if (empty($data)) {
                $body = $request->get_body();
                $data = json_decode($body, true);
            }

            if (empty($data)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Geçersiz veri gönderildi', 'woo-whatsapp')
                ], 400);
            }

            $result = WWA()->chatbot->update_rule($rule_id, $data);

            if ($result) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('Kural güncellendi', 'woo-whatsapp')
                ], 200);
            }

            global $wpdb;
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Kural güncellenemedi', 'woo-whatsapp'),
                'db_error' => $wpdb->last_error
            ], 400);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chatbot kuralını sil
     */
    public function delete_chatbot_rule($request) {
        $rule_id = $request->get_param('id');
        $result = WWA()->chatbot->delete_rule($rule_id);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('Kural silindi', 'woo-whatsapp')
                : __('Kural silinemedi', 'woo-whatsapp')
        ], $result ? 200 : 400);
    }

    /**
     * Chatbot kuralını aktif/pasif yap
     */
    public function toggle_chatbot_rule($request) {
        $rule_id = $request->get_param('id');
        $result = WWA()->chatbot->toggle_rule($rule_id);

        return new WP_REST_Response([
            'success' => $result !== false,
            'is_active' => $result,
            'message' => $result !== false
                ? __('Kural durumu güncellendi', 'woo-whatsapp')
                : __('Kural durumu güncellenemedi', 'woo-whatsapp')
        ], $result !== false ? 200 : 400);
    }

    /**
     * Chatbot konuşmalarını getir
     */
    public function get_chatbot_conversations($request) {
        $args = [
            'per_page' => $request->get_param('per_page') ?: 50,
            'page' => $request->get_param('page') ?: 1
        ];

        $conversations = WWA()->chatbot->get_conversations($args);

        return new WP_REST_Response($conversations, 200);
    }

    /**
     * Konuşma geçmişini getir
     */
    public function get_conversation_history($request) {
        $phone = urldecode($request->get_param('phone'));
        $history = WWA()->chatbot->get_conversation_history($phone);

        return new WP_REST_Response($history, 200);
    }

    /**
     * Chatbot ayarlarını getir
     */
    public function get_chatbot_settings() {
        $settings = [
            'enabled' => get_option('wwa_chatbot_enabled', 'yes'),
            'fallback_message' => get_option('wwa_chatbot_fallback', __('Mesajınız alındı. En kısa sürede size dönüş yapacağız.', 'woo-whatsapp')),
            'working_hours_enabled' => get_option('wwa_chatbot_working_hours', 'no'),
            'working_hours_start' => get_option('wwa_chatbot_hours_start', '09:00'),
            'working_hours_end' => get_option('wwa_chatbot_hours_end', '18:00'),
            'outside_hours_message' => get_option('wwa_chatbot_outside_hours_msg', __('Şu anda çalışma saatleri dışındayız. En kısa sürede size dönüş yapacağız.', 'woo-whatsapp')),
            'notify_admin' => get_option('wwa_chatbot_notify_admin', 'no'),
            'welcome_message' => get_option('wwa_chatbot_welcome_msg', ''),
            'delay_seconds' => get_option('wwa_chatbot_delay', 2)
        ];

        return new WP_REST_Response($settings, 200);
    }

    /**
     * Chatbot ayarlarını güncelle
     */
    public function update_chatbot_settings($request) {
        $params = $request->get_json_params();

        // JSON params boşsa body'den almayı dene
        if (empty($params)) {
            $body = $request->get_body();
            $params = json_decode($body, true);
        }

        $settings_map = [
            'enabled' => 'wwa_chatbot_enabled',
            'fallback_message' => 'wwa_chatbot_fallback',
            'working_hours_enabled' => 'wwa_chatbot_working_hours',
            'working_hours_start' => 'wwa_chatbot_hours_start',
            'working_hours_end' => 'wwa_chatbot_hours_end',
            'outside_hours_message' => 'wwa_chatbot_outside_hours_msg',
            'notify_admin' => 'wwa_chatbot_notify_admin',
            'welcome_message' => 'wwa_chatbot_welcome_msg',
            'delay_seconds' => 'wwa_chatbot_delay'
        ];

        foreach ($params as $key => $value) {
            if (isset($settings_map[$key])) {
                update_option($settings_map[$key], $value);
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Ayarlar kaydedildi', 'woo-whatsapp')
        ], 200);
    }

    /**
     * Chatbot istatistikleri
     */
    public function get_chatbot_stats() {
        $stats = WWA()->chatbot->get_stats();
        return new WP_REST_Response($stats, 200);
    }

    /**
     * Varsayılan chatbot kurallarını yükle
     */
    public function load_default_chatbot_rules() {
        WWA()->chatbot->add_default_rules();

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Varsayılan kurallar yüklendi', 'woo-whatsapp')
        ], 200);
    }

    /**
     * Chatbot mesajını test et (simülasyon)
     */
    public function test_chatbot_message($request) {
        $params = $request->get_json_params();

        if (empty($params)) {
            $body = $request->get_body();
            $params = json_decode($body, true);
        }

        $message = $params['message'] ?? '';
        $phone = $params['phone'] ?? '905551234567';

        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Test mesajı gerekli', 'woo-whatsapp')
            ], 400);
        }

        // Mesajı işle ama gerçekten gönderme
        $message_lower = mb_strtolower(trim($message), 'UTF-8');

        // Kuralları kontrol et
        $rules = WWA()->chatbot->get_active_rules();
        $matched_rule = null;

        foreach ($rules as $rule) {
            $keywords = json_decode($rule['keywords'], true) ?: explode(',', $rule['keywords']);
            $match_type = $rule['match_type'];

            foreach ($keywords as $keyword) {
                $keyword = mb_strtolower(trim($keyword), 'UTF-8');
                if (empty($keyword)) continue;

                $matched = false;
                switch ($match_type) {
                    case 'exact':
                        $matched = ($message_lower === $keyword);
                        break;
                    case 'contains':
                        $matched = (strpos($message_lower, $keyword) !== false);
                        break;
                    case 'starts_with':
                        $matched = (strpos($message_lower, $keyword) === 0);
                        break;
                    case 'ends_with':
                        $matched = (substr($message_lower, -strlen($keyword)) === $keyword);
                        break;
                    case 'regex':
                        $matched = (preg_match('/' . $keyword . '/ui', $message_lower) === 1);
                        break;
                }

                if ($matched) {
                    $matched_rule = $rule;
                    break 2;
                }
            }
        }

        if ($matched_rule) {
            $response_content = $matched_rule['response_content'];

            // Basit placeholder değiştirme
            $replacements = [
                '{müşteri_adı}' => 'Test Müşteri',
                '{mağaza_adı}' => get_bloginfo('name'),
                '{telefon}' => $phone,
                '{tarih}' => date_i18n(get_option('date_format')),
                '{saat}' => date_i18n(get_option('time_format')),
            ];
            $response_content = str_replace(array_keys($replacements), array_values($replacements), $response_content);

            return new WP_REST_Response([
                'success' => true,
                'matched' => true,
                'rule_name' => $matched_rule['name'],
                'rule_id' => $matched_rule['id'],
                'response' => $response_content
            ], 200);
        }

        // Eşleşme yok
        $default_response = get_option('wwa_chatbot_default_response', '');

        return new WP_REST_Response([
            'success' => true,
            'matched' => false,
            'response' => $default_response ?: __('Eşleşen kural bulunamadı', 'woo-whatsapp')
        ], 200);
    }

    // ========================================
    // Analytics Callbacks
    // ========================================

    /**
     * Genel analitik özeti
     */
    public function get_analytics_overview($request) {
        // Pro lisans kontrolü
        if (!WWA()->settings->is_pro_active()) {
            return $this->license_required_error('Analitik');
        }

        $period = $request->get_param('period') ?: '30days';

        $date_from = $this->get_period_start($period);
        $date_to = current_time('mysql');

        global $wpdb;

        // Mesaj istatistikleri
        $message_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_messages,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$wpdb->prefix}wwa_message_logs
            WHERE created_at BETWEEN %s AND %s",
            $date_from,
            $date_to
        ), ARRAY_A);

        // Terk edilmiş sepet istatistikleri
        $cart_stats = [];
        if (WWA()->abandoned_cart) {
            $cart_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as total_abandoned,
                    SUM(CASE WHEN status = 'recovered' THEN 1 ELSE 0 END) as recovered,
                    SUM(cart_total) as total_value,
                    SUM(CASE WHEN status = 'recovered' THEN cart_total ELSE 0 END) as recovered_value
                FROM {$wpdb->prefix}wwa_abandoned_carts
                WHERE created_at BETWEEN %s AND %s",
                $date_from,
                $date_to
            ), ARRAY_A);
        }

        // Flow istatistikleri
        $flow_stats = [];
        if (WWA()->flow_builder) {
            $flow_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as total_executions,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$wpdb->prefix}wwa_flow_logs
                WHERE created_at BETWEEN %s AND %s",
                $date_from,
                $date_to
            ), ARRAY_A);
        }

        // Chatbot istatistikleri
        $chatbot_stats = [];
        if (WWA()->chatbot) {
            $chatbot_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as total_messages,
                    COUNT(DISTINCT phone) as unique_users,
                    SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming,
                    SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing
                FROM {$wpdb->prefix}wwa_chatbot_logs
                WHERE created_at BETWEEN %s AND %s",
                $date_from,
                $date_to
            ), ARRAY_A);
        }

        return new WP_REST_Response([
            'period' => $period,
            'messages' => $message_stats,
            'abandoned_carts' => $cart_stats,
            'flows' => $flow_stats,
            'chatbot' => $chatbot_stats
        ], 200);
    }

    /**
     * Mesaj analitikleri
     */
    public function get_message_analytics($request) {
        $period = $request->get_param('period') ?: '30days';
        $group_by = $request->get_param('group_by') ?: 'day';

        $date_from = $this->get_period_start($period);

        global $wpdb;

        $date_format = $group_by === 'day' ? '%Y-%m-%d' : ($group_by === 'week' ? '%Y-%u' : '%Y-%m');

        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE_FORMAT(created_at, %s) as period,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$wpdb->prefix}wwa_message_logs
            WHERE created_at >= %s
            GROUP BY DATE_FORMAT(created_at, %s)
            ORDER BY period ASC",
            $date_format,
            $date_from,
            $date_format
        ), ARRAY_A);

        // Durum dağılımı
        $status_distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT
                status,
                COUNT(*) as count
            FROM {$wpdb->prefix}wwa_message_logs
            WHERE created_at >= %s
            GROUP BY status",
            $date_from
        ), ARRAY_A);

        // En çok mesaj gönderilen durumlar
        $top_statuses = $wpdb->get_results($wpdb->prepare(
            "SELECT
                status_type,
                COUNT(*) as count
            FROM {$wpdb->prefix}wwa_message_logs
            WHERE created_at >= %s AND status_type IS NOT NULL
            GROUP BY status_type
            ORDER BY count DESC
            LIMIT 10",
            $date_from
        ), ARRAY_A);

        return new WP_REST_Response([
            'timeline' => $data,
            'status_distribution' => $status_distribution,
            'top_order_statuses' => $top_statuses
        ], 200);
    }

    /**
     * Dönüşüm analitikleri
     */
    public function get_conversion_analytics($request) {
        $period = $request->get_param('period') ?: '30days';
        $date_from = $this->get_period_start($period);

        global $wpdb;

        // Terk edilmiş sepet dönüşümü
        $cart_conversion = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_carts,
                SUM(CASE WHEN status = 'recovered' THEN 1 ELSE 0 END) as recovered_carts,
                SUM(cart_total) as total_value,
                SUM(CASE WHEN status = 'recovered' THEN cart_total ELSE 0 END) as recovered_value,
                AVG(CASE WHEN status = 'recovered' THEN cart_total ELSE NULL END) as avg_recovered_value
            FROM {$wpdb->prefix}wwa_abandoned_carts
            WHERE created_at >= %s",
            $date_from
        ), ARRAY_A);

        // Hatırlatma etkinliği
        $reminder_effectiveness = $wpdb->get_results($wpdb->prepare(
            "SELECT
                reminder_count,
                COUNT(*) as carts,
                SUM(CASE WHEN status = 'recovered' THEN 1 ELSE 0 END) as recovered
            FROM {$wpdb->prefix}wwa_abandoned_carts
            WHERE created_at >= %s
            GROUP BY reminder_count
            ORDER BY reminder_count",
            $date_from
        ), ARRAY_A);

        // Waitlist dönüşümü
        $waitlist_conversion = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_subscriptions,
                SUM(CASE WHEN status = 'notified' THEN 1 ELSE 0 END) as notified,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted
            FROM {$wpdb->prefix}wwa_waitlist
            WHERE created_at >= %s",
            $date_from
        ), ARRAY_A);

        // Zaman bazlı kurtarma analizi
        $recovery_timing = $wpdb->get_results($wpdb->prepare(
            "SELECT
                TIMESTAMPDIFF(HOUR, created_at, recovered_at) as hours_to_recover,
                COUNT(*) as recoveries
            FROM {$wpdb->prefix}wwa_abandoned_carts
            WHERE status = 'recovered' AND created_at >= %s
            GROUP BY FLOOR(TIMESTAMPDIFF(HOUR, created_at, recovered_at) / 6) * 6
            ORDER BY hours_to_recover",
            $date_from
        ), ARRAY_A);

        return new WP_REST_Response([
            'cart_conversion' => $cart_conversion,
            'reminder_effectiveness' => $reminder_effectiveness,
            'waitlist_conversion' => $waitlist_conversion,
            'recovery_timing' => $recovery_timing,
            'conversion_rate' => $cart_conversion['total_carts'] > 0
                ? round(($cart_conversion['recovered_carts'] / $cart_conversion['total_carts']) * 100, 2)
                : 0
        ], 200);
    }

    /**
     * Periyot başlangıç tarihini hesapla
     */
    private function get_period_start($period) {
        switch ($period) {
            case '7days':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            case '90days':
                return date('Y-m-d H:i:s', strtotime('-90 days'));
            case '1year':
                return date('Y-m-d H:i:s', strtotime('-1 year'));
            default:
                return date('Y-m-d H:i:s', strtotime('-30 days'));
        }
    }

    // ========================================
    // API Test & Webhook Callbacks
    // ========================================

    /**
     * API bağlantısını test et
     */
    public function test_api_connection($request) {
        $provider = $request->get_param('provider') ?: get_option('wwa_api_provider', 'whatsapp_business');

        // Bağlantı durumunu kontrol et
        $is_configured = WWA()->api->is_configured();

        if (!$is_configured) {
            return new WP_REST_Response([
                'success' => false,
                'status' => 'not_configured',
                'message' => __('API ayarları yapılandırılmamış. Lütfen önce API bilgilerinizi girin.', 'woo-whatsapp')
            ], 200);
        }

        // Gerçek bağlantı testi
        $result = WWA()->api->test_connection();

        return new WP_REST_Response([
            'success' => $result['success'],
            'status' => $result['success'] ? 'connected' : 'failed',
            'message' => $result['success']
                ? __('API bağlantısı başarılı! Test mesajı gönderildi.', 'woo-whatsapp')
                : ($result['error'] ?? __('API bağlantısı başarısız', 'woo-whatsapp')),
            'message_id' => $result['message_id'] ?? null,
            'provider' => $provider
        ], 200);
    }


    public function preview_message($request) {
        $template = $request->get_param('template');
        $order_id = $request->get_param('order_id');
        $sample_data = $request->get_param('sample_data');

        if (empty($template)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Şablon gerekli', 'woo-whatsapp')
            ], 400);
        }

        // Gerçek sipariş varsa kullan
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order) {
                $parsed = WWA()->settings->parse_template($template, $order);
                return new WP_REST_Response([
                    'success' => true,
                    'preview' => $parsed,
                    'source' => 'order',
                    'order_id' => $order_id
                ], 200);
            }
        }

        // Örnek veri ile önizleme
        $sample = [
            '{sipariş_no}' => '#12345',
            '{müşteri_adı}' => 'Mehmet Yılmaz',
            '{müşteri_email}' => 'mehmet@example.com',
            '{müşteri_telefon}' => '+90 532 123 4567',
            '{sipariş_durumu}' => 'İşleniyor',
            '{sipariş_toplam}' => '₺1.250,00',
            '{sipariş_tarihi}' => date_i18n('d.m.Y H:i'),
            '{ödeme_yöntemi}' => 'Kredi Kartı',
            '{kargo_yöntemi}' => 'Ücretsiz Kargo',
            '{ürün_listesi}' => "• iPhone 15 Pro x1 - ₺1.000,00\n• AirPods Pro x1 - ₺250,00",
            '{sipariş_notu}' => 'Lütfen hediye paketi yapın',
            '{fatura_adresi}' => 'Atatürk Cad. No:123, İstanbul',
            '{teslimat_adresi}' => 'Cumhuriyet Mah. Sok:45, Ankara',
            '{mağaza_adı}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
            '{ürün_adı}' => 'Örnek Ürün',
            '{ürün_fiyat}' => '₺500,00',
            '{ürün_linki}' => home_url('/urun/ornek-urun/'),
            '{kupon_kodu}' => 'HOSGELDIN10',
            '{indirim_tutarı}' => '₺50,00'
        ];

        // Özel örnek veri varsa birleştir
        if ($sample_data && is_array($sample_data)) {
            $sample = array_merge($sample, $sample_data);
        }

        $preview = str_replace(array_keys($sample), array_values($sample), $template);

        return new WP_REST_Response([
            'success' => true,
            'preview' => $preview,
            'source' => 'sample',
            'variables' => array_keys($sample)
        ], 200);
    }

    /**
     * Webhook handler - WhatsApp'tan gelen mesajları işle
     */
    public function handle_webhook($request) {
        $method = $request->get_method();

        // GET - Webhook doğrulama (Meta için)
        if ($method === 'GET') {
            $mode = $request->get_param('hub_mode');
            $token = $request->get_param('hub_verify_token');
            $challenge = $request->get_param('hub_challenge');

            $verify_token = get_option('wwa_webhook_verify_token', '');

            if ($mode === 'subscribe' && $token === $verify_token) {
                return new WP_REST_Response($challenge, 200);
            }

            return new WP_REST_Response('Forbidden', 403);
        }

        // POST - Gelen mesajları işle
        $body = $request->get_json_params();
        $provider = $this->detect_webhook_provider($body, $request);

        // Webhook logla
        WWA()->logger->debug_log('Webhook alındı', [
            'provider' => $provider,
            'body' => $body
        ]);

        // Mesajları işle
        $messages = $this->extract_webhook_messages($body, $provider);

        foreach ($messages as $msg) {
            $this->process_incoming_message($msg, $provider);
        }

        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Webhook sağlayıcısını tespit et
     */
    private function detect_webhook_provider($body, $request) {
        // Meta WhatsApp Business API
        if (isset($body['object']) && $body['object'] === 'whatsapp_business_account') {
            return 'whatsapp_business';
        }

        // Twilio
        if ($request->get_param('AccountSid') || isset($body['AccountSid'])) {
            return 'twilio';
        }

        // Ultramsg
        if (isset($body['event_type'])) {
            return 'ultramsg';
        }

        // WATI
        if (isset($body['waId'])) {
            return 'wati';
        }

        return 'unknown';
    }

    /**
     * Webhook'tan mesajları çıkar
     */
    private function extract_webhook_messages($body, $provider) {
        $messages = [];

        switch ($provider) {
            case 'whatsapp_business':
                if (isset($body['entry'])) {
                    foreach ($body['entry'] as $entry) {
                        if (isset($entry['changes'])) {
                            foreach ($entry['changes'] as $change) {
                                if (isset($change['value']['messages'])) {
                                    foreach ($change['value']['messages'] as $msg) {
                                        $messages[] = [
                                            'id' => $msg['id'],
                                            'from' => $msg['from'],
                                            'type' => $msg['type'],
                                            'text' => $msg['text']['body'] ?? '',
                                            'timestamp' => $msg['timestamp']
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            case 'twilio':
                if (isset($body['Body'])) {
                    $messages[] = [
                        'id' => $body['MessageSid'] ?? uniqid(),
                        'from' => str_replace('whatsapp:', '', $body['From'] ?? ''),
                        'type' => 'text',
                        'text' => $body['Body'],
                        'timestamp' => time()
                    ];
                }
                break;

            case 'ultramsg':
                if (isset($body['data'])) {
                    $messages[] = [
                        'id' => $body['data']['id'] ?? uniqid(),
                        'from' => $body['data']['from'] ?? '',
                        'type' => 'text',
                        'text' => $body['data']['body'] ?? '',
                        'timestamp' => time()
                    ];
                }
                break;

            case 'wati':
                if (isset($body['text'])) {
                    $messages[] = [
                        'id' => $body['id'] ?? uniqid(),
                        'from' => $body['waId'] ?? '',
                        'type' => 'text',
                        'text' => $body['text'],
                        'timestamp' => time()
                    ];
                }
                break;
        }

        return $messages;
    }

    /**
     * Gelen mesajı işle
     */
    private function process_incoming_message($message, $provider) {
        $phone = preg_replace('/[^0-9]/', '', $message['from']);
        $text = $message['text'];

        // Chatbot aktifse işle
        if (WWA()->chatbot && get_option('wwa_chatbot_enabled') === 'yes') {
            WWA()->chatbot->handle_incoming_message($phone, $text, $message);
        }

        // Gelen mesajı logla
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wwa_chatbot_logs',
            [
                'phone' => $phone,
                'message' => $text,
                'direction' => 'incoming',
                'provider' => $provider,
                'raw_data' => wp_json_encode($message),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Hook - diğer eklentiler kullanabilir
        do_action('wwa_message_received', $phone, $text, $message, $provider);
    }

    // ========================================
    // Setup Wizard Callbacks
    // ========================================

    /**
     * Kurulum durumunu getir
     */
    public function get_setup_status() {
        $steps = [
            'api_configured' => WWA()->api->is_configured(),
            'admin_phone_set' => !empty(get_option('wwa_admin_phone', '')),
            'templates_configured' => $this->check_templates_configured(),
            'test_message_sent' => get_option('wwa_test_message_sent', false),
            'order_statuses_selected' => !empty(get_option('wwa_order_statuses', []))
        ];

        $completed = array_filter($steps);
        $total = count($steps);
        $done = count($completed);

        // Setup tamamlandı mı kontrol et
        $setup_completed = get_option('wwa_setup_completed', false);
        $setup_dismissed = get_option('wwa_setup_dismissed', false);

        return new WP_REST_Response([
            'steps' => $steps,
            'progress' => [
                'completed' => $done,
                'total' => $total,
                'percentage' => round(($done / $total) * 100)
            ],
            'is_complete' => $done === $total,
            'setup_completed' => $setup_completed || $setup_dismissed,
            'setup_dismissed' => $setup_dismissed,
            'webhook_url' => rest_url('wwa/v1/webhook'),
            'webhook_verify_token' => get_option('wwa_webhook_verify_token', wp_generate_password(32, false))
        ], 200);
    }

    /**
     * Şablonların yapılandırılıp yapılandırılmadığını kontrol et
     */
    private function check_templates_configured() {
        $templates = get_option('wwa_templates', []);
        return !empty($templates) && is_array($templates) && count(array_filter($templates)) > 0;
    }

    /**
     * Kurulumu tamamla
     */
    public function complete_setup($request) {
        $data = $request->get_json_params();

        // API ayarlarını kaydet
        if (isset($data['api_provider'])) {
            update_option('wwa_api_provider', sanitize_text_field($data['api_provider']));
        }

        if (isset($data['api_credentials'])) {
            foreach ($data['api_credentials'] as $key => $value) {
                update_option('wwa_' . sanitize_key($key), sanitize_text_field($value));
            }
        }

        // Admin telefonu kaydet
        if (isset($data['admin_phone'])) {
            update_option('wwa_admin_phone', sanitize_text_field($data['admin_phone']));
        }

        // Sipariş durumlarını kaydet
        if (isset($data['order_statuses'])) {
            update_option('wwa_order_statuses', array_map('sanitize_text_field', $data['order_statuses']));
        }

        // Webhook verify token oluştur
        if (!get_option('wwa_webhook_verify_token')) {
            update_option('wwa_webhook_verify_token', wp_generate_password(32, false));
        }

        // Kurulum tamamlandı işareti
        update_option('wwa_setup_completed', true);
        update_option('wwa_setup_completed_at', current_time('mysql'));

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Kurulum tamamlandı!', 'woo-whatsapp')
        ], 200);
    }

    /**
     * Kurulum sihirbazını atla
     */
    public function skip_setup() {
        update_option('wwa_setup_dismissed', true);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Kurulum sihirbazı atlandı', 'woo-whatsapp')
        ], 200);
    }

    // ========================================
    // Multi-language (i18n) Methods
    // ========================================

    /**
     * Mevcut dilleri getir
     */
    public function get_languages() {
        $i18n = WWA()->i18n;

        return new WP_REST_Response([
            'enabled' => $i18n->is_multilingual(),
            'plugin' => $i18n->get_active_plugin(),
            'languages' => $i18n->get_languages(),
            'current' => $i18n->get_current_language(),
            'default' => $i18n->get_default_language()
        ], 200);
    }

    /**
     * Belirli bir şablonun tüm dil versiyonlarını getir
     */
    public function get_template_languages($request) {
        $status = $request->get_param('status');
        $i18n = WWA()->i18n;

        if (!$i18n->is_multilingual()) {
            // Tek dil modunda varsayılan şablonu döndür
            $templates = get_option('wwa_templates', []);
            return new WP_REST_Response([
                'multilingual' => false,
                'templates' => [
                    [
                        'language' => [
                            'code' => get_locale(),
                            'name' => 'Default',
                            'default' => true
                        ],
                        'template' => $templates[$status] ?? ''
                    ]
                ]
            ], 200);
        }

        return new WP_REST_Response([
            'multilingual' => true,
            'templates' => $i18n->get_all_language_templates($status)
        ], 200);
    }

    /**
     * Belirli bir dil için şablonu getir
     */
    public function get_template_for_language($request) {
        $status = $request->get_param('status');
        $lang = $request->get_param('lang');
        $i18n = WWA()->i18n;

        $template = $i18n->template_for_language($status, $lang);

        return new WP_REST_Response([
            'status' => $status,
            'language' => $lang,
            'template' => $template
        ], 200);
    }

    /**
     * Belirli bir dil için şablonu kaydet
     */
    public function save_template_for_language($request) {
        $status = $request->get_param('status');
        $lang = $request->get_param('lang');
        $params = $request->get_json_params();

        if (!isset($params['template'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Şablon içeriği gerekli', 'woo-whatsapp')
            ], 400);
        }

        $i18n = WWA()->i18n;
        $result = $i18n->template_for_language($status, $lang, $params['template']);

        if ($result) {
            // String'i çeviri sistemine kaydet
            $i18n->register_string(
                $params['template'],
                "template_{$status}_{$lang}",
                'woo-whatsapp-templates'
            );

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Şablon kaydedildi', 'woo-whatsapp')
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => __('Şablon kaydedilemedi', 'woo-whatsapp')
        ], 500);
    }

    // ========================================
    // Reports & Export Methods
    // ========================================

    /**
     * Rapor özeti getir
     */
    public function get_report_summary($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'wwa_message_logs';

        $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = $request->get_param('end_date') ?: date('Y-m-d');

        // Genel istatistikler
        $total_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE DATE(created_at) BETWEEN %s AND %s",
            $start_date, $end_date
        ));

        $sent_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'sent' AND DATE(created_at) BETWEEN %s AND %s",
            $start_date, $end_date
        ));

        $failed_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'failed' AND DATE(created_at) BETWEEN %s AND %s",
            $start_date, $end_date
        ));

        $delivery_rate = $total_messages > 0 ? round(($sent_messages / $total_messages) * 100, 2) : 0;

        // Günlük dağılım
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM $table
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $start_date, $end_date
        ), ARRAY_A);

        // Durum dağılımı
        $status_distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count
            FROM $table
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY status",
            $start_date, $end_date
        ), ARRAY_A);

        // Sipariş durumlarına göre
        $by_order_status = $wpdb->get_results($wpdb->prepare(
            "SELECT status_type, COUNT(*) as count
            FROM $table
            WHERE DATE(created_at) BETWEEN %s AND %s AND status_type IS NOT NULL
            GROUP BY status_type
            ORDER BY count DESC",
            $start_date, $end_date
        ), ARRAY_A);

        // Saatlik dağılım
        $hourly_distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM $table
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC",
            $start_date, $end_date
        ), ARRAY_A);

        // Abandoned Cart istatistikleri
        $cart_table = $wpdb->prefix . 'wwa_abandoned_carts';
        $cart_stats = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$cart_table'") === $cart_table) {
            $cart_stats = [
                'total_abandoned' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $cart_table WHERE DATE(created_at) BETWEEN %s AND %s",
                    $start_date, $end_date
                )),
                'recovered' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $cart_table WHERE status = 'recovered' AND DATE(created_at) BETWEEN %s AND %s",
                    $start_date, $end_date
                )),
                'total_value' => $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(cart_total) FROM $cart_table WHERE DATE(created_at) BETWEEN %s AND %s",
                    $start_date, $end_date
                )) ?: 0,
                'recovered_value' => $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(cart_total) FROM $cart_table WHERE status = 'recovered' AND DATE(created_at) BETWEEN %s AND %s",
                    $start_date, $end_date
                )) ?: 0
            ];
            $cart_stats['recovery_rate'] = $cart_stats['total_abandoned'] > 0
                ? round(($cart_stats['recovered'] / $cart_stats['total_abandoned']) * 100, 2)
                : 0;
        }

        // Waitlist istatistikleri
        $waitlist_table = $wpdb->prefix . 'wwa_waitlist';
        $waitlist_stats = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$waitlist_table'") === $waitlist_table) {
            $waitlist_stats = [
                'total_subscribers' => $wpdb->get_var("SELECT COUNT(*) FROM $waitlist_table"),
                'notified' => $wpdb->get_var("SELECT COUNT(*) FROM $waitlist_table WHERE status = 'notified'"),
                'converted' => $wpdb->get_var("SELECT COUNT(*) FROM $waitlist_table WHERE status = 'converted'")
            ];
            $waitlist_stats['conversion_rate'] = $waitlist_stats['notified'] > 0
                ? round(($waitlist_stats['converted'] / $waitlist_stats['notified']) * 100, 2)
                : 0;
        }

        return new WP_REST_Response([
            'period' => [
                'start' => $start_date,
                'end' => $end_date
            ],
            'summary' => [
                'total_messages' => (int) $total_messages,
                'sent_messages' => (int) $sent_messages,
                'failed_messages' => (int) $failed_messages,
                'delivery_rate' => $delivery_rate
            ],
            'daily_stats' => $daily_stats,
            'status_distribution' => $status_distribution,
            'by_order_status' => $by_order_status,
            'hourly_distribution' => $hourly_distribution,
            'abandoned_cart' => $cart_stats,
            'waitlist' => $waitlist_stats
        ], 200);
    }

    /**
     * Mesaj raporu getir
     */
    public function get_messages_report($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'wwa_message_logs';

        $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = $request->get_param('end_date') ?: date('Y-m-d');
        $status = $request->get_param('status');
        $page = max(1, (int) $request->get_param('page') ?: 1);
        $per_page = min(100, max(10, (int) $request->get_param('per_page') ?: 50));

        $where = $wpdb->prepare("DATE(created_at) BETWEEN %s AND %s", $start_date, $end_date);
        if ($status) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
        $offset = ($page - 1) * $per_page;

        $messages = $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT $offset, $per_page",
            ARRAY_A
        );

        // Sipariş bilgilerini ekle
        foreach ($messages as &$msg) {
            if ($msg['order_id']) {
                $order = wc_get_order($msg['order_id']);
                if ($order) {
                    $msg['order_number'] = $order->get_order_number();
                    $msg['order_total'] = $order->get_total();
                    $msg['customer_name'] = $order->get_formatted_billing_full_name();
                }
            }
        }

        return new WP_REST_Response([
            'messages' => $messages,
            'pagination' => [
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page)
            ]
        ], 200);
    }

    /**
     * CSV olarak dışa aktar
     */
    public function export_csv($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'wwa_message_logs';

        $type = $request->get_param('type') ?: 'messages';
        $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = $request->get_param('end_date') ?: date('Y-m-d');

        $csv_data = [];
        $filename = '';

        switch ($type) {
            case 'messages':
                $filename = 'wwa-messages-' . date('Y-m-d') . '.csv';
                $csv_data[] = ['ID', 'Sipariş No', 'Telefon', 'Durum', 'Mesaj Türü', 'Tarih', 'Gönderim Tarihi'];

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table WHERE DATE(created_at) BETWEEN %s AND %s ORDER BY created_at DESC",
                    $start_date, $end_date
                ), ARRAY_A);

                foreach ($results as $row) {
                    $csv_data[] = [
                        $row['id'],
                        $row['order_id'] ?: '-',
                        $row['phone'],
                        $row['status'],
                        $row['status_type'] ?: '-',
                        $row['created_at'],
                        $row['sent_at'] ?: '-'
                    ];
                }
                break;

            case 'abandoned_carts':
                $cart_table = $wpdb->prefix . 'wwa_abandoned_carts';
                $filename = 'wwa-abandoned-carts-' . date('Y-m-d') . '.csv';
                $csv_data[] = ['ID', 'E-posta', 'Telefon', 'Sepet Tutarı', 'Durum', 'Hatırlatma Sayısı', 'Oluşturma Tarihi'];

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $cart_table WHERE DATE(created_at) BETWEEN %s AND %s ORDER BY created_at DESC",
                    $start_date, $end_date
                ), ARRAY_A);

                foreach ($results as $row) {
                    $csv_data[] = [
                        $row['id'],
                        $row['email'] ?: '-',
                        $row['phone'] ?: '-',
                        number_format($row['cart_total'], 2),
                        $row['status'],
                        $row['reminder_count'],
                        $row['created_at']
                    ];
                }
                break;

            case 'waitlist':
                $waitlist_table = $wpdb->prefix . 'wwa_waitlist';
                $filename = 'wwa-waitlist-' . date('Y-m-d') . '.csv';
                $csv_data[] = ['ID', 'Ürün ID', 'E-posta', 'Telefon', 'Durum', 'Kayıt Tarihi', 'Bildirim Tarihi'];

                $results = $wpdb->get_results(
                    "SELECT * FROM $waitlist_table ORDER BY created_at DESC",
                    ARRAY_A
                );

                foreach ($results as $row) {
                    $csv_data[] = [
                        $row['id'],
                        $row['product_id'],
                        $row['email'] ?: '-',
                        $row['phone'] ?: '-',
                        $row['status'],
                        $row['created_at'],
                        $row['notified_at'] ?: '-'
                    ];
                }
                break;
        }

        // CSV string oluştur
        $csv_string = '';
        foreach ($csv_data as $row) {
            $csv_string .= implode(',', array_map(function($cell) {
                return '"' . str_replace('"', '""', $cell) . '"';
            }, $row)) . "\n";
        }

        return new WP_REST_Response([
            'success' => true,
            'filename' => $filename,
            'content' => $csv_string,
            'mime_type' => 'text/csv'
        ], 200);
    }

    /**
     * PDF olarak dışa aktar
     */
    public function export_pdf($request) {
        $type = $request->get_param('type') ?: 'summary';
        $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = $request->get_param('end_date') ?: date('Y-m-d');

        // Rapor verilerini al
        $report_request = new WP_REST_Request('GET', '/wwa/v1/reports/summary');
        $report_request->set_param('start_date', $start_date);
        $report_request->set_param('end_date', $end_date);
        $report_data = $this->get_report_summary($report_request)->get_data();

        // HTML rapor oluştur
        $html = $this->generate_pdf_html($report_data, $start_date, $end_date);

        return new WP_REST_Response([
            'success' => true,
            'filename' => 'wwa-report-' . date('Y-m-d') . '.html',
            'content' => $html,
            'mime_type' => 'text/html',
            'note' => __('HTML rapor oluşturuldu. Tarayıcınızdan PDF olarak kaydedebilirsiniz.', 'woo-whatsapp')
        ], 200);
    }

    /**
     * PDF için HTML oluştur
     */
    private function generate_pdf_html($data, $start_date, $end_date) {
        $site_name = get_bloginfo('name');
        $currency = get_woocommerce_currency_symbol();

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Woo WhatsApp Pro - Rapor</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
        h1 { color: #25D366; border-bottom: 3px solid #25D366; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #25D366; }
        .period { color: #666; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 30px 0; }
        .stat-card { background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: bold; color: #25D366; }
        .stat-label { color: #666; font-size: 14px; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .success { color: #28a745; }
        .danger { color: #dc3545; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 12px; }
        @media print { body { padding: 20px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🟢 Woo WhatsApp Pro</div>
        <div class="period">' . esc_html($site_name) . '<br>' . esc_html($start_date) . ' - ' . esc_html($end_date) . '</div>
    </div>

    <h1>WhatsApp Mesaj Raporu</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value">' . number_format($data['summary']['total_messages']) . '</div>
            <div class="stat-label">Toplam Mesaj</div>
        </div>
        <div class="stat-card">
            <div class="stat-value success">' . number_format($data['summary']['sent_messages']) . '</div>
            <div class="stat-label">Gönderilen</div>
        </div>
        <div class="stat-card">
            <div class="stat-value danger">' . number_format($data['summary']['failed_messages']) . '</div>
            <div class="stat-label">Başarısız</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">%' . $data['summary']['delivery_rate'] . '</div>
            <div class="stat-label">Başarı Oranı</div>
        </div>
    </div>';

        // Abandoned Cart istatistikleri
        if (!empty($data['abandoned_cart'])) {
            $cart = $data['abandoned_cart'];
            $html .= '
    <h2>Terk Edilmiş Sepet</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value">' . number_format($cart['total_abandoned']) . '</div>
            <div class="stat-label">Toplam Terk</div>
        </div>
        <div class="stat-card">
            <div class="stat-value success">' . number_format($cart['recovered']) . '</div>
            <div class="stat-label">Kurtarılan</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">' . $currency . number_format($cart['recovered_value'], 2) . '</div>
            <div class="stat-label">Kurtarılan Değer</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">%' . $cart['recovery_rate'] . '</div>
            <div class="stat-label">Kurtarma Oranı</div>
        </div>
    </div>';
        }

        // Waitlist istatistikleri
        if (!empty($data['waitlist'])) {
            $wl = $data['waitlist'];
            $html .= '
    <h2>Stok Bildirimi (Waitlist)</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value">' . number_format($wl['total_subscribers']) . '</div>
            <div class="stat-label">Toplam Abone</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">' . number_format($wl['notified']) . '</div>
            <div class="stat-label">Bildirildi</div>
        </div>
        <div class="stat-card">
            <div class="stat-value success">' . number_format($wl['converted']) . '</div>
            <div class="stat-label">Satın Aldı</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">%' . $wl['conversion_rate'] . '</div>
            <div class="stat-label">Dönüşüm Oranı</div>
        </div>
    </div>';
        }

        // Günlük istatistikler tablosu
        if (!empty($data['daily_stats'])) {
            $html .= '
    <h2>Günlük İstatistikler</h2>
    <table>
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Toplam</th>
                <th>Gönderilen</th>
                <th>Başarısız</th>
            </tr>
        </thead>
        <tbody>';
            foreach ($data['daily_stats'] as $day) {
                $html .= '
            <tr>
                <td>' . esc_html($day['date']) . '</td>
                <td>' . number_format($day['total']) . '</td>
                <td class="success">' . number_format($day['sent']) . '</td>
                <td class="danger">' . number_format($day['failed']) . '</td>
            </tr>';
            }
            $html .= '
        </tbody>
    </table>';
        }

        $html .= '
    <div class="footer">
        <p>Bu rapor Woo WhatsApp Pro tarafından oluşturulmuştur.</p>
        <p>Oluşturulma Tarihi: ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</p>
    </div>
</body>
</html>';

        return $html;
    }

    // ========================================
    // License System Methods
    // ========================================

    /**
     * Lisans durumunu getir
     * Lifetime (Ömür Boyu) lisans modeli - CodeCanyon satışları için
     */
    public function get_license_status() {
        $license_key = get_option('wwa_license_key', '');
        $license_status = get_option('wwa_license_status', 'inactive');
        $license_type = get_option('wwa_license_type', 'lifetime');
        $activated_at = get_option('wwa_license_activated_at', '');

        $is_active = $license_status === 'active';
        $pro_features = [
            'flow_builder' => $is_active,
            'abandoned_cart' => $is_active,
            'chatbot' => $is_active,
            'waitlist' => $is_active,
            'analytics' => $is_active,
            'multi_language' => $is_active
        ];

        return new WP_REST_Response([
            'license_key' => $license_key ? substr($license_key, 0, 8) . str_repeat('*', 24) : '',
            'status' => $license_status,
            'type' => $license_type,
            'activated_at' => $activated_at,
            'is_valid' => $is_active,
            'is_lifetime' => true,
            'license_model' => 'lifetime',
            'pro_features' => $pro_features,
            'site_url' => home_url()
        ], 200);
    }

    /**
     * Lisans aktivasyonu
     * Lifetime (Ömür Boyu) lisans modeli - CodeCanyon satışları için
     */
    public function activate_license($request) {
        $params = $request->get_json_params();
        $license_key = sanitize_text_field($params['license_key'] ?? '');

        if (empty($license_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Lisans anahtarı gerekli', 'woo-whatsapp')
            ], 400);
        }

        $response = $this->verify_license_with_server($license_key, 'activate');

        if ($response['success']) {
            update_option('wwa_license_key', $license_key);
            update_option('wwa_license_status', 'active');
            update_option('wwa_license_type', $response['type'] ?? 'lifetime');
            update_option('wwa_license_activated_at', current_time('mysql'));

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Lisans başarıyla aktifleştirildi! Ömür boyu erişiminiz başladı.', 'woo-whatsapp'),
                'license' => [
                    'status' => 'active',
                    'type' => $response['type'] ?? 'lifetime',
                    'is_lifetime' => true
                ]
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $response['message'] ?? __('Lisans doğrulanamadı', 'woo-whatsapp')
        ], 400);
    }

    /**
     * Lisans deaktivasyonu
     */
    public function deactivate_license() {
        $license_key = get_option('wwa_license_key', '');

        if (empty($license_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Aktif lisans bulunamadı', 'woo-whatsapp')
            ], 400);
        }

        $this->verify_license_with_server($license_key, 'deactivate');

        delete_option('wwa_license_key');
        update_option('wwa_license_status', 'inactive');
        delete_option('wwa_license_type');
        delete_option('wwa_license_activated_at');

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Lisans deaktif edildi', 'woo-whatsapp')
        ], 200);
    }

    /**
     * Lisans doğrulama
     */
    public function verify_license($request) {
        $license_key = get_option('wwa_license_key', '');

        if (empty($license_key)) {
            return new WP_REST_Response([
                'success' => false,
                'valid' => false,
                'message' => __('Lisans bulunamadı', 'woo-whatsapp')
            ], 200);
        }

        $response = $this->verify_license_with_server($license_key, 'verify');

        if ($response['success']) {
            update_option('wwa_license_status', 'active');

            return new WP_REST_Response([
                'success' => true,
                'valid' => true,
                'message' => __('Lisans geçerli', 'woo-whatsapp'),
                'is_lifetime' => true
            ], 200);
        }

        update_option('wwa_license_status', 'invalid');

        return new WP_REST_Response([
            'success' => false,
            'valid' => false,
            'message' => $response['message'] ?? __('Lisans geçersiz', 'woo-whatsapp')
        ], 200);
    }

    /**
     * Lisans sunucusuyla iletişim
     */
    private function verify_license_with_server($license_key, $action = 'verify') {
        // Envato Personal Token - WordPress ayarlarından al veya sabit tanımla
        // CodeCanyon'dan satış yapıyorsanız https://build.envato.com/create-token/ adresinden token oluşturun
        // Gerekli izinler: "View and search Envato sites" ve "View the user's items' sales history"
        $envato_token = defined('WWA_ENVATO_TOKEN') ? WWA_ENVATO_TOKEN : get_option('wwa_envato_token', '');

        // Demo mod: Token yoksa demo lisansları kullan (geliştirme/test için)
        if (empty($envato_token)) {
            return $this->verify_demo_license($license_key);
        }

        // Envato Purchase Code formatını kontrol et (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $license_key)) {
            return [
                'success' => false,
                'message' => __('Geçersiz Envato Purchase Code formatı', 'woo-whatsapp')
            ];
        }

        // Envato API ile doğrula
        return $this->verify_envato_purchase($license_key, $envato_token, $action);
    }

    /**
     * Demo lisans doğrulaması (geliştirme/test modu)
     * Lifetime (Ömür Boyu) lisans modeli
     */
    private function verify_demo_license($license_key) {
        $demo_licenses = [
            'DEMO-1234-5678-DEMO' => ['type' => 'lifetime'],
            'PRO1-2345-6789-ABCD' => ['type' => 'lifetime'],
            'AGEN-CY12-3456-7890' => ['type' => 'lifetime']
        ];

        $license_upper = strtoupper($license_key);
        if (isset($demo_licenses[$license_upper])) {
            return [
                'success' => true,
                'type' => $demo_licenses[$license_upper]['type'],
                'is_lifetime' => true,
                'demo_mode' => true
            ];
        }

        return [
            'success' => false,
            'message' => __('Geçersiz lisans anahtarı. Envato token yapılandırılmamış, demo modunda çalışıyor.', 'woo-whatsapp')
        ];
    }

    /**
     * Envato API ile purchase code doğrulaması
     */
    private function verify_envato_purchase($purchase_code, $token, $action = 'verify') {
        // Önce cache'i kontrol et (API rate limit'e takılmamak için)
        $cache_key = 'wwa_envato_' . md5($purchase_code);
        $cached = get_transient($cache_key);

        if ($cached !== false && $action === 'verify') {
            return $cached;
        }

        // Envato API'sine istek at
        $response = wp_remote_get(
            'https://api.envato.com/v3/market/author/sale?code=' . $purchase_code,
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent' => 'WooWhatsApp Pro/' . WWA_VERSION
                ]
            ]
        );

        if (is_wp_error($response)) {
            // API hatası - cache'deki eski veriyi kullan veya hata döndür
            if ($cached !== false) {
                return $cached;
            }
            return [
                'success' => false,
                'message' => __('Envato API\'sine ulaşılamadı. Lütfen daha sonra tekrar deneyin.', 'woo-whatsapp')
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        // HTTP durum kodlarını kontrol et
        if ($status_code === 404) {
            return [
                'success' => false,
                'message' => __('Geçersiz purchase code. Bu kod bulunamadı.', 'woo-whatsapp')
            ];
        }

        if ($status_code === 403) {
            return [
                'success' => false,
                'message' => __('Envato API erişim hatası. Token geçersiz veya yetersiz izinlere sahip.', 'woo-whatsapp')
            ];
        }

        if ($status_code !== 200 || empty($body)) {
            return [
                'success' => false,
                'message' => __('Envato API\'sinden geçersiz yanıt alındı.', 'woo-whatsapp')
            ];
        }

        // Ürün ID'sini kontrol et (CodeCanyon'daki ürün ID'nizi buraya yazın)
        // Bu ID'yi CodeCanyon'da ürününüzün URL'sinden alabilirsiniz
        $your_item_id = defined('WWA_ENVATO_ITEM_ID') ? WWA_ENVATO_ITEM_ID : get_option('wwa_envato_item_id', '');

        if (!empty($your_item_id) && isset($body['item']['id'])) {
            if ((string)$body['item']['id'] !== (string)$your_item_id) {
                return [
                    'success' => false,
                    'message' => __('Bu purchase code farklı bir ürüne ait.', 'woo-whatsapp')
                ];
            }
        }

        // Satış bilgilerini al
        $sold_at = isset($body['sold_at']) ? $body['sold_at'] : '';
        $license_type = isset($body['license']) ? $body['license'] : 'Regular License';
        $support_until = isset($body['supported_until']) ? $body['supported_until'] : '';
        $buyer = isset($body['buyer']) ? $body['buyer'] : '';

        $plugin_license_type = 'lifetime';
        if (stripos($license_type, 'Extended') !== false) {
            $plugin_license_type = 'lifetime_extended';
        }

        if ($action === 'activate') {
            $this->record_site_activation($purchase_code, home_url(), $buyer);
        } elseif ($action === 'deactivate') {
            $this->remove_site_activation($purchase_code, home_url());
        }

        $result = [
            'success' => true,
            'type' => $plugin_license_type,
            'is_lifetime' => true,
            'envato_license' => $license_type,
            'buyer' => $buyer,
            'sold_at' => $sold_at,
            'support_until' => $support_until
        ];

        // Sonucu 1 saat cache'le
        set_transient($cache_key, $result, HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Site aktivasyonunu kaydet (opsiyonel - çoklu site takibi için)
     */
    private function record_site_activation($purchase_code, $site_url, $buyer) {
        $activations = get_option('wwa_license_activations', []);
        $code_hash = md5($purchase_code);

        if (!isset($activations[$code_hash])) {
            $activations[$code_hash] = [];
        }

        $activations[$code_hash][$site_url] = [
            'activated_at' => current_time('mysql'),
            'buyer' => $buyer
        ];

        update_option('wwa_license_activations', $activations);
    }

    /**
     * Site aktivasyonunu kaldır
     */
    private function remove_site_activation($purchase_code, $site_url) {
        $activations = get_option('wwa_license_activations', []);
        $code_hash = md5($purchase_code);

        if (isset($activations[$code_hash][$site_url])) {
            unset($activations[$code_hash][$site_url]);
            update_option('wwa_license_activations', $activations);
        }
    }
}
