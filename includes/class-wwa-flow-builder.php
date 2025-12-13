<?php
/**
 * Flow Builder Sınıfı - Görsel Otomasyon Akış Oluşturucu
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_Flow_Builder {

    /**
     * Flow tablosu adı
     */
    private $table_flows;
    private $table_flow_logs;

    /**
     * Desteklenen trigger'lar
     */
    private $triggers = [];

    /**
     * Desteklenen action'lar
     */
    private $actions = [];

    /**
     * Desteklenen condition'lar
     */
    private $conditions = [];

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_flows = $wpdb->prefix . 'wwa_flows';
        $this->table_flow_logs = $wpdb->prefix . 'wwa_flow_logs';

        $this->maybe_create_tables();

        $this->register_triggers();
        $this->register_actions();
        $this->register_conditions();
        $this->init_hooks();
    }

    /**
     * Tablo adını getir (debug için)
     */
    public function get_table_name() {
        return $this->table_flows;
    }

    /**
     * Tablolar yoksa oluştur
     */
    private function maybe_create_tables() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_flows}'") !== $this->table_flows) {
            self::create_tables();
        }
    }

    /**
     * Mevcut trigger'ları getir (static)
     */
    public static function get_available_triggers() {
        $triggers = [
            'order_created' => [
                'name' => __('Yeni Sipariş Oluşturuldu', 'woo-whatsapp'),
                'description' => __('Yeni bir sipariş geldiğinde tetiklenir', 'woo-whatsapp'),
                'icon' => 'shopping-cart',
                'category' => 'order',
                'config_fields' => []
            ],
            'order_status_changed' => [
                'name' => __('Sipariş Durumu Değişti', 'woo-whatsapp'),
                'description' => __('Sipariş durumu belirli bir duruma geçtiğinde tetiklenir', 'woo-whatsapp'),
                'icon' => 'refresh',
                'category' => 'order',
                'config_fields' => [
                    'from_status' => [
                        'type' => 'select',
                        'label' => __('Eski Durum', 'woo-whatsapp'),
                        'options' => 'order_statuses',
                        'multiple' => true,
                        'required' => false
                    ],
                    'to_status' => [
                        'type' => 'select',
                        'label' => __('Yeni Durum', 'woo-whatsapp'),
                        'options' => 'order_statuses',
                        'multiple' => true,
                        'required' => true
                    ]
                ]
            ],
            'abandoned_cart' => [
                'name' => __('Sepet Terk Edildi', 'woo-whatsapp'),
                'description' => __('Müşteri sepetini terk ettiğinde tetiklenir', 'woo-whatsapp'),
                'icon' => 'shopping-bag',
                'category' => 'cart',
                'config_fields' => [
                    'wait_time' => [
                        'type' => 'number',
                        'label' => __('Bekleme Süresi (Dakika)', 'woo-whatsapp'),
                        'default' => 60,
                        'min' => 15,
                        'required' => true
                    ]
                ]
            ],
            'product_back_in_stock' => [
                'name' => __('Ürün Stoğa Girdi', 'woo-whatsapp'),
                'description' => __('Stokta olmayan bir ürün tekrar stoğa girdiğinde', 'woo-whatsapp'),
                'icon' => 'package',
                'category' => 'product',
                'config_fields' => []
            ],
            'webhook_received' => [
                'name' => __('Webhook Alındı', 'woo-whatsapp'),
                'description' => __('Harici bir sistemden webhook geldiğinde', 'woo-whatsapp'),
                'icon' => 'link',
                'category' => 'integration',
                'config_fields' => []
            ]
        ];

        return apply_filters('wwa_flow_triggers', $triggers);
    }

    /**
     * Mevcut action'ları getir (static)
     */
    public static function get_available_actions() {
        $actions = [
            'send_whatsapp' => [
                'name' => __('WhatsApp Mesajı Gönder', 'woo-whatsapp'),
                'description' => __('Müşteriye WhatsApp mesajı gönder', 'woo-whatsapp'),
                'icon' => 'message-square',
                'color' => '#25D366',
                'config_fields' => [
                    'recipient' => [
                        'type' => 'select',
                        'label' => __('Alıcı', 'woo-whatsapp'),
                        'options' => [
                            'customer' => __('Müşteri', 'woo-whatsapp'),
                            'admin' => __('Admin', 'woo-whatsapp'),
                            'custom' => __('Özel Numara', 'woo-whatsapp')
                        ],
                        'default' => 'customer'
                    ],
                    'message_template' => [
                        'type' => 'textarea',
                        'label' => __('Mesaj Şablonu', 'woo-whatsapp'),
                        'rows' => 5
                    ]
                ]
            ],
            'wait' => [
                'name' => __('Bekle', 'woo-whatsapp'),
                'description' => __('Belirli bir süre bekle', 'woo-whatsapp'),
                'icon' => 'clock',
                'color' => '#6c757d',
                'config_fields' => [
                    'wait_time' => [
                        'type' => 'number',
                        'label' => __('Süre', 'woo-whatsapp'),
                        'default' => 60
                    ],
                    'wait_unit' => [
                        'type' => 'select',
                        'label' => __('Birim', 'woo-whatsapp'),
                        'options' => [
                            'minutes' => __('Dakika', 'woo-whatsapp'),
                            'hours' => __('Saat', 'woo-whatsapp'),
                            'days' => __('Gün', 'woo-whatsapp')
                        ],
                        'default' => 'minutes'
                    ]
                ]
            ],
            'condition' => [
                'name' => __('Koşul Kontrolü', 'woo-whatsapp'),
                'description' => __('Belirli bir koşulu kontrol et', 'woo-whatsapp'),
                'icon' => 'git-branch',
                'color' => '#ffc107',
                'config_fields' => []
            ],
            'add_order_note' => [
                'name' => __('Sipariş Notu Ekle', 'woo-whatsapp'),
                'description' => __('Siparişe not ekle', 'woo-whatsapp'),
                'icon' => 'file-text',
                'color' => '#6f42c1',
                'config_fields' => [
                    'note' => [
                        'type' => 'textarea',
                        'label' => __('Not', 'woo-whatsapp')
                    ]
                ]
            ],
            'apply_coupon' => [
                'name' => __('Kupon Oluştur', 'woo-whatsapp'),
                'description' => __('Özel indirim kuponu oluştur', 'woo-whatsapp'),
                'icon' => 'tag',
                'color' => '#28a745',
                'config_fields' => [
                    'discount_type' => [
                        'type' => 'select',
                        'label' => __('İndirim Tipi', 'woo-whatsapp'),
                        'options' => [
                            'percent' => __('Yüzde (%)', 'woo-whatsapp'),
                            'fixed_cart' => __('Sabit Tutar', 'woo-whatsapp')
                        ]
                    ],
                    'discount_amount' => [
                        'type' => 'number',
                        'label' => __('İndirim Miktarı', 'woo-whatsapp')
                    ]
                ]
            ]
        ];

        return apply_filters('wwa_flow_actions', $actions);
    }

    /**
     * Tabloları oluştur
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_flows = $wpdb->prefix . 'wwa_flows';
        $sql_flows = "CREATE TABLE IF NOT EXISTS $table_flows (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            trigger_type varchar(100) NOT NULL,
            trigger_config longtext,
            nodes longtext NOT NULL,
            edges longtext,
            status varchar(20) DEFAULT 'active',
            priority int(11) DEFAULT 10,
            run_count bigint(20) DEFAULT 0,
            last_run datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trigger_type (trigger_type),
            KEY status (status)
        ) $charset_collate;";

        $table_flow_logs = $wpdb->prefix . 'wwa_flow_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_flow_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            flow_id bigint(20) NOT NULL,
            trigger_data longtext,
            execution_path longtext,
            status varchar(20) DEFAULT 'completed',
            error_message text,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY flow_id (flow_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_flows);
        dbDelta($sql_logs);
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        add_action('woocommerce_order_status_changed', [$this, 'trigger_order_status_changed'], 10, 4);

        add_action('woocommerce_new_order', [$this, 'trigger_new_order'], 10, 2);

        add_action('wwa_check_abandoned_carts', [$this, 'check_abandoned_carts']);
        if (!wp_next_scheduled('wwa_check_abandoned_carts')) {
            wp_schedule_event(time(), 'hourly', 'wwa_check_abandoned_carts');
        }

        add_action('wwa_process_scheduled_messages', [$this, 'process_scheduled_messages']);
        if (!wp_next_scheduled('wwa_process_scheduled_messages')) {
            wp_schedule_event(time(), 'every_five_minutes', 'wwa_process_scheduled_messages');
        }

        add_filter('cron_schedules', [$this, 'add_cron_intervals']);

        add_action('woocommerce_product_set_stock_status', [$this, 'trigger_back_in_stock'], 10, 3);

        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
    }

    /**
     * Cron interval ekle
     */
    public function add_cron_intervals($schedules) {
        $schedules['every_five_minutes'] = [
            'interval' => 300,
            'display' => __('Her 5 dakikada bir', 'woo-whatsapp')
        ];
        return $schedules;
    }

    /**
     * Trigger'ları kaydet
     */
    private function register_triggers() {
        $this->triggers = [
            'order_created' => [
                'name' => __('Yeni Sipariş Oluşturuldu', 'woo-whatsapp'),
                'description' => __('Yeni bir sipariş geldiğinde tetiklenir', 'woo-whatsapp'),
                'icon' => 'shopping-cart',
                'category' => 'order',
                'config_fields' => []
            ],
            'order_status_changed' => [
                'name' => __('Sipariş Durumu Değişti', 'woo-whatsapp'),
                'description' => __('Sipariş durumu belirli bir duruma geçtiğinde tetiklenir', 'woo-whatsapp'),
                'icon' => 'refresh',
                'category' => 'order',
                'config_fields' => [
                    'from_status' => [
                        'type' => 'select',
                        'label' => __('Eski Durum', 'woo-whatsapp'),
                        'options' => 'order_statuses',
                        'multiple' => true,
                        'required' => false
                    ],
                    'to_status' => [
                        'type' => 'select',
                        'label' => __('Yeni Durum', 'woo-whatsapp'),
                        'options' => 'order_statuses',
                        'multiple' => true,
                        'required' => true
                    ]
                ]
            ],
            'abandoned_cart' => [
                'name' => __('Sepet Terk Edildi', 'woo-whatsapp'),
                'description' => __('Müşteri sepetini terk ettiğinde tetiklenir', 'woo-whatsapp'),
                'icon' => 'shopping-bag',
                'category' => 'cart',
                'config_fields' => [
                    'wait_time' => [
                        'type' => 'number',
                        'label' => __('Bekleme Süresi (Dakika)', 'woo-whatsapp'),
                        'default' => 60,
                        'min' => 15,
                        'required' => true
                    ],
                    'min_cart_total' => [
                        'type' => 'number',
                        'label' => __('Minimum Sepet Tutarı', 'woo-whatsapp'),
                        'default' => 0,
                        'required' => false
                    ]
                ]
            ],
            'product_back_in_stock' => [
                'name' => __('Ürün Stoğa Girdi', 'woo-whatsapp'),
                'description' => __('Stokta olmayan bir ürün tekrar stoğa girdiğinde', 'woo-whatsapp'),
                'icon' => 'package',
                'category' => 'product',
                'config_fields' => [
                    'product_ids' => [
                        'type' => 'product_select',
                        'label' => __('Ürünler (Boş = Tümü)', 'woo-whatsapp'),
                        'multiple' => true,
                        'required' => false
                    ]
                ]
            ],
            'customer_birthday' => [
                'name' => __('Müşteri Doğum Günü', 'woo-whatsapp'),
                'description' => __('Müşterinin doğum gününde tetiklenir', 'woo-whatsapp'),
                'icon' => 'gift',
                'category' => 'customer',
                'config_fields' => [
                    'days_before' => [
                        'type' => 'number',
                        'label' => __('Kaç Gün Önce', 'woo-whatsapp'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 7
                    ]
                ]
            ],
            'webhook_received' => [
                'name' => __('Webhook Alındı', 'woo-whatsapp'),
                'description' => __('Harici bir sistemden webhook geldiğinde', 'woo-whatsapp'),
                'icon' => 'link',
                'category' => 'integration',
                'config_fields' => [
                    'webhook_key' => [
                        'type' => 'text',
                        'label' => __('Webhook Anahtarı', 'woo-whatsapp'),
                        'readonly' => true,
                        'generated' => true
                    ]
                ]
            ],
            'scheduled' => [
                'name' => __('Zamanlanmış', 'woo-whatsapp'),
                'description' => __('Belirli bir zamanda otomatik çalışır', 'woo-whatsapp'),
                'icon' => 'clock',
                'category' => 'time',
                'config_fields' => [
                    'schedule_type' => [
                        'type' => 'select',
                        'label' => __('Zamanlama Tipi', 'woo-whatsapp'),
                        'options' => [
                            'once' => __('Bir Kez', 'woo-whatsapp'),
                            'daily' => __('Her Gün', 'woo-whatsapp'),
                            'weekly' => __('Her Hafta', 'woo-whatsapp'),
                            'monthly' => __('Her Ay', 'woo-whatsapp')
                        ]
                    ],
                    'schedule_time' => [
                        'type' => 'time',
                        'label' => __('Saat', 'woo-whatsapp')
                    ],
                    'schedule_date' => [
                        'type' => 'date',
                        'label' => __('Tarih', 'woo-whatsapp'),
                        'condition' => ['schedule_type' => 'once']
                    ],
                    'schedule_days' => [
                        'type' => 'multiselect',
                        'label' => __('Günler', 'woo-whatsapp'),
                        'options' => [
                            'mon' => __('Pazartesi', 'woo-whatsapp'),
                            'tue' => __('Salı', 'woo-whatsapp'),
                            'wed' => __('Çarşamba', 'woo-whatsapp'),
                            'thu' => __('Perşembe', 'woo-whatsapp'),
                            'fri' => __('Cuma', 'woo-whatsapp'),
                            'sat' => __('Cumartesi', 'woo-whatsapp'),
                            'sun' => __('Pazar', 'woo-whatsapp')
                        ],
                        'condition' => ['schedule_type' => 'weekly']
                    ]
                ]
            ],
            'whatsapp_reply' => [
                'name' => __('WhatsApp Yanıtı Alındı', 'woo-whatsapp'),
                'description' => __('Müşteri WhatsApp\'tan yanıt verdiğinde', 'woo-whatsapp'),
                'icon' => 'message-circle',
                'category' => 'whatsapp',
                'config_fields' => [
                    'keywords' => [
                        'type' => 'tags',
                        'label' => __('Anahtar Kelimeler', 'woo-whatsapp'),
                        'placeholder' => __('Örn: kargo, sipariş, iade', 'woo-whatsapp')
                    ]
                ]
            ]
        ];
    }

    /**
     * Action'ları kaydet
     */
    private function register_actions() {
        $this->actions = [
            'send_whatsapp' => [
                'name' => __('WhatsApp Mesajı Gönder', 'woo-whatsapp'),
                'description' => __('Müşteriye WhatsApp mesajı gönder', 'woo-whatsapp'),
                'icon' => 'message-square',
                'color' => '#25D366',
                'config_fields' => [
                    'recipient' => [
                        'type' => 'select',
                        'label' => __('Alıcı', 'woo-whatsapp'),
                        'options' => [
                            'customer' => __('Müşteri', 'woo-whatsapp'),
                            'admin' => __('Admin', 'woo-whatsapp'),
                            'custom' => __('Özel Numara', 'woo-whatsapp')
                        ],
                        'default' => 'customer'
                    ],
                    'custom_phone' => [
                        'type' => 'text',
                        'label' => __('Telefon Numarası', 'woo-whatsapp'),
                        'condition' => ['recipient' => 'custom']
                    ],
                    'message_template' => [
                        'type' => 'textarea',
                        'label' => __('Mesaj Şablonu', 'woo-whatsapp'),
                        'rows' => 5,
                        'placeholder' => __('Placeholder kullanabilirsiniz: {müşteri_adı}, {sipariş_no}...', 'woo-whatsapp')
                    ],
                    'media_url' => [
                        'type' => 'url',
                        'label' => __('Medya URL (Opsiyonel)', 'woo-whatsapp'),
                        'placeholder' => 'https://...'
                    ]
                ]
            ],
            'wait' => [
                'name' => __('Bekle', 'woo-whatsapp'),
                'description' => __('Belirli bir süre bekle', 'woo-whatsapp'),
                'icon' => 'clock',
                'color' => '#6c757d',
                'config_fields' => [
                    'wait_time' => [
                        'type' => 'number',
                        'label' => __('Süre', 'woo-whatsapp'),
                        'default' => 60
                    ],
                    'wait_unit' => [
                        'type' => 'select',
                        'label' => __('Birim', 'woo-whatsapp'),
                        'options' => [
                            'minutes' => __('Dakika', 'woo-whatsapp'),
                            'hours' => __('Saat', 'woo-whatsapp'),
                            'days' => __('Gün', 'woo-whatsapp')
                        ],
                        'default' => 'minutes'
                    ]
                ]
            ],
            'condition' => [
                'name' => __('Koşul Kontrolü', 'woo-whatsapp'),
                'description' => __('Belirli bir koşulu kontrol et', 'woo-whatsapp'),
                'icon' => 'git-branch',
                'color' => '#ffc107',
                'config_fields' => [
                    'condition_type' => [
                        'type' => 'select',
                        'label' => __('Koşul Tipi', 'woo-whatsapp'),
                        'options' => 'conditions'
                    ],
                    'condition_config' => [
                        'type' => 'dynamic',
                        'depends_on' => 'condition_type'
                    ]
                ]
            ],
            'update_order_meta' => [
                'name' => __('Sipariş Meta Güncelle', 'woo-whatsapp'),
                'description' => __('Siparişe özel veri ekle', 'woo-whatsapp'),
                'icon' => 'edit',
                'color' => '#17a2b8',
                'config_fields' => [
                    'meta_key' => [
                        'type' => 'text',
                        'label' => __('Meta Anahtarı', 'woo-whatsapp')
                    ],
                    'meta_value' => [
                        'type' => 'text',
                        'label' => __('Meta Değeri', 'woo-whatsapp')
                    ]
                ]
            ],
            'add_order_note' => [
                'name' => __('Sipariş Notu Ekle', 'woo-whatsapp'),
                'description' => __('Siparişe not ekle', 'woo-whatsapp'),
                'icon' => 'file-text',
                'color' => '#6f42c1',
                'config_fields' => [
                    'note' => [
                        'type' => 'textarea',
                        'label' => __('Not', 'woo-whatsapp')
                    ],
                    'is_customer_note' => [
                        'type' => 'checkbox',
                        'label' => __('Müşteriye Göster', 'woo-whatsapp'),
                        'default' => false
                    ]
                ]
            ],
            'apply_coupon' => [
                'name' => __('Kupon Oluştur ve Gönder', 'woo-whatsapp'),
                'description' => __('Özel indirim kuponu oluştur', 'woo-whatsapp'),
                'icon' => 'tag',
                'color' => '#28a745',
                'config_fields' => [
                    'discount_type' => [
                        'type' => 'select',
                        'label' => __('İndirim Tipi', 'woo-whatsapp'),
                        'options' => [
                            'percent' => __('Yüzde (%)', 'woo-whatsapp'),
                            'fixed_cart' => __('Sabit Tutar', 'woo-whatsapp')
                        ]
                    ],
                    'discount_amount' => [
                        'type' => 'number',
                        'label' => __('İndirim Miktarı', 'woo-whatsapp')
                    ],
                    'expiry_days' => [
                        'type' => 'number',
                        'label' => __('Geçerlilik Süresi (Gün)', 'woo-whatsapp'),
                        'default' => 7
                    ],
                    'usage_limit' => [
                        'type' => 'number',
                        'label' => __('Kullanım Limiti', 'woo-whatsapp'),
                        'default' => 1
                    ],
                    'minimum_amount' => [
                        'type' => 'number',
                        'label' => __('Minimum Sipariş Tutarı', 'woo-whatsapp'),
                        'default' => 0
                    ]
                ]
            ],
            'send_email' => [
                'name' => __('E-posta Gönder', 'woo-whatsapp'),
                'description' => __('E-posta bildirimi gönder', 'woo-whatsapp'),
                'icon' => 'mail',
                'color' => '#dc3545',
                'config_fields' => [
                    'email_to' => [
                        'type' => 'select',
                        'label' => __('Alıcı', 'woo-whatsapp'),
                        'options' => [
                            'customer' => __('Müşteri', 'woo-whatsapp'),
                            'admin' => __('Admin', 'woo-whatsapp'),
                            'custom' => __('Özel E-posta', 'woo-whatsapp')
                        ]
                    ],
                    'custom_email' => [
                        'type' => 'email',
                        'label' => __('E-posta Adresi', 'woo-whatsapp'),
                        'condition' => ['email_to' => 'custom']
                    ],
                    'email_subject' => [
                        'type' => 'text',
                        'label' => __('Konu', 'woo-whatsapp')
                    ],
                    'email_body' => [
                        'type' => 'richtext',
                        'label' => __('İçerik', 'woo-whatsapp')
                    ]
                ]
            ],
            'webhook_send' => [
                'name' => __('Webhook Gönder', 'woo-whatsapp'),
                'description' => __('Harici bir sisteme veri gönder', 'woo-whatsapp'),
                'icon' => 'send',
                'color' => '#20c997',
                'config_fields' => [
                    'webhook_url' => [
                        'type' => 'url',
                        'label' => __('Webhook URL', 'woo-whatsapp'),
                        'required' => true
                    ],
                    'webhook_method' => [
                        'type' => 'select',
                        'label' => __('Method', 'woo-whatsapp'),
                        'options' => ['POST' => 'POST', 'GET' => 'GET', 'PUT' => 'PUT']
                    ],
                    'webhook_headers' => [
                        'type' => 'keyvalue',
                        'label' => __('Headers', 'woo-whatsapp')
                    ],
                    'webhook_body' => [
                        'type' => 'json',
                        'label' => __('Body (JSON)', 'woo-whatsapp')
                    ]
                ]
            ],
            'stop_flow' => [
                'name' => __('Akışı Durdur', 'woo-whatsapp'),
                'description' => __('Bu noktada akışı sonlandır', 'woo-whatsapp'),
                'icon' => 'x-circle',
                'color' => '#dc3545',
                'config_fields' => [
                    'reason' => [
                        'type' => 'text',
                        'label' => __('Durdurma Sebebi (Log için)', 'woo-whatsapp')
                    ]
                ]
            ]
        ];
    }

    /**
     * Condition'ları kaydet
     */
    private function register_conditions() {
        $this->conditions = [
            'order_total' => [
                'name' => __('Sipariş Tutarı', 'woo-whatsapp'),
                'fields' => [
                    'operator' => ['>', '<', '>=', '<=', '=='],
                    'value' => 'number'
                ]
            ],
            'order_item_count' => [
                'name' => __('Ürün Adedi', 'woo-whatsapp'),
                'fields' => [
                    'operator' => ['>', '<', '>=', '<=', '=='],
                    'value' => 'number'
                ]
            ],
            'customer_order_count' => [
                'name' => __('Müşteri Sipariş Sayısı', 'woo-whatsapp'),
                'fields' => [
                    'operator' => ['>', '<', '>=', '<=', '=='],
                    'value' => 'number'
                ]
            ],
            'product_in_order' => [
                'name' => __('Siparişte Ürün Var', 'woo-whatsapp'),
                'fields' => [
                    'product_ids' => 'product_select'
                ]
            ],
            'category_in_order' => [
                'name' => __('Siparişte Kategori Var', 'woo-whatsapp'),
                'fields' => [
                    'category_ids' => 'category_select'
                ]
            ],
            'payment_method' => [
                'name' => __('Ödeme Yöntemi', 'woo-whatsapp'),
                'fields' => [
                    'methods' => 'payment_methods'
                ]
            ],
            'shipping_method' => [
                'name' => __('Kargo Yöntemi', 'woo-whatsapp'),
                'fields' => [
                    'methods' => 'shipping_methods'
                ]
            ],
            'customer_country' => [
                'name' => __('Müşteri Ülkesi', 'woo-whatsapp'),
                'fields' => [
                    'countries' => 'country_select'
                ]
            ],
            'is_first_order' => [
                'name' => __('İlk Sipariş mi?', 'woo-whatsapp'),
                'fields' => []
            ],
            'coupon_used' => [
                'name' => __('Kupon Kullanıldı mı?', 'woo-whatsapp'),
                'fields' => [
                    'coupon_code' => 'text'
                ]
            ],
            'time_of_day' => [
                'name' => __('Günün Saati', 'woo-whatsapp'),
                'fields' => [
                    'from_time' => 'time',
                    'to_time' => 'time'
                ]
            ],
            'day_of_week' => [
                'name' => __('Haftanın Günü', 'woo-whatsapp'),
                'fields' => [
                    'days' => 'weekdays'
                ]
            ]
        ];
    }

    /**
     * Trigger'ları getir
     */
    public function get_triggers() {
        return apply_filters('wwa_flow_triggers', $this->triggers);
    }

    /**
     * Action'ları getir
     */
    public function get_actions() {
        return apply_filters('wwa_flow_actions', $this->actions);
    }

    /**
     * Condition'ları getir
     */
    public function get_conditions() {
        return apply_filters('wwa_flow_conditions', $this->conditions);
    }

    /**
     * Flow kaydet
     */
    public function save_flow($data) {
        global $wpdb;

        $status = 'active';
        if (isset($data['is_active'])) {
            $status = ($data['is_active'] === true || $data['is_active'] === '1' || $data['is_active'] === 1) ? 'active' : 'inactive';
        }
        if (isset($data['status'])) {
            $status = sanitize_text_field($data['status']);
        }

        $flow_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'trigger_type' => sanitize_text_field($data['trigger_type'] ?? 'order_status'),
            'trigger_config' => wp_json_encode($data['trigger_config'] ?? []),
            'nodes' => wp_json_encode($data['nodes'] ?? []),
            'edges' => wp_json_encode($data['edges'] ?? []),
            'status' => $status,
            'priority' => absint($data['priority'] ?? 10)
        ];

        if (!empty($data['id'])) {
            $wpdb->update(
                $this->table_flows,
                $flow_data,
                ['id' => absint($data['id'])],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'],
                ['%d']
            );
            return absint($data['id']);
        } else {
            $flows = get_option('wwa_flows_data', []);

            $max_id = 0;
            foreach ($flows as $f) {
                if (isset($f['id']) && $f['id'] > $max_id) {
                    $max_id = $f['id'];
                }
            }
            $new_id = $max_id + 1;

            $flow_data['id'] = $new_id;
            $flow_data['created_at'] = current_time('mysql');
            $flow_data['updated_at'] = current_time('mysql');
            $flow_data['run_count'] = 0;
            $flow_data['last_run'] = null;

            $flows[$new_id] = $flow_data;
            update_option('wwa_flows_data', $flows, true);

            wp_cache_delete('wwa_flows_data', 'options');
            wp_cache_delete('alloptions', 'options');

            return $new_id;
        }
    }

    /**
     * Yeni flow oluştur (REST API için)
     */
    public function create_flow($data) {
        return $this->save_flow($data);
    }

    /**
     * Flow güncelle (REST API için)
     */
    public function update_flow($id, $data) {
        $data['id'] = $id;
        return $this->save_flow($data);
    }

    /**
     * Flow durumunu değiştir (aktif/pasif) - wp_options kullan
     */
    public function toggle_flow($id) {
        $flows = get_option('wwa_flows_data', []);

        if (!isset($flows[$id])) {
            return false;
        }

        $current = $flows[$id]['status'] ?? 'inactive';
        $new_status = ($current === 'active') ? 'inactive' : 'active';

        $flows[$id]['status'] = $new_status;
        $flows[$id]['updated_at'] = current_time('mysql');

        update_option('wwa_flows_data', $flows, false);

        return $new_status;
    }

    /**
     * Flow kopyala - wp_options kullan
     */
    public function duplicate_flow($id) {
        $flows = get_option('wwa_flows_data', []);

        if (!isset($flows[$id])) {
            return false;
        }

        $flow = $flows[$id];

        $max_id = 0;
        foreach ($flows as $f) {
            if (isset($f['id']) && $f['id'] > $max_id) {
                $max_id = $f['id'];
            }
        }
        $new_id = $max_id + 1;

        $new_flow = [
            'id' => $new_id,
            'name' => $flow['name'] . ' (Kopya)',
            'description' => $flow['description'] ?? '',
            'trigger_type' => $flow['trigger_type'],
            'trigger_config' => $flow['trigger_config'] ?? [],
            'nodes' => $flow['nodes'] ?? [],
            'edges' => $flow['edges'] ?? [],
            'status' => 'inactive',
            'priority' => $flow['priority'] ?? 10,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'run_count' => 0,
            'last_run' => null
        ];

        $flows[$new_id] = $new_flow;
        update_option('wwa_flows_data', $flows, false);

        return $new_id;
    }

    /**
     * Tek flow getir - wp_options kullan
     */
    public function get_flow($id) {
        $flows = get_option('wwa_flows_data', []);

        if (isset($flows[$id])) {
            return $flows[$id];
        }

        return null;
    }

    /**
     * Tüm flow'ları getir - wp_options kullan
     */
    public function get_flows($args = []) {
        $defaults = [
            'status' => null,
            'trigger_type' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0,
            'per_page' => null,
            'page' => null
        ];

        $args = wp_parse_args($args, $defaults);

        $all_flows = get_option('wwa_flows_data', []);

        $unique_flows = [];
        $seen_names = [];
        foreach ($all_flows as $id => $flow) {
            $name = $flow['name'] ?? '';
            if (!empty($name) && in_array($name, $seen_names)) {
                continue; // Duplicate, atla
            }
            if (!empty($name)) {
                $seen_names[] = $name;
            }
            $unique_flows[$id] = $flow;
        }

        $flows = array_values($unique_flows);

        if ($args['status']) {
            $flows = array_filter($flows, function($f) use ($args) {
                return isset($f['status']) && $f['status'] === $args['status'];
            });
        }

        if ($args['trigger_type']) {
            $flows = array_filter($flows, function($f) use ($args) {
                return isset($f['trigger_type']) && $f['trigger_type'] === $args['trigger_type'];
            });
        }

        usort($flows, function($a, $b) use ($args) {
            $field = $args['orderby'];
            $aVal = $a[$field] ?? '';
            $bVal = $b[$field] ?? '';
            $cmp = strcmp($aVal, $bVal);
            return $args['order'] === 'DESC' ? -$cmp : $cmp;
        });

        if ($args['per_page'] !== null) {
            $args['limit'] = absint($args['per_page']);
        }
        if ($args['page'] !== null && $args['per_page'] !== null) {
            $args['offset'] = (absint($args['page']) - 1) * absint($args['per_page']);
        }

        $flows = array_slice($flows, $args['offset'], $args['limit']);

        foreach ($flows as &$flow) {
            if (is_string($flow['trigger_config'] ?? '')) {
                $flow['trigger_config'] = json_decode($flow['trigger_config'], true);
            }
            if (is_string($flow['nodes'] ?? '')) {
                $flow['nodes'] = json_decode($flow['nodes'], true);
            }
            if (is_string($flow['edges'] ?? '')) {
                $flow['edges'] = json_decode($flow['edges'], true);
            }
        }

        return $flows;
    }

    /**
     * Flow sayısını getir - wp_options kullan
     */
    public function get_flows_count($args = []) {
        $all_flows = get_option('wwa_flows_data', []);
        $flows = array_values($all_flows);

        if (!empty($args['status'])) {
            $flows = array_filter($flows, function($f) use ($args) {
                return isset($f['status']) && $f['status'] === $args['status'];
            });
        }

        if (!empty($args['trigger_type'])) {
            $flows = array_filter($flows, function($f) use ($args) {
                return isset($f['trigger_type']) && $f['trigger_type'] === $args['trigger_type'];
            });
        }

        return count($flows);
    }

    /**
     * Flow sil - wp_options kullan
     */
    public function delete_flow($id) {
        $flows = get_option('wwa_flows_data', []);

        if (isset($flows[$id])) {
            unset($flows[$id]);
            update_option('wwa_flows_data', $flows, false);
            return true;
        }

        return false;
    }

    /**
     * Sipariş durumu değişti trigger
     */
    public function trigger_order_status_changed($order_id, $old_status, $new_status, $order) {
        $flows = $this->get_flows([
            'status' => 'active',
            'trigger_type' => 'order_status_changed'
        ]);

        foreach ($flows as $flow) {
            $config = $flow['trigger_config'];

            $from_statuses = $config['from_status'] ?? [];
            $to_statuses = $config['to_status'] ?? [];

            if (!empty($from_statuses) && !in_array($old_status, $from_statuses)) {
                continue;
            }

            if (!empty($to_statuses) && !in_array($new_status, $to_statuses)) {
                continue;
            }

            $this->execute_flow($flow, [
                'order_id' => $order_id,
                'order' => $order,
                'old_status' => $old_status,
                'new_status' => $new_status
            ]);
        }
    }

    /**
     * Yeni sipariş trigger
     */
    public function trigger_new_order($order_id, $order = null) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }

        $flows = $this->get_flows([
            'status' => 'active',
            'trigger_type' => 'order_created'
        ]);

        foreach ($flows as $flow) {
            $this->execute_flow($flow, [
                'order_id' => $order_id,
                'order' => $order
            ]);
        }
    }

    /**
     * Ürün stoğa girdi trigger
     */
    public function trigger_back_in_stock($product_id, $stock_status, $product) {
        if ($stock_status !== 'instock') {
            return;
        }

        $flows = $this->get_flows([
            'status' => 'active',
            'trigger_type' => 'product_back_in_stock'
        ]);

        foreach ($flows as $flow) {
            $config = $flow['trigger_config'];
            $product_ids = $config['product_ids'] ?? [];

            if (!empty($product_ids) && !in_array($product_id, $product_ids)) {
                continue;
            }

            $waitlist = $this->get_product_waitlist($product_id);

            foreach ($waitlist as $customer) {
                $this->execute_flow($flow, [
                    'product_id' => $product_id,
                    'product' => $product,
                    'customer_email' => $customer['email'],
                    'customer_phone' => $customer['phone'],
                    'customer_name' => $customer['name']
                ]);
            }
        }
    }

    /**
     * Flow çalıştır
     */
    public function execute_flow($flow, $context = []) {
        global $wpdb;

        $log_id = $wpdb->insert($this->table_flow_logs, [
            'flow_id' => $flow['id'],
            'trigger_data' => wp_json_encode($context),
            'status' => 'running'
        ], ['%d', '%s', '%s']);

        $log_id = $wpdb->insert_id;
        $execution_path = [];

        try {
            $nodes = $flow['nodes'];
            $edges = $flow['edges'];

            $start_node = null;
            foreach ($nodes as $node) {
                if ($node['type'] === 'trigger' || $node['type'] === 'start') {
                    $start_node = $node;
                    break;
                }
            }

            if (!$start_node) {
                throw new Exception('Start node bulunamadı');
            }

            $this->execute_node($start_node, $nodes, $edges, $context, $execution_path);

            $wpdb->update(
                $this->table_flow_logs,
                [
                    'status' => 'completed',
                    'execution_path' => wp_json_encode($execution_path),
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $log_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            $all_flows = get_option('wwa_flows_data', []);
            if (isset($all_flows[$flow['id']])) {
                $all_flows[$flow['id']]['run_count'] = ($all_flows[$flow['id']]['run_count'] ?? 0) + 1;
                $all_flows[$flow['id']]['last_run'] = current_time('mysql');
                update_option('wwa_flows_data', $all_flows, false);
            }

        } catch (Exception $e) {
            $wpdb->update(
                $this->table_flow_logs,
                [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'execution_path' => wp_json_encode($execution_path),
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $log_id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            WWA()->logger->debug_log('Flow execution error: ' . $e->getMessage(), [
                'flow_id' => $flow['id'],
                'context' => $context
            ]);
        }

        return $log_id;
    }

    /**
     * Node çalıştır (recursive)
     */
    private function execute_node($node, $nodes, $edges, &$context, &$execution_path) {
        $execution_path[] = [
            'node_id' => $node['id'],
            'type' => $node['type'],
            'timestamp' => current_time('mysql')
        ];

        $result = true;

        switch ($node['type']) {
            case 'trigger':
            case 'start':
                break;

            case 'send_whatsapp':
                $result = $this->action_send_whatsapp($node['data'], $context);
                break;

            case 'wait':
                $result = $this->action_wait($node['data'], $context);
                if ($result === 'scheduled') {
                    return; // Zamanlanmış, devam etme
                }
                break;

            case 'condition':
                $result = $this->check_condition($node['data'], $context);
                break;

            case 'apply_coupon':
                $result = $this->action_apply_coupon($node['data'], $context);
                break;

            case 'add_order_note':
                $result = $this->action_add_order_note($node['data'], $context);
                break;

            case 'send_email':
                $result = $this->action_send_email($node['data'], $context);
                break;

            case 'webhook_send':
                $result = $this->action_webhook_send($node['data'], $context);
                break;

            case 'update_order_meta':
                $result = $this->action_update_order_meta($node['data'], $context);
                break;

            case 'stop_flow':
                return;
        }

        $next_edges = array_filter($edges, function($edge) use ($node, $result) {
            if ($edge['source'] !== $node['id']) {
                return false;
            }

            if ($node['type'] === 'condition') {
                $handle = $edge['sourceHandle'] ?? '';
                if ($result && $handle === 'yes') return true;
                if (!$result && $handle === 'no') return true;
                return false;
            }

            return true;
        });

        foreach ($next_edges as $edge) {
            $next_node = null;
            foreach ($nodes as $n) {
                if ($n['id'] === $edge['target']) {
                    $next_node = $n;
                    break;
                }
            }

            if ($next_node) {
                $this->execute_node($next_node, $nodes, $edges, $context, $execution_path);
            }
        }
    }

    /**
     * WhatsApp mesaj gönder action
     */
    private function action_send_whatsapp($data, &$context) {
        $recipient = $data['recipient'] ?? 'customer';
        $phone = '';

        switch ($recipient) {
            case 'customer':
                if (isset($context['order'])) {
                    $phone = $context['order']->get_billing_phone();
                } elseif (isset($context['customer_phone'])) {
                    $phone = $context['customer_phone'];
                }
                break;
            case 'admin':
                $phone = WWA()->settings->get('admin_phone');
                break;
            case 'custom':
                $phone = $data['custom_phone'] ?? '';
                break;
        }

        if (empty($phone)) {
            return false;
        }

        $message = $data['message_template'] ?? '';

        if (isset($context['order'])) {
            $parser = new WWA_Template_Parser($context['order']);
            $message = $parser->parse($message);
        } else {
            $replacements = [
                '{müşteri_adı}' => $context['customer_name'] ?? '',
                '{ürün_adı}' => isset($context['product']) ? $context['product']->get_name() : '',
                '{kupon_kodu}' => $context['coupon_code'] ?? ''
            ];
            $message = str_replace(array_keys($replacements), array_values($replacements), $message);
        }

        $result = WWA()->api->send_message($phone, $message);

        $context['last_message_result'] = $result;

        return $result['success'] ?? false;
    }

    /**
     * Bekleme action
     */
    private function action_wait($data, &$context) {
        $wait_time = absint($data['wait_time'] ?? 60);
        $wait_unit = $data['wait_unit'] ?? 'minutes';

        $multiplier = [
            'minutes' => 60,
            'hours' => 3600,
            'days' => 86400
        ];

        $seconds = $wait_time * ($multiplier[$wait_unit] ?? 60);

        $scheduled_time = time() + $seconds;

        $this->schedule_flow_continuation($context, $scheduled_time);

        return 'scheduled';
    }

    /**
     * Koşul kontrolü
     */
    private function check_condition($data, &$context) {
        $condition_type = $data['condition_type'] ?? '';
        $config = $data['condition_config'] ?? [];

        switch ($condition_type) {
            case 'order_total':
                if (!isset($context['order'])) return false;
                $total = $context['order']->get_total();
                return $this->compare_value($total, $config['operator'], $config['value']);

            case 'order_item_count':
                if (!isset($context['order'])) return false;
                $count = $context['order']->get_item_count();
                return $this->compare_value($count, $config['operator'], $config['value']);

            case 'is_first_order':
                if (!isset($context['order'])) return false;
                $customer_id = $context['order']->get_customer_id();
                if (!$customer_id) return true; // Guest
                $order_count = wc_get_customer_order_count($customer_id);
                return $order_count <= 1;

            case 'payment_method':
                if (!isset($context['order'])) return false;
                $method = $context['order']->get_payment_method();
                $allowed = $config['methods'] ?? [];
                return in_array($method, $allowed);

            case 'time_of_day':
                $current_hour = (int) current_time('H');
                $from = (int) ($config['from_time'] ?? 0);
                $to = (int) ($config['to_time'] ?? 24);
                return $current_hour >= $from && $current_hour <= $to;

            default:
                return true;
        }
    }

    /**
     * Değer karşılaştırma
     */
    private function compare_value($value, $operator, $compare_to) {
        switch ($operator) {
            case '>': return $value > $compare_to;
            case '<': return $value < $compare_to;
            case '>=': return $value >= $compare_to;
            case '<=': return $value <= $compare_to;
            case '==': return $value == $compare_to;
            case '!=': return $value != $compare_to;
            default: return false;
        }
    }

    /**
     * Kupon oluştur action
     */
    private function action_apply_coupon($data, &$context) {
        $discount_type = $data['discount_type'] ?? 'percent';
        $discount_amount = floatval($data['discount_amount'] ?? 10);
        $expiry_days = absint($data['expiry_days'] ?? 7);
        $usage_limit = absint($data['usage_limit'] ?? 1);
        $minimum_amount = floatval($data['minimum_amount'] ?? 0);

        $coupon_code = 'WWA' . strtoupper(wp_generate_password(8, false));

        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type($discount_type);
        $coupon->set_amount($discount_amount);
        $coupon->set_date_expires(strtotime("+{$expiry_days} days"));
        $coupon->set_usage_limit($usage_limit);
        $coupon->set_usage_limit_per_user(1);
        $coupon->set_minimum_amount($minimum_amount);
        $coupon->set_individual_use(true);

        if (isset($context['order'])) {
            $email = $context['order']->get_billing_email();
            if ($email) {
                $coupon->set_email_restrictions([$email]);
            }
        }

        $coupon->save();

        $context['coupon_code'] = $coupon_code;
        $context['coupon_amount'] = $discount_amount;
        $context['coupon_type'] = $discount_type;

        return true;
    }

    /**
     * Sipariş notu ekle action
     */
    private function action_add_order_note($data, &$context) {
        if (!isset($context['order'])) {
            return false;
        }

        $note = $data['note'] ?? '';
        $is_customer_note = !empty($data['is_customer_note']);

        $parser = new WWA_Template_Parser($context['order']);
        $note = $parser->parse($note);

        $context['order']->add_order_note($note, $is_customer_note);

        return true;
    }

    /**
     * E-posta gönder action
     */
    private function action_send_email($data, &$context) {
        $email_to = $data['email_to'] ?? 'customer';
        $to = '';

        switch ($email_to) {
            case 'customer':
                if (isset($context['order'])) {
                    $to = $context['order']->get_billing_email();
                }
                break;
            case 'admin':
                $to = get_option('admin_email');
                break;
            case 'custom':
                $to = $data['custom_email'] ?? '';
                break;
        }

        if (empty($to)) {
            return false;
        }

        $subject = $data['email_subject'] ?? '';
        $body = $data['email_body'] ?? '';

        if (isset($context['order'])) {
            $parser = new WWA_Template_Parser($context['order']);
            $subject = $parser->parse($subject);
            $body = $parser->parse($body);
        }

        return wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    /**
     * Webhook gönder action
     */
    private function action_webhook_send($data, &$context) {
        $url = $data['webhook_url'] ?? '';
        $method = $data['webhook_method'] ?? 'POST';
        $headers = $data['webhook_headers'] ?? [];
        $body = $data['webhook_body'] ?? '';

        if (empty($url)) {
            return false;
        }

        if (isset($context['order'])) {
            $parser = new WWA_Template_Parser($context['order']);
            $body = $parser->parse($body);
        }

        $args = [
            'method' => $method,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'body' => $body,
            'timeout' => 30
        ];

        $response = wp_remote_request($url, $args);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
    }

    /**
     * Sipariş meta güncelle action
     */
    private function action_update_order_meta($data, &$context) {
        if (!isset($context['order'])) {
            return false;
        }

        $meta_key = sanitize_key($data['meta_key'] ?? '');
        $meta_value = $data['meta_value'] ?? '';

        if (empty($meta_key)) {
            return false;
        }

        $context['order']->update_meta_data($meta_key, $meta_value);
        $context['order']->save();

        return true;
    }

    /**
     * Ürün waitlist'ini getir
     */
    private function get_product_waitlist($product_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'wwa_waitlist';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE product_id = %d AND notified = 0",
            $product_id
        ), ARRAY_A) ?: [];
    }

    /**
     * Flow devamını zamanla
     */
    private function schedule_flow_continuation($context, $scheduled_time) {
        global $wpdb;

        $table = $wpdb->prefix . 'wwa_scheduled_flows';

        $wpdb->insert($table, [
            'context' => wp_json_encode($context),
            'scheduled_at' => date('Y-m-d H:i:s', $scheduled_time),
            'status' => 'pending'
        ]);
    }

    /**
     * Terk edilmiş sepetleri kontrol et
     */
    public function check_abandoned_carts() {
        $flows = $this->get_flows([
            'status' => 'active',
            'trigger_type' => 'abandoned_cart'
        ]);

        if (empty($flows)) {
            return;
        }

        global $wpdb;

        foreach ($flows as $flow) {
            $config = $flow['trigger_config'];
            $wait_time = absint($config['wait_time'] ?? 60);
            $min_total = floatval($config['min_cart_total'] ?? 0);

            $threshold = date('Y-m-d H:i:s', strtotime("-{$wait_time} minutes"));

            $abandoned = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wwa_abandoned_carts
                 WHERE status = 'abandoned'
                 AND cart_total >= %f
                 AND updated_at <= %s
                 AND recovery_sent = 0",
                $min_total,
                $threshold
            ), ARRAY_A);

            foreach ($abandoned as $cart) {
                $this->execute_flow($flow, [
                    'cart_id' => $cart['id'],
                    'customer_email' => $cart['email'],
                    'customer_phone' => $cart['phone'],
                    'customer_name' => $cart['customer_name'],
                    'cart_total' => $cart['cart_total'],
                    'cart_items' => json_decode($cart['cart_contents'], true),
                    'recovery_url' => $this->get_cart_recovery_url($cart['id'])
                ]);

                $wpdb->update(
                    $wpdb->prefix . 'wwa_abandoned_carts',
                    ['recovery_sent' => 1],
                    ['id' => $cart['id']]
                );
            }
        }
    }

    /**
     * Sepet kurtarma URL'i oluştur
     */
    private function get_cart_recovery_url($cart_id) {
        $token = wp_hash($cart_id . AUTH_KEY);
        return add_query_arg([
            'wwa_recover' => $cart_id,
            'token' => $token
        ], wc_get_cart_url());
    }

    /**
     * Webhook endpoint kaydet
     */
    public function register_webhook_endpoint() {
        register_rest_route('wwa/v1', '/webhook/(?P<key>[a-zA-Z0-9]+)', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Webhook işle
     */
    public function handle_webhook($request) {
        $key = $request->get_param('key');
        $data = $request->get_json_params() ?: $request->get_query_params();

        $flows = $this->get_flows([
            'status' => 'active',
            'trigger_type' => 'webhook_received'
        ]);

        foreach ($flows as $flow) {
            $config = $flow['trigger_config'];
            if (($config['webhook_key'] ?? '') === $key) {
                $this->execute_flow($flow, [
                    'webhook_data' => $data,
                    'webhook_key' => $key
                ]);
            }
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Flow istatistikleri
     */
    public function get_flow_stats($flow_id = null) {
        global $wpdb;

        if ($flow_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as total_runs,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    MAX(started_at) as last_run
                 FROM {$this->table_flow_logs}
                 WHERE flow_id = %d",
                $flow_id
            ), ARRAY_A);
        }

        return $wpdb->get_results(
            "SELECT
                flow_id,
                COUNT(*) as total_runs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$this->table_flow_logs}
             GROUP BY flow_id",
            ARRAY_A
        );
    }
}
