<?php
/**
 * WooCommerce Entegrasyon Sınıfı
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_WooCommerce {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 4);

        add_action('woocommerce_new_order', [$this, 'on_new_order'], 10, 2);

        add_action('woocommerce_process_shop_order_meta', [$this, 'on_order_meta_saved'], 50, 1);

        add_action('woocommerce_low_stock', [$this, 'on_low_stock']);
        add_action('woocommerce_no_stock', [$this, 'on_no_stock']);

        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'add_send_whatsapp_button']);

        add_action('wp_ajax_wwa_send_manual_message', [$this, 'ajax_send_manual_message']);

        add_action('woocommerce_order_note_added', [$this, 'on_order_note_added'], 10, 2);
    }

    /**
     * Sipariş durumu değiştiğinde
     *
     * @param int $order_id Sipariş ID
     * @param string $old_status Eski durum
     * @param string $new_status Yeni durum
     * @param WC_Order $order Sipariş objesi
     */
    public function on_order_status_changed($order_id, $old_status, $new_status, $order) {
        if (!WWA()->settings->is_enabled()) {
            return;
        }

        if (!WWA()->settings->is_status_enabled($new_status)) {
            return;
        }

        if (WWA()->settings->get('send_to_customer') === 'yes') {
            $this->send_customer_notification($order, $new_status);
        }

        if (WWA()->settings->get('send_to_admin') === 'yes' && $new_status === 'processing') {
            $this->send_admin_notification($order, 'admin_new_order');
        }

        if (WWA()->settings->get('send_to_admin') === 'yes' && $new_status === 'cancelled') {
            $this->send_admin_notification($order, 'admin_cancelled');
        }

        WWA()->logger->debug_log("Sipariş durumu değişti: #{$order_id} - {$old_status} -> {$new_status}");
    }

    /**
     * Yeni sipariş oluşturulduğunda
     *
     * @param int $order_id Sipariş ID
     * @param WC_Order $order Sipariş objesi
     */
    public function on_new_order($order_id, $order = null) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        WWA()->logger->debug_log("Yeni sipariş oluşturuldu: #{$order_id}");
    }

    /**
     * Müşteriye bildirim gönder
     *
     * @param WC_Order $order Sipariş objesi
     * @param string $status_type Durum tipi
     * @return bool
     */
    public function send_customer_notification($order, $status_type) {
        $customer_phone = $order->get_billing_phone();

        if (empty($customer_phone)) {
            WWA()->logger->debug_log("Müşteri telefonu bulunamadı: Sipariş #{$order->get_id()}");
            return false;
        }

        $template = WWA()->settings->get_template($status_type);

        if (empty($template)) {
            WWA()->logger->debug_log("Şablon bulunamadı: {$status_type}");
            return false;
        }

        // Mesajı hazırla
        $parser = new WWA_Template_Parser($order);
        $message = $parser->parse($template);

        // Log kaydı oluştur
        $log_id = WWA()->logger->add([
            'order_id' => $order->get_id(),
            'phone' => $customer_phone,
            'message' => $message,
            'status' => 'pending',
            'status_type' => $status_type,
            'recipient_type' => 'customer'
        ]);

        $result = WWA()->api->send_message($customer_phone, $message);

        if ($result['success']) {
            WWA()->logger->update_status($log_id, 'sent', [
                'message_id' => $result['message_id'] ?? null,
                'api_response' => $result['response'] ?? null
            ]);

            $order->add_order_note(
                sprintf(
                    __('WhatsApp bildirimi gönderildi (%s): %s', 'woo-whatsapp'),
                    $status_type,
                    $customer_phone
                ),
                false
            );

            return true;
        } else {
            WWA()->logger->update_status($log_id, 'failed', [
                'api_response' => ['error' => $result['error'] ?? 'Unknown error']
            ]);

            $order->add_order_note(
                sprintf(
                    __('WhatsApp bildirimi BAŞARISIZ (%s): %s - Hata: %s', 'woo-whatsapp'),
                    $status_type,
                    $customer_phone,
                    $result['error'] ?? 'Unknown'
                ),
                false
            );

            return false;
        }
    }

    /**
     * Admin'e bildirim gönder
     *
     * @param WC_Order $order Sipariş objesi
     * @param string $template_type Şablon tipi
     * @return bool
     */
    public function send_admin_notification($order, $template_type = 'admin_new_order') {
        $admin_phone = WWA()->settings->get('admin_phone');

        if (empty($admin_phone)) {
            WWA()->logger->debug_log("Admin telefonu ayarlanmamış");
            return false;
        }

        $template = WWA()->settings->get_template($template_type);

        if (empty($template)) {
            return false;
        }

        $parser = new WWA_Template_Parser($order);
        $message = $parser->parse($template);

        $log_id = WWA()->logger->add([
            'order_id' => $order->get_id(),
            'phone' => $admin_phone,
            'message' => $message,
            'status' => 'pending',
            'status_type' => $template_type,
            'recipient_type' => 'admin'
        ]);

        $result = WWA()->api->send_message($admin_phone, $message);

        if ($result['success']) {
            WWA()->logger->update_status($log_id, 'sent', [
                'message_id' => $result['message_id'] ?? null,
                'api_response' => $result['response'] ?? null
            ]);
            return true;
        } else {
            WWA()->logger->update_status($log_id, 'failed', [
                'api_response' => ['error' => $result['error'] ?? 'Unknown error']
            ]);
            return false;
        }
    }

    /**
     * Sipariş meta kaydedildiğinde (kargo takip vb.)
     *
     * @param int $order_id Sipariş ID
     */
    public function on_order_meta_saved($order_id) {
        if (!isset($_POST['_wwa_tracking_number']) || empty($_POST['_wwa_tracking_number'])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $tracking_sent = $order->get_meta('_wwa_tracking_sent');
        if ($tracking_sent === 'yes') {
            return;
        }

        if (WWA()->settings->is_status_enabled('shipped')) {
            $this->send_customer_notification($order, 'shipped');
            $order->update_meta_data('_wwa_tracking_sent', 'yes');
            $order->save();
        }
    }

    /**
     * Düşük stok uyarısı
     *
     * @param WC_Product $product Ürün
     */
    public function on_low_stock($product) {
        if (WWA()->settings->get('send_to_admin') !== 'yes') {
            return;
        }

        $admin_phone = WWA()->settings->get('admin_phone');
        if (empty($admin_phone)) {
            return;
        }

        $template = WWA()->settings->get_template('low_stock');
        if (empty($template)) {
            return;
        }

        $message = str_replace(
            ['{ürün_adı}', '{stok_miktarı}', '{ürün_sku}'],
            [$product->get_name(), $product->get_stock_quantity(), $product->get_sku()],
            $template
        );

        WWA()->api->send_message($admin_phone, $message);
    }

    /**
     * Stok tükendi uyarısı
     *
     * @param WC_Product $product Ürün
     */
    public function on_no_stock($product) {
        if (WWA()->settings->get('send_to_admin') !== 'yes') {
            return;
        }

        $admin_phone = WWA()->settings->get('admin_phone');
        if (empty($admin_phone)) {
            return;
        }

        $message = sprintf(
            __("⚠️ Stok Tükendi!\n\nÜrün: %s\nSKU: %s\n\nStok güncelleme yapmanız gerekmektedir.", 'woo-whatsapp'),
            $product->get_name(),
            $product->get_sku()
        );

        WWA()->api->send_message($admin_phone, $message);
    }

    /**
     * Sipariş sayfasına WhatsApp butonu ekle
     *
     * @param WC_Order $order Sipariş objesi
     */
    public function add_send_whatsapp_button($order) {
        $customer_phone = $order->get_billing_phone();
        ?>
        <div class="wwa-admin-actions" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
            <h4 style="margin-bottom: 10px;">
                <span class="dashicons dashicons-whatsapp" style="color: #25D366;"></span>
                <?php esc_html_e('WhatsApp Bildirimi', 'woo-whatsapp'); ?>
            </h4>

            <?php if (!empty($customer_phone)) : ?>
                <p>
                    <strong><?php esc_html_e('Müşteri Telefonu:', 'woo-whatsapp'); ?></strong>
                    <?php echo esc_html($customer_phone); ?>
                </p>

                <select id="wwa-template-select" style="width: 100%; margin-bottom: 10px;">
                    <option value=""><?php esc_html_e('Şablon seçin...', 'woo-whatsapp'); ?></option>
                    <?php
                    $statuses = WWA()->settings->get_order_statuses();
                    foreach ($statuses as $status => $label) :
                    ?>
                        <option value="<?php echo esc_attr($status); ?>">
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="custom"><?php esc_html_e('Özel Mesaj', 'woo-whatsapp'); ?></option>
                </select>

                <textarea id="wwa-custom-message" placeholder="<?php esc_attr_e('Özel mesajınızı yazın...', 'woo-whatsapp'); ?>" style="width: 100%; height: 80px; display: none; margin-bottom: 10px;"></textarea>

                <button type="button" class="button button-primary" id="wwa-send-message" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                    <span class="dashicons dashicons-whatsapp" style="margin-top: 3px;"></span>
                    <?php esc_html_e('WhatsApp Gönder', 'woo-whatsapp'); ?>
                </button>

                <span id="wwa-send-status" style="margin-left: 10px;"></span>

            <?php else : ?>
                <p style="color: #999;">
                    <?php esc_html_e('Müşteri telefon numarası bulunamadı.', 'woo-whatsapp'); ?>
                </p>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#wwa-template-select').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#wwa-custom-message').show();
                } else {
                    $('#wwa-custom-message').hide();
                }
            });

            $('#wwa-send-message').on('click', function() {
                var $btn = $(this);
                var orderId = $btn.data('order-id');
                var template = $('#wwa-template-select').val();
                var customMessage = $('#wwa-custom-message').val();

                if (!template) {
                    alert('<?php esc_html_e('Lütfen bir şablon seçin', 'woo-whatsapp'); ?>');
                    return;
                }

                if (template === 'custom' && !customMessage) {
                    alert('<?php esc_html_e('Lütfen mesajınızı yazın', 'woo-whatsapp'); ?>');
                    return;
                }

                $btn.prop('disabled', true);
                $('#wwa-send-status').html('<span style="color: #999;"><?php esc_html_e('Gönderiliyor...', 'woo-whatsapp'); ?></span>');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wwa_send_manual_message',
                        order_id: orderId,
                        template: template,
                        custom_message: customMessage,
                        nonce: '<?php echo wp_create_nonce('wwa_send_message'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#wwa-send-status').html('<span style="color: green;">✓ <?php esc_html_e('Gönderildi!', 'woo-whatsapp'); ?></span>');
                        } else {
                            $('#wwa-send-status').html('<span style="color: red;">✗ ' + response.data + '</span>');
                        }
                        $btn.prop('disabled', false);
                    },
                    error: function() {
                        $('#wwa-send-status').html('<span style="color: red;">✗ <?php esc_html_e('Hata oluştu', 'woo-whatsapp'); ?></span>');
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Manuel mesaj gönderimi AJAX handler
     */
    public function ajax_send_manual_message() {
        check_ajax_referer('wwa_send_message', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz yok', 'woo-whatsapp'));
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $custom_message = isset($_POST['custom_message']) ? sanitize_textarea_field($_POST['custom_message']) : '';

        if (!$order_id) {
            wp_send_json_error(__('Geçersiz sipariş', 'woo-whatsapp'));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Sipariş bulunamadı', 'woo-whatsapp'));
        }

        $customer_phone = $order->get_billing_phone();
        if (empty($customer_phone)) {
            wp_send_json_error(__('Müşteri telefonu bulunamadı', 'woo-whatsapp'));
        }

        // Mesajı hazırla
        if ($template === 'custom') {
            $parser = new WWA_Template_Parser($order);
            $message = $parser->parse($custom_message);
        } else {
            $template_content = WWA()->settings->get_template($template);
            if (empty($template_content)) {
                wp_send_json_error(__('Şablon bulunamadı', 'woo-whatsapp'));
            }
            $parser = new WWA_Template_Parser($order);
            $message = $parser->parse($template_content);
        }

        $log_id = WWA()->logger->add([
            'order_id' => $order_id,
            'phone' => $customer_phone,
            'message' => $message,
            'status' => 'pending',
            'status_type' => $template,
            'recipient_type' => 'customer'
        ]);

        $result = WWA()->api->send_message($customer_phone, $message);

        if ($result['success']) {
            WWA()->logger->update_status($log_id, 'sent', [
                'message_id' => $result['message_id'] ?? null,
                'api_response' => $result['response'] ?? null
            ]);

            $order->add_order_note(
                sprintf(
                    __('Manuel WhatsApp bildirimi gönderildi: %s', 'woo-whatsapp'),
                    $customer_phone
                ),
                false
            );

            wp_send_json_success();
        } else {
            WWA()->logger->update_status($log_id, 'failed', [
                'api_response' => ['error' => $result['error']]
            ]);
            wp_send_json_error($result['error']);
        }
    }

    /**
     * Sipariş notu eklendiğinde
     *
     * @param int $comment_id Yorum ID
     * @param WC_Order $order Sipariş objesi
     */
    public function on_order_note_added($comment_id, $order) {
    }

    /**
     * Toplu mesaj gönder
     *
     * @param array $order_ids Sipariş ID'leri
     * @param string $message Mesaj
     * @return array Sonuçlar
     */
    public function send_bulk_message($order_ids, $message) {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                $results['failed']++;
                $results['errors'][] = "Sipariş #{$order_id} bulunamadı";
                continue;
            }

            $customer_phone = $order->get_billing_phone();
            if (empty($customer_phone)) {
                $results['failed']++;
                $results['errors'][] = "Sipariş #{$order_id}: Telefon bulunamadı";
                continue;
            }

            $parser = new WWA_Template_Parser($order);
            $parsed_message = $parser->parse($message);

            $result = WWA()->api->send_message($customer_phone, $parsed_message);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Sipariş #{$order_id}: " . ($result['error'] ?? 'Bilinmeyen hata');
            }

            sleep(1);
        }

        return $results;
    }
}
