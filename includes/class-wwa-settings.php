<?php
/**
 * Ayarlar Sınıfı
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        // Hiçbir şey cache'leme
    }

    /**
     * Veritabanından doğrudan oku (cache bypass)
     *
     * @param string $option_name Option adı
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    private function get_fresh_option($option_name, $default = false) {
        global $wpdb;

        // Tüm olası cache'leri temizle
        wp_cache_delete($option_name, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');

        // Yeni bir sorgu yap - RAND() ile cache bypass
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option_name
            )
        );

        if ($row) {
            return maybe_unserialize($row->option_value);
        }

        return $default;
    }

    /**
     * Tek ayar getir - HER ZAMAN veritabanından
     *
     * @param string $key Ayar anahtarı
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public function get($key, $default = null) {
        $option_key = 'wwa_' . $key;
        return $this->get_fresh_option($option_key, $default);
    }

    /**
     * Ayar güncelle
     *
     * @param string $key Ayar anahtarı
     * @param mixed $value Değer
     * @return bool
     */
    public function set($key, $value) {
        global $wpdb;

        $option_key = 'wwa_' . $key;
        $serialized_value = maybe_serialize($value);

        // Tüm cache'leri temizle
        wp_cache_delete($option_key, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_flush();

        // Önce option var mı kontrol et
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option_key
            )
        );

        if ($existing) {
            // Güncelle - doğrudan SQL kullan
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
                    $serialized_value,
                    $option_key
                )
            );
        } else {
            // Ekle
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'yes')",
                    $option_key,
                    $serialized_value
                )
            );
        }

        // Cache'leri tekrar temizle
        wp_cache_delete($option_key, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_flush();

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WWA Settings SET: key={$option_key}, result={$result}, error=" . $wpdb->last_error);
        }

        return $result !== false;
    }

    /**
     * Toplu ayar güncelle
     *
     * @param array $settings Ayarlar
     * @return bool
     */
    public function update_settings($settings) {
        foreach ($settings as $key => $value) {
            $sanitized_value = $this->sanitize_setting($key, $value);
            $this->set($key, $sanitized_value);
        }

        return true;
    }

    /**
     * Ayarı temizle (sanitize)
     *
     * @param string $key Ayar anahtarı
     * @param mixed $value Değer
     * @return mixed
     */
    private function sanitize_setting($key, $value) {
        switch ($key) {
            // Boolean değerler
            case 'enabled':
            case 'debug_mode':
            case 'send_to_customer':
            case 'send_to_admin':
                return $value === 'yes' || $value === true ? 'yes' : 'no';

            // Sayısal değerler
            case 'log_retention_days':
                return absint($value);

            // Telefon numarası
            case 'admin_phone':
            case 'twilio_phone_number':
                return preg_replace('/[^0-9+]/', '', $value);

            // Array değerler
            case 'order_statuses':
                return is_array($value) ? array_map('sanitize_text_field', $value) : [];

            case 'templates':
                if (is_array($value)) {
                    return array_map('sanitize_textarea_field', $value);
                }
                return [];

            // API token ve hassas veriler
            case 'api_token':
            case 'twilio_auth_token':
            case 'ultramsg_token':
            case 'wati_api_token':
                return sanitize_text_field($value);

            // URL değerler
            case 'wati_api_url':
                return esc_url_raw($value);

            // Varsayılan
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Tüm ayarları getir (API için) - HER ZAMAN veritabanından
     *
     * @return array
     */
    public function get_all() {
        return [
            // Genel
            'enabled' => $this->get('enabled', 'yes'),
            'debug_mode' => $this->get('debug_mode', 'no'),

            // API
            'api_provider' => $this->get('api_provider', 'whatsapp_business'),
            'api_token' => $this->get('api_token', ''),
            'phone_number_id' => $this->get('phone_number_id', ''),
            'business_account_id' => $this->get('business_account_id', ''),

            // Twilio
            'twilio_account_sid' => $this->get('twilio_account_sid', ''),
            'twilio_auth_token' => $this->get('twilio_auth_token', ''),
            'twilio_phone_number' => $this->get('twilio_phone_number', ''),

            // Ultramsg
            'ultramsg_instance_id' => $this->get('ultramsg_instance_id', ''),
            'ultramsg_token' => $this->get('ultramsg_token', ''),

            // WATI
            'wati_api_url' => $this->get('wati_api_url', ''),
            'wati_api_token' => $this->get('wati_api_token', ''),

            // Bildirim
            'admin_phone' => $this->get('admin_phone', ''),
            'send_to_customer' => $this->get('send_to_customer', 'yes'),
            'send_to_admin' => $this->get('send_to_admin', 'yes'),
            'order_statuses' => $this->get('order_statuses', ['processing', 'completed']),

            // Şablonlar
            'templates' => $this->get('templates', []),

            // Log
            'log_retention_days' => $this->get('log_retention_days', 30)
        ];
    }

    /**
     * Şablon getir
     *
     * @param string $status_type Sipariş durumu
     * @return string
     */
    public function get_template($status_type) {
        $templates = $this->get('templates', []);

        if (isset($templates[$status_type])) {
            return $templates[$status_type];
        }

        // Varsayılan şablonlar
        $defaults = $this->get_default_templates();
        return $defaults[$status_type] ?? '';
    }

    /**
     * Varsayılan şablonları getir
     *
     * @return array
     */
    public function get_default_templates() {
        return [
            'pending' => __("Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz oluşturuldu.\n\nÖdeme bekleniyor.\n\nToplam: {toplam_tutar}", 'woo-whatsapp'),

            'processing' => __("Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz alındı ve işleme alındı.\n\nÜrünler:\n{ürün_listesi}\n\nToplam: {toplam_tutar}\n\nTeşekkürler!", 'woo-whatsapp'),

            'on-hold' => __("Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz ödeme bekleniyor.\n\nLütfen ödemenizi tamamlayın.\n\nToplam: {toplam_tutar}", 'woo-whatsapp'),

            'completed' => __("Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz tamamlandı!\n\nBizi tercih ettiğiniz için teşekkürler.", 'woo-whatsapp'),

            'cancelled' => __("Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz iptal edildi.\n\nSorularınız için bize ulaşabilirsiniz.", 'woo-whatsapp'),

            'refunded' => __("Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişinizin iadesi yapıldı.\n\nİade Tutarı: {toplam_tutar}", 'woo-whatsapp'),

            'failed' => __("Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişinizin ödemesi başarısız oldu.\n\nLütfen tekrar deneyin.", 'woo-whatsapp'),

            'shipped' => __("Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz kargoya verildi!\n\nKargo Firması: {kargo_firma}\nTakip No: {kargo_takip}\n\nİyi günler dileriz!", 'woo-whatsapp'),

            'admin_new_order' => __("🛒 Yeni Sipariş!\n\nSipariş No: #{sipariş_no}\nTarih: {sipariş_tarihi}\n\nMüşteri: {müşteri_adı} {müşteri_soyad}\nTelefon: {müşteri_telefon}\nE-posta: {müşteri_email}\n\nÜrünler:\n{ürün_listesi}\n\nAra Toplam: {ara_toplam}\nKargo: {kargo_ücreti}\nToplam: {toplam_tutar}\n\nÖdeme Yöntemi: {ödeme_yöntemi}\nTeslimat Adresi:\n{teslimat_adresi}", 'woo-whatsapp'),

            'admin_cancelled' => __("❌ Sipariş İptal Edildi!\n\nSipariş No: #{sipariş_no}\nMüşteri: {müşteri_adı}\nTutar: {toplam_tutar}", 'woo-whatsapp'),

            'low_stock' => __("⚠️ Düşük Stok Uyarısı!\n\nÜrün: {ürün_adı}\nMevcut Stok: {stok_miktarı}\n\nLütfen stok güncelleme yapın.", 'woo-whatsapp'),

            'custom' => __("Merhaba {müşteri_adı},\n\nSipariş No: #{sipariş_no}\n\n{özel_mesaj}", 'woo-whatsapp')
        ];
    }

    /**
     * Kullanılabilir placeholder'ları getir
     *
     * @return array
     */
    public function get_available_placeholders() {
        return [
            'müşteri' => [
                '{müşteri_adı}' => __('Müşteri adı', 'woo-whatsapp'),
                '{müşteri_soyad}' => __('Müşteri soyadı', 'woo-whatsapp'),
                '{müşteri_tam_ad}' => __('Müşteri tam adı', 'woo-whatsapp'),
                '{müşteri_email}' => __('Müşteri e-posta', 'woo-whatsapp'),
                '{müşteri_telefon}' => __('Müşteri telefonu', 'woo-whatsapp')
            ],
            'sipariş' => [
                '{sipariş_no}' => __('Sipariş numarası', 'woo-whatsapp'),
                '{sipariş_tarihi}' => __('Sipariş tarihi', 'woo-whatsapp'),
                '{sipariş_durumu}' => __('Sipariş durumu', 'woo-whatsapp'),
                '{ödeme_yöntemi}' => __('Ödeme yöntemi', 'woo-whatsapp'),
                '{sipariş_notu}' => __('Müşteri notu', 'woo-whatsapp')
            ],
            'tutar' => [
                '{ara_toplam}' => __('Ara toplam', 'woo-whatsapp'),
                '{kargo_ücreti}' => __('Kargo ücreti', 'woo-whatsapp'),
                '{indirim_tutarı}' => __('İndirim tutarı', 'woo-whatsapp'),
                '{vergi_tutarı}' => __('Vergi tutarı', 'woo-whatsapp'),
                '{toplam_tutar}' => __('Toplam tutar', 'woo-whatsapp')
            ],
            'ürün' => [
                '{ürün_listesi}' => __('Ürün listesi', 'woo-whatsapp'),
                '{ürün_sayısı}' => __('Toplam ürün sayısı', 'woo-whatsapp'),
                '{ilk_ürün}' => __('İlk ürün adı', 'woo-whatsapp')
            ],
            'kargo' => [
                '{kargo_firma}' => __('Kargo firması', 'woo-whatsapp'),
                '{kargo_takip}' => __('Kargo takip numarası', 'woo-whatsapp'),
                '{teslimat_adresi}' => __('Teslimat adresi', 'woo-whatsapp'),
                '{fatura_adresi}' => __('Fatura adresi', 'woo-whatsapp')
            ],
            'mağaza' => [
                '{mağaza_adı}' => __('Mağaza adı', 'woo-whatsapp'),
                '{mağaza_url}' => __('Mağaza URL', 'woo-whatsapp'),
                '{sipariş_url}' => __('Sipariş detay URL', 'woo-whatsapp')
            ]
        ];
    }

    /**
     * Desteklenen sipariş durumlarını getir
     *
     * @return array
     */
    public function get_order_statuses() {
        if (!function_exists('wc_get_order_statuses')) {
            return [];
        }

        $wc_statuses = wc_get_order_statuses();
        $statuses = [];

        foreach ($wc_statuses as $status => $label) {
            // wc- prefix'ini kaldır
            $status_key = str_replace('wc-', '', $status);
            $statuses[$status_key] = $label;
        }

        // Özel durumlar ekle
        $statuses['shipped'] = __('Kargoya Verildi', 'woo-whatsapp');

        return $statuses;
    }

    /**
     * Bildirimlerin etkin olup olmadığını kontrol et
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->get('enabled') === 'yes';
    }

    /**
     * Belirli durum için bildirimin etkin olup olmadığını kontrol et
     *
     * @param string $status Sipariş durumu
     * @return bool
     */
    public function is_status_enabled($status) {
        $enabled_statuses = $this->get('order_statuses', []);
        return in_array($status, $enabled_statuses, true);
    }

    /**
     * API yapılandırılmış mı kontrol et
     *
     * @return bool
     */
    public function is_api_configured() {
        return WWA()->api->is_configured();
    }

    /**
     * Ayarları dışa aktar
     *
     * @return string JSON formatında ayarlar
     */
    public function export_settings() {
        // Tüm ayarları veritabanından taze oku
        $export_settings = [
            'api_provider' => get_option('wwa_api_provider', 'whatsapp_business'),
            'api_token' => get_option('wwa_api_token', ''),
            'phone_number_id' => get_option('wwa_phone_number_id', ''),
            'twilio_account_sid' => get_option('wwa_twilio_account_sid', ''),
            'twilio_auth_token' => get_option('wwa_twilio_auth_token', ''),
            'twilio_phone_number' => get_option('wwa_twilio_phone_number', ''),
            'ultramsg_instance_id' => get_option('wwa_ultramsg_instance_id', ''),
            'ultramsg_token' => get_option('wwa_ultramsg_token', ''),
            'wati_api_url' => get_option('wwa_wati_api_url', ''),
            'wati_api_token' => get_option('wwa_wati_api_token', ''),
            'admin_phone' => get_option('wwa_admin_phone', ''),
            'country_code' => get_option('wwa_country_code', '90'),
            'debug_mode' => get_option('wwa_debug_mode', 'no'),
            'enabled_statuses' => get_option('wwa_enabled_statuses', []),
        ];

        // Hassas verileri gizle
        $sensitive_keys = ['api_token', 'twilio_auth_token', 'ultramsg_token', 'wati_api_token'];
        foreach ($sensitive_keys as $key) {
            if (isset($export_settings[$key]) && !empty($export_settings[$key])) {
                $export_settings[$key] = '***hidden***';
            }
        }

        return wp_json_encode($export_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ayarları içe aktar
     *
     * @param string $json JSON formatında ayarlar
     * @return bool|WP_Error
     */
    public function import_settings($json) {
        $settings = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Geçersiz JSON formatı', 'woo-whatsapp'));
        }

        // Hassas verileri atla
        $sensitive_keys = ['api_token', 'twilio_auth_token', 'ultramsg_token', 'wati_api_token'];
        foreach ($sensitive_keys as $key) {
            if (isset($settings[$key]) && $settings[$key] === '***hidden***') {
                unset($settings[$key]);
            }
        }

        return $this->update_settings($settings);
    }

    /**
     * Pro lisansın aktif olup olmadığını kontrol et
     * Lifetime (Ömür Boyu) lisans modeli - CodeCanyon satışları için
     *
     * @return bool
     */
    public function is_pro_active() {
        $license_status = get_option('wwa_license_status', 'inactive');

        return $license_status === 'active';
    }

    /**
     * Belirli bir Pro özelliğin aktif olup olmadığını kontrol et
     * Lifetime lisans - aktifse tüm özellikler açık
     *
     * @param string $feature Özellik adı (flow_builder, abandoned_cart, chatbot, waitlist, analytics, multi_language)
     * @return bool
     */
    public function is_feature_active($feature) {
        if (!$this->is_pro_active()) {
            return false;
        }

        $all_features = ['flow_builder', 'abandoned_cart', 'chatbot', 'waitlist', 'analytics', 'multi_language'];

        return in_array($feature, $all_features);
    }

    /**
     * Lisans türünü getir
     *
     * @return string
     */
    public function get_license_type() {
        if (!$this->is_pro_active()) {
            return 'free';
        }
        return get_option('wwa_license_type', 'lifetime');
    }

    /**
     * Lisans bilgilerini getir
     *
     * @return array
     */
    public function get_license_info() {
        return [
            'is_active' => $this->is_pro_active(),
            'type' => $this->get_license_type(),
            'is_lifetime' => true,
            'key' => get_option('wwa_license_key', ''),
            'activated_at' => get_option('wwa_license_activated_at', '')
        ];
    }
}
