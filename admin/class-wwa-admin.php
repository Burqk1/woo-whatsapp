<?php
/**
 * Admin Panel Sınıfı
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_filter('plugin_action_links_' . WWA_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        add_filter('admin_body_class', [$this, 'admin_body_class']);
    }

    /**
     * Admin body class ekle - fullpage layout için
     */
    public function admin_body_class($classes) {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'woo-whatsapp') !== false) {
            $classes .= ' wwa-fullpage-admin';
        }
        return $classes;
    }

    /**
     * Admin menüsü ekle
     */
    public function add_menu() {
        add_menu_page(
            __('WhatsApp Bildirimleri', 'woo-whatsapp'),
            __('WhatsApp', 'woo-whatsapp'),
            'manage_options',
            'woo-whatsapp',
            [$this, 'render_app'],
            'dashicons-whatsapp',
            56
        );

        add_submenu_page(
            'woo-whatsapp',
            __('Dashboard', 'woo-whatsapp'),
            __('Dashboard', 'woo-whatsapp'),
            'manage_options',
            'woo-whatsapp',
            [$this, 'render_app']
        );

        add_submenu_page(
            'woo-whatsapp',
            __('Ayarlar', 'woo-whatsapp'),
            __('Ayarlar', 'woo-whatsapp'),
            'manage_options',
            'woo-whatsapp#/settings',
            [$this, 'render_app']
        );

        add_submenu_page(
            'woo-whatsapp',
            __('Şablonlar', 'woo-whatsapp'),
            __('Şablonlar', 'woo-whatsapp'),
            'manage_options',
            'woo-whatsapp#/templates',
            [$this, 'render_app']
        );

        add_submenu_page(
            'woo-whatsapp',
            __('Mesaj Logları', 'woo-whatsapp'),
            __('Loglar', 'woo-whatsapp'),
            'manage_options',
            'woo-whatsapp#/logs',
            [$this, 'render_app']
        );
    }

    /**
     * React uygulamasını render et
     */
    public function render_app() {
        echo '<div class="wrap wwa-wrap"><div id="wwa-react-root" class="wwa-admin-wrap"></div></div>';
    }

    /**
     * Admin scriptlerini yükle
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'woo-whatsapp') === false && $hook !== 'toplevel_page_woo-whatsapp') {
            return;
        }

        $asset_file = WWA_PLUGIN_DIR . 'build/index.asset.php';

        if (file_exists($asset_file)) {
            $asset = include $asset_file;

            wp_enqueue_script(
                'wwa-admin-script',
                WWA_PLUGIN_URL . 'build/index.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );

            if (file_exists(WWA_PLUGIN_DIR . 'build/index.css')) {
                wp_enqueue_style(
                    'wwa-admin-style',
                    WWA_PLUGIN_URL . 'build/index.css',
                    ['wp-components'],
                    $asset['version']
                );
            }
        } else {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php esc_html_e('Woo WhatsApp:', 'woo-whatsapp'); ?></strong>
                        <?php esc_html_e('Build dosyaları bulunamadı. Lütfen "npm install && npm run build" komutunu çalıştırın.', 'woo-whatsapp'); ?>
                    </p>
                </div>
                <?php
            });
            return;
        }

        wp_enqueue_style('wp-components');

        wp_localize_script('wwa-admin-script', 'wwaSettings', [
            'apiUrl' => esc_url_raw(rest_url('wwa/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'pluginUrl' => WWA_PLUGIN_URL,
            'version' => WWA_VERSION,
            'isConfigured' => WWA()->api->is_configured(),
            'debugMode' => get_option('wwa_debug_mode') === 'yes',
            'i18n' => $this->get_i18n_strings()
        ]);

        wp_add_inline_style('wwa-admin-style', $this->get_admin_css());
    }

    /**
     * i18n string'leri
     */
    private function get_i18n_strings() {
        return [
            'dashboard' => __('Dashboard', 'woo-whatsapp'),
            'settings' => __('Ayarlar', 'woo-whatsapp'),
            'templates' => __('Şablonlar', 'woo-whatsapp'),
            'logs' => __('Mesaj Logları', 'woo-whatsapp'),
            'save' => __('Kaydet', 'woo-whatsapp'),
            'saving' => __('Kaydediliyor...', 'woo-whatsapp'),
            'saved' => __('Kaydedildi!', 'woo-whatsapp'),
            'error' => __('Hata!', 'woo-whatsapp'),
            'testMessage' => __('Test Mesajı Gönder', 'woo-whatsapp'),
            'testConnection' => __('Bağlantıyı Test Et', 'woo-whatsapp'),
            'sending' => __('Gönderiliyor...', 'woo-whatsapp'),
            'sent' => __('Gönderildi!', 'woo-whatsapp'),
            'failed' => __('Başarısız', 'woo-whatsapp'),
            'pending' => __('Bekliyor', 'woo-whatsapp'),
            'today' => __('Bugün', 'woo-whatsapp'),
            'thisWeek' => __('Bu Hafta', 'woo-whatsapp'),
            'thisMonth' => __('Bu Ay', 'woo-whatsapp'),
            'total' => __('Toplam', 'woo-whatsapp'),
            'noData' => __('Veri bulunamadı', 'woo-whatsapp'),
            'confirmClearLogs' => __('Tüm logları silmek istediğinize emin misiniz?', 'woo-whatsapp'),
            'preview' => __('Önizleme', 'woo-whatsapp'),
            'resend' => __('Yeniden Gönder', 'woo-whatsapp'),
            'delete' => __('Sil', 'woo-whatsapp'),
            'customer' => __('Müşteri', 'woo-whatsapp'),
            'admin' => __('Admin', 'woo-whatsapp'),
            'general' => __('Genel', 'woo-whatsapp'),
            'apiSettings' => __('API Ayarları', 'woo-whatsapp'),
            'notifications' => __('Bildirimler', 'woo-whatsapp'),
            'advanced' => __('Gelişmiş', 'woo-whatsapp'),
            'enabled' => __('Etkin', 'woo-whatsapp'),
            'disabled' => __('Devre Dışı', 'woo-whatsapp'),
            'selectProvider' => __('API Sağlayıcısı Seçin', 'woo-whatsapp'),
            'adminPhone' => __('Yönetici Telefonu', 'woo-whatsapp'),
            'sendToCustomer' => __('Müşteriye Bildirim Gönder', 'woo-whatsapp'),
            'sendToAdmin' => __('Yöneticiye Bildirim Gönder', 'woo-whatsapp'),
            'orderStatuses' => __('Sipariş Durumları', 'woo-whatsapp'),
            'debugMode' => __('Debug Modu', 'woo-whatsapp'),
            'logRetention' => __('Log Saklama Süresi (Gün)', 'woo-whatsapp'),
            'availablePlaceholders' => __('Kullanılabilir Değişkenler', 'woo-whatsapp'),
            'exportSettings' => __('Ayarları Dışa Aktar', 'woo-whatsapp'),
            'importSettings' => __('Ayarları İçe Aktar', 'woo-whatsapp'),
            'apiConfigured' => __('API yapılandırıldı', 'woo-whatsapp'),
            'apiNotConfigured' => __('API yapılandırılmadı', 'woo-whatsapp')
        ];
    }

    /**
     * Admin CSS
     */
    private function get_admin_css() {
        return '
            .wwa-admin-wrap {
                margin: 20px 20px 20px 0;
            }
            #wwa-react-root {
                max-width: 1200px;
            }
            .wwa-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
            }
            .wwa-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }
            .wwa-stat-card {
                background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
                color: #fff;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            }
            .wwa-stat-card.secondary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .wwa-stat-card.warning {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }
            .wwa-stat-number {
                font-size: 36px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .wwa-stat-label {
                font-size: 14px;
                opacity: 0.9;
            }
            .wwa-nav-tabs {
                display: flex;
                gap: 0;
                border-bottom: 1px solid #c3c4c7;
                margin-bottom: 20px;
            }
            .wwa-nav-tab {
                padding: 12px 20px;
                cursor: pointer;
                border: none;
                background: none;
                font-size: 14px;
                color: #50575e;
                border-bottom: 2px solid transparent;
                margin-bottom: -1px;
            }
            .wwa-nav-tab:hover {
                color: #135e96;
            }
            .wwa-nav-tab.active {
                color: #1d2327;
                border-bottom-color: #2271b1;
            }
            .wwa-template-editor {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            .wwa-template-editor textarea {
                min-height: 200px;
                font-family: monospace;
            }
            .wwa-placeholder-list {
                background: #f0f0f1;
                padding: 15px;
                border-radius: 4px;
                max-height: 300px;
                overflow-y: auto;
            }
            .wwa-placeholder-item {
                padding: 5px 10px;
                cursor: pointer;
                border-radius: 3px;
                font-family: monospace;
                font-size: 12px;
            }
            .wwa-placeholder-item:hover {
                background: #fff;
            }
            .wwa-log-table {
                width: 100%;
                border-collapse: collapse;
            }
            .wwa-log-table th,
            .wwa-log-table td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #c3c4c7;
            }
            .wwa-status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .wwa-status-badge.sent {
                background: #d4edda;
                color: #155724;
            }
            .wwa-status-badge.failed {
                background: #f8d7da;
                color: #721c24;
            }
            .wwa-status-badge.pending {
                background: #fff3cd;
                color: #856404;
            }
            .wwa-form-group {
                margin-bottom: 20px;
            }
            .wwa-form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .wwa-form-group input[type="text"],
            .wwa-form-group input[type="password"],
            .wwa-form-group input[type="number"],
            .wwa-form-group select,
            .wwa-form-group textarea {
                width: 100%;
                max-width: 400px;
            }
            .wwa-form-help {
                color: #757575;
                font-size: 12px;
                margin-top: 5px;
            }
            .wwa-alert {
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .wwa-alert.success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }
            .wwa-alert.error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
            .wwa-alert.warning {
                background: #fff3cd;
                border: 1px solid #ffeeba;
                color: #856404;
            }
            .wwa-alert.info {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                color: #0c5460;
            }
            @media (max-width: 782px) {
                .wwa-template-editor {
                    grid-template-columns: 1fr;
                }
                .wwa-stats-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }
        ';
    }

    /**
     * Admin uyarıları
     */
    public function admin_notices() {
        if (!WWA()->api->is_configured()) {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'woo-whatsapp') !== false) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e('Woo WhatsApp:', 'woo-whatsapp'); ?></strong>
                        <?php
                        printf(
                            /* translators: %s: settings page URL */
                            esc_html__('WhatsApp API henüz yapılandırılmadı. %s sayfasından API ayarlarınızı yapın.', 'woo-whatsapp'),
                            '<a href="' . esc_url(admin_url('admin.php?page=woo-whatsapp#/settings')) . '">' . esc_html__('Ayarlar', 'woo-whatsapp') . '</a>'
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Plugin eylem linkleri
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=woo-whatsapp'),
            __('Ayarlar', 'woo-whatsapp')
        );

        array_unshift($links, $settings_link);

        return $links;
    }
}
