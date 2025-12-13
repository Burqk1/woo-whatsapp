<?php
/**
 * Woo WhatsApp Uninstall
 *
 * Plugin silindiğinde çalışır ve tüm verileri temizler.
 *
 * @package Woo_WhatsApp
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (is_multisite()) {
    global $wpdb;

    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        wwa_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    wwa_uninstall_cleanup();
}

/**
 * Temizlik işlemleri
 */
function wwa_uninstall_cleanup() {
    global $wpdb;

    $options = [
        'wwa_version',
        'wwa_enabled',
        'wwa_api_provider',
        'wwa_admin_phone',
        'wwa_api_token',
        'wwa_phone_number_id',
        'wwa_business_account_id',
        'wwa_twilio_account_sid',
        'wwa_twilio_auth_token',
        'wwa_twilio_phone_number',
        'wwa_ultramsg_instance_id',
        'wwa_ultramsg_token',
        'wwa_wati_api_url',
        'wwa_wati_api_token',
        'wwa_send_to_customer',
        'wwa_send_to_admin',
        'wwa_order_statuses',
        'wwa_templates',
        'wwa_debug_mode',
        'wwa_log_retention_days',
        'wwa_setup_completed',
        'wwa_setup_completed_at',
        'wwa_setup_dismissed',
        'wwa_license_key',
        'wwa_license_status',
        'wwa_license_type',
        'wwa_license_activated_at',
        'wwa_license_activations',
        'wwa_webhook_verify_token',
        'wwa_abandoned_cart_enabled',
        'wwa_abandoned_cart_timeout',
        'wwa_abandoned_cart_template',
        'wwa_chatbot_enabled',
        'wwa_chatbot_default_response',
        'wwa_custom_languages'
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    $tables = [
        $wpdb->prefix . 'wwa_message_logs',
        $wpdb->prefix . 'wwa_abandoned_carts',
        $wpdb->prefix . 'wwa_waitlist',
        $wpdb->prefix . 'wwa_chatbot_rules',
        $wpdb->prefix . 'wwa_chatbot_conversations',
        $wpdb->prefix . 'wwa_flows',
        $wpdb->prefix . 'wwa_scheduled_messages'
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wwa_%'");

    if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
        if (Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $wpdb->query("DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE '_wwa_%'");
        }
    }

    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wwa_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wwa_%'");

    wp_clear_scheduled_hook('wwa_cleanup_logs');

    wp_cache_flush();
}
