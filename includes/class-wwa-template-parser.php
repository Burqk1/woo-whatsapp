<?php
/**
 * Mesaj Şablonu Parser Sınıfı
 *
 * Placeholder'ları gerçek değerlerle değiştirir
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_Template_Parser {

    /**
     * Sipariş objesi
     *
     * @var WC_Order
     */
    private $order;

    /**
     * Placeholder değerleri cache
     *
     * @var array
     */
    private $values = [];

    /**
     * Constructor
     *
     * @param WC_Order $order Sipariş objesi
     */
    public function __construct($order) {
        $this->order = $order;
        $this->prepare_values();
    }

    /**
     * Tüm placeholder değerlerini hazırla
     */
    private function prepare_values() {
        if (!$this->order) {
            return;
        }

        // Müşteri bilgileri
        $this->values['{müşteri}'] = $this->order->get_billing_first_name();
        $this->values['{müşteri_adı}'] = $this->order->get_billing_first_name();
        $this->values['{müşteri_soyad}'] = $this->order->get_billing_last_name();
        $this->values['{müşteri_tam_ad}'] = $this->order->get_formatted_billing_full_name();
        $this->values['{müşteri_email}'] = $this->order->get_billing_email();
        $this->values['{müşteri_telefon}'] = $this->order->get_billing_phone();

        // Sipariş bilgileri
        $this->values['{sipariş_no}'] = $this->order->get_order_number();
        $this->values['{sipariş_tarihi}'] = wc_format_datetime($this->order->get_date_created());
        $this->values['{sipariş_durumu}'] = wc_get_order_status_name($this->order->get_status());
        $this->values['{ödeme_yöntemi}'] = $this->order->get_payment_method_title();
        $this->values['{sipariş_notu}'] = $this->order->get_customer_note();

        // Tutar bilgileri - WhatsApp için HTML olmadan düz metin format
        $this->values['{ara_toplam}'] = $this->format_price_plain($this->order->get_subtotal());
        $this->values['{kargo_ücreti}'] = $this->order->get_shipping_total() > 0
            ? $this->format_price_plain($this->order->get_shipping_total())
            : __('Ücretsiz', 'woo-whatsapp');
        $this->values['{indirim_tutarı}'] = $this->order->get_discount_total() > 0
            ? $this->format_price_plain($this->order->get_discount_total())
            : '-';
        $this->values['{vergi_tutarı}'] = $this->format_price_plain($this->order->get_total_tax());
        $this->values['{toplam_tutar}'] = $this->format_price_plain($this->order->get_total());

        // Ürün bilgileri
        $this->values['{ürün_listesi}'] = $this->get_items_list();
        $this->values['{ürün_sayısı}'] = $this->order->get_item_count();
        $this->values['{ilk_ürün}'] = $this->get_first_item_name();

        // Kargo bilgileri
        $this->values['{kargo_firma}'] = $this->order->get_shipping_method();
        $this->values['{kargo_takip}'] = $this->get_tracking_number();
        $this->values['{teslimat_adresi}'] = $this->get_formatted_shipping_address();
        $this->values['{fatura_adresi}'] = $this->get_formatted_billing_address();

        // Mağaza bilgileri
        $this->values['{mağaza_adı}'] = get_bloginfo('name');
        $this->values['{mağaza_url}'] = home_url();
        $this->values['{sipariş_url}'] = $this->order->get_view_order_url();

        // Ekstra
        $this->values['{kupon_kodu}'] = implode(', ', $this->order->get_coupon_codes());

        // Hook ile ek değerler eklenebilir
        $this->values = apply_filters('wwa_template_values', $this->values, $this->order);
    }

    /**
     * Şablonu parse et
     *
     * @param string $template Şablon metni
     * @return string Parse edilmiş metin
     */
    public function parse($template) {
        if (empty($template)) {
            return '';
        }

        // Placeholder'ları değiştir
        $message = str_replace(
            array_keys($this->values),
            array_values($this->values),
            $template
        );

        // Kalan placeholder'ları temizle (değeri olmayan)
        $message = preg_replace('/\{[^}]+\}/', '', $message);

        // Çoklu boş satırları temizle
        $message = preg_replace('/\n{3,}/', "\n\n", $message);

        return trim($message);
    }

    /**
     * Fiyatı düz metin olarak formatla (HTML olmadan)
     * Siparişin para birimini kullanır
     *
     * @param float $price Fiyat
     * @return string Formatlanmış fiyat (örn: "$2,500.00" veya "₺250,00")
     */
    private function format_price_plain($price) {
        // Siparişin para birimini al (müşteri hangi para birimiyle ödediyse o)
        $currency = $this->order ? $this->order->get_currency() : get_woocommerce_currency();

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

        // Para birimi pozisyonuna göre formatla
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
     * Ürün listesini formatla
     *
     * @return string
     */
    private function get_items_list() {
        $items = $this->order->get_items();
        $lines = [];

        foreach ($items as $item) {
            $product = $item->get_product();
            $name = $item->get_name();
            $qty = $item->get_quantity();
            $total = $this->format_price_plain($item->get_total());

            // SKU varsa ekle
            $sku = '';
            if ($product && $product->get_sku()) {
                $sku = ' (' . $product->get_sku() . ')';
            }

            // Varyasyon detayları
            $variation = '';
            if ($product && $product->is_type('variation')) {
                $attributes = $product->get_variation_attributes();
                if (!empty($attributes)) {
                    $variation_parts = [];
                    foreach ($attributes as $attr => $value) {
                        $attr_label = wc_attribute_label(str_replace('attribute_', '', $attr));
                        $variation_parts[] = "{$attr_label}: {$value}";
                    }
                    $variation = ' [' . implode(', ', $variation_parts) . ']';
                }
            }

            $lines[] = "• {$name}{$sku}{$variation} x {$qty} = {$total}";
        }

        return implode("\n", $lines);
    }

    /**
     * İlk ürün adını getir
     *
     * @return string
     */
    private function get_first_item_name() {
        $items = $this->order->get_items();

        if (empty($items)) {
            return '';
        }

        $first_item = reset($items);
        return $first_item->get_name();
    }

    /**
     * Kargo takip numarasını getir
     *
     * @return string
     */
    private function get_tracking_number() {
        // Özel meta alanından
        $tracking = $this->order->get_meta('_wwa_tracking_number');

        if (!empty($tracking)) {
            return $tracking;
        }

        // WooCommerce Shipment Tracking eklentisi uyumluluğu
        $tracking_items = $this->order->get_meta('_wc_shipment_tracking_items');
        if (!empty($tracking_items) && is_array($tracking_items)) {
            $first_tracking = reset($tracking_items);
            return $first_tracking['tracking_number'] ?? '';
        }

        // YITH WooCommerce Order Tracking uyumluluğu
        $yith_tracking = $this->order->get_meta('ywot_tracking_code');
        if (!empty($yith_tracking)) {
            return $yith_tracking;
        }

        return __('Henüz belirtilmedi', 'woo-whatsapp');
    }

    /**
     * Teslimat adresini formatla
     *
     * @return string
     */
    private function get_formatted_shipping_address() {
        $address = $this->order->get_formatted_shipping_address();

        if (empty($address)) {
            // Teslimat adresi yoksa fatura adresini kullan
            $address = $this->order->get_formatted_billing_address();
        }

        // HTML etiketlerini kaldır ve satır sonlarını düzenle
        $address = wp_strip_all_tags($address);
        $address = str_replace('<br/>', "\n", $address);
        $address = str_replace('<br>', "\n", $address);

        return $address;
    }

    /**
     * Fatura adresini formatla
     *
     * @return string
     */
    private function get_formatted_billing_address() {
        $address = $this->order->get_formatted_billing_address();

        // HTML etiketlerini kaldır
        $address = wp_strip_all_tags($address);
        $address = str_replace('<br/>', "\n", $address);
        $address = str_replace('<br>', "\n", $address);

        return $address;
    }

    /**
     * Özel placeholder ekle
     *
     * @param string $placeholder Placeholder adı (süslü parantez dahil)
     * @param string $value Değer
     */
    public function add_value($placeholder, $value) {
        $this->values[$placeholder] = $value;
    }

    /**
     * Tüm placeholder değerlerini getir
     *
     * @return array
     */
    public function get_values() {
        return $this->values;
    }

    /**
     * Belirli bir placeholder değerini getir
     *
     * @param string $placeholder Placeholder adı
     * @return string|null
     */
    public function get_value($placeholder) {
        return $this->values[$placeholder] ?? null;
    }

    /**
     * Şablon önizlemesi oluştur (örnek verilerle)
     *
     * @param string $template Şablon metni
     * @return string
     */
    public static function preview($template) {
        $sample_values = [
            '{müşteri}' => 'Ahmet',
            '{müşteri_adı}' => 'Ahmet',
            '{müşteri_soyad}' => 'Yılmaz',
            '{müşteri_tam_ad}' => 'Ahmet Yılmaz',
            '{müşteri_email}' => 'ahmet@example.com',
            '{müşteri_telefon}' => '+90 555 123 4567',
            '{sipariş_no}' => '12345',
            '{sipariş_tarihi}' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            '{sipariş_durumu}' => 'İşleniyor',
            '{ödeme_yöntemi}' => 'Kredi Kartı',
            '{ara_toplam}' => '₺250,00',
            '{kargo_ücreti}' => '₺25,00',
            '{indirim_tutarı}' => '₺25,00',
            '{vergi_tutarı}' => '₺45,00',
            '{toplam_tutar}' => '₺295,00',
            '{ürün_listesi}' => "• Örnek Ürün 1 (SKU001) x 2 = ₺150,00\n• Örnek Ürün 2 (SKU002) x 1 = ₺100,00",
            '{ürün_sayısı}' => '3',
            '{ilk_ürün}' => 'Örnek Ürün 1',
            '{kargo_firma}' => 'Aras Kargo',
            '{kargo_takip}' => 'TRK123456789',
            '{teslimat_adresi}' => "Örnek Mahallesi\nTest Sokak No: 1\n34000 İstanbul",
            '{fatura_adresi}' => "Örnek Mahallesi\nTest Sokak No: 1\n34000 İstanbul",
            '{mağaza_adı}' => get_bloginfo('name') ?: 'Mağaza Adı',
            '{mağaza_url}' => home_url(),
            '{sipariş_url}' => home_url('/my-account/view-order/12345/'),
            '{kupon_kodu}' => 'INDIRIM10'
        ];

        $preview = str_replace(
            array_keys($sample_values),
            array_values($sample_values),
            $template
        );

        return $preview;
    }
}
