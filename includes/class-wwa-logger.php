<?php
/**
 * Logger Sınıfı - Mesaj loglarını yönetir
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_Logger {

    /**
     * Tablo adı
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wwa_message_logs';

        // Tablo yoksa oluştur
        $this->maybe_create_table();

        // Günlük temizlik zamanlaması
        if (!wp_next_scheduled('wwa_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'wwa_cleanup_logs');
        }
        add_action('wwa_cleanup_logs', [$this, 'cleanup_old_logs']);
    }

    /**
     * Tablo yoksa oluştur
     */
    private function maybe_create_table() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") !== $this->table_name) {
            $this->create_table();
        }
    }

    /**
     * Tabloyu oluştur
     */
    public static function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
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
    }

    /**
     * Yeni log kaydı oluştur
     *
     * @param array $data Log verileri
     * @return int|false Eklenen kayıt ID'si veya false
     */
    public function add($data) {
        global $wpdb;

        $defaults = [
            'order_id' => null,
            'phone' => '',
            'message' => '',
            'status' => 'pending',
            'status_type' => null,
            'api_response' => null,
            'message_id' => null,
            'recipient_type' => 'admin',
            'created_at' => current_time('mysql'),
            'sent_at' => null
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $this->table_name,
            [
                'order_id' => $data['order_id'],
                'phone' => sanitize_text_field($data['phone']),
                'message' => sanitize_textarea_field($data['message']),
                'status' => sanitize_text_field($data['status']),
                'status_type' => sanitize_text_field($data['status_type']),
                'api_response' => $data['api_response'] ? wp_json_encode($data['api_response']) : null,
                'message_id' => sanitize_text_field($data['message_id']),
                'recipient_type' => sanitize_text_field($data['recipient_type']),
                'created_at' => $data['created_at'],
                'sent_at' => $data['sent_at']
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            $this->debug_log('Log ekleme hatası: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Log durumunu güncelle
     *
     * @param int $log_id Log ID
     * @param string $status Yeni durum
     * @param array $extra_data Ekstra veriler
     * @return bool
     */
    public function update_status($log_id, $status, $extra_data = []) {
        global $wpdb;

        $update_data = ['status' => sanitize_text_field($status)];
        $format = ['%s'];

        if ($status === 'sent') {
            $update_data['sent_at'] = current_time('mysql');
            $format[] = '%s';
        }

        if (!empty($extra_data['api_response'])) {
            $update_data['api_response'] = wp_json_encode($extra_data['api_response']);
            $format[] = '%s';
        }

        if (!empty($extra_data['message_id'])) {
            $update_data['message_id'] = sanitize_text_field($extra_data['message_id']);
            $format[] = '%s';
        }

        return $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $log_id],
            $format,
            ['%d']
        ) !== false;
    }

    /**
     * Logları getir
     *
     * @param array $args Sorgu parametreleri
     * @return array
     */
    public function get_logs($args = []) {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'order_id' => null,
            'status' => null,
            'recipient_type' => null,
            'date_from' => null,
            'date_to' => null,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['order_id']) {
            $where[] = 'order_id = %d';
            $values[] = $args['order_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['recipient_type']) {
            $where[] = 'recipient_type = %s';
            $values[] = $args['recipient_type'];
        }

        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'created_at DESC';
        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Toplam log sayısı
     *
     * @param array $args Filtre parametreleri
     * @return int
     */
    public function get_total_count($args = []) {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['recipient_type'])) {
            $where[] = 'recipient_type = %s';
            $values[] = $args['recipient_type'];
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * İstatistikleri getir
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $stats = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
            'today' => 0,
            'this_week' => 0,
            'this_month' => 0
        ];

        // Toplam ve duruma göre
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status",
            ARRAY_A
        );

        foreach ($status_counts as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        // Bugün
        $stats['today'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(created_at) = %s",
            current_time('Y-m-d')
        ));

        // Bu hafta
        $stats['this_week'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
            date('Y-m-d', strtotime('-7 days'))
        ));

        // Bu ay
        $stats['this_month'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
            date('Y-m-01')
        ));

        return $stats;
    }

    /**
     * Eski logları temizle
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $retention_days = (int) get_option('wwa_log_retention_days', 30);

        if ($retention_days <= 0) {
            return;
        }

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $date_threshold
        ));

        $this->debug_log("Eski loglar temizlendi (>{$retention_days} gün)");
    }

    /**
     * Tek log kaydı getir
     *
     * @param int $log_id Log ID
     * @return array|null
     */
    public function get_log($log_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $log_id
        ), ARRAY_A);
    }

    /**
     * Log sil
     *
     * @param int $log_id Log ID
     * @return bool
     */
    public function delete($log_id) {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            ['id' => $log_id],
            ['%d']
        ) !== false;
    }

    /**
     * Tüm logları temizle
     *
     * @return bool
     */
    public function clear_all() {
        global $wpdb;

        return $wpdb->query("TRUNCATE TABLE {$this->table_name}") !== false;
    }

    /**
     * Debug log
     *
     * @param string $message Mesaj
     * @param mixed $data Ekstra veri
     */
    public function debug_log($message, $data = null) {
        if (get_option('wwa_debug_mode') !== 'yes') {
            return;
        }

        $log_message = '[WWA Debug] ' . $message;

        if ($data !== null) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }

        error_log($log_message);
    }
}
