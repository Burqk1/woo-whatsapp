<?php
/**
 * Internationalization / Multi-language Support
 *
 * WPML ve Polylang entegrasyonu
 *
 * @package Woo_WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class WWA_I18n {

    /**
     * Aktif çoklu dil eklentisi
     *
     * @var string|null
     */
    private $active_plugin = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->detect_multilingual_plugin();
        $this->init_hooks();
    }

    /**
     * Aktif çoklu dil eklentisini tespit et
     */
    private function detect_multilingual_plugin() {
        if (defined('ICL_SITEPRESS_VERSION') && class_exists('SitePress')) {
            $this->active_plugin = 'wpml';
        }
        elseif (function_exists('pll_current_language') || defined('POLYLANG_VERSION')) {
            $this->active_plugin = 'polylang';
        }
        elseif (class_exists('TRP_Translate_Press')) {
            $this->active_plugin = 'translatepress';
        }
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        add_filter('wwa_get_template', [$this, 'get_translated_template'], 10, 2);

        add_filter('wwa_template_settings', [$this, 'add_language_options']);

        add_filter('wwa_rest_settings', [$this, 'add_i18n_settings']);

        add_filter('wwa_order_language', [$this, 'get_order_language'], 10, 2);

        add_action('wwa_before_send_message', [$this, 'switch_to_order_language'], 10, 2);
        add_action('wwa_after_send_message', [$this, 'restore_language'], 10, 2);
    }

    /**
     * Aktif çoklu dil eklentisini getir
     *
     * @return string|null
     */
    public function get_active_plugin() {
        return $this->active_plugin;
    }

    /**
     * Çoklu dil desteği aktif mi?
     *
     * @return bool
     */
    public function is_multilingual() {
        return $this->active_plugin !== null;
    }

    /**
     * Mevcut dilleri getir
     *
     * @return array
     */
    public function get_languages() {
        $languages = [];

        switch ($this->active_plugin) {
            case 'wpml':
                $languages = $this->get_wpml_languages();
                break;

            case 'polylang':
                $languages = $this->get_polylang_languages();
                break;

            case 'translatepress':
                $languages = $this->get_translatepress_languages();
                break;

            default:
                $languages = [
                    [
                        'code' => get_locale(),
                        'name' => $this->get_language_name(get_locale()),
                        'native_name' => $this->get_language_name(get_locale()),
                        'default' => true,
                        'flag' => ''
                    ]
                ];
        }

        return $languages;
    }

    /**
     * WPML dillerini getir
     *
     * @return array
     */
    private function get_wpml_languages() {
        if (!function_exists('icl_get_languages')) {
            return [];
        }

        $wpml_languages = icl_get_languages('skip_missing=0');
        $languages = [];

        foreach ($wpml_languages as $code => $lang) {
            $languages[] = [
                'code' => $code,
                'name' => $lang['english_name'],
                'native_name' => $lang['native_name'],
                'default' => $lang['active'] && $lang['code'] === apply_filters('wpml_default_language', null),
                'flag' => $lang['country_flag_url'] ?? ''
            ];
        }

        return $languages;
    }

    /**
     * Polylang dillerini getir
     *
     * @return array
     */
    private function get_polylang_languages() {
        if (!function_exists('pll_languages_list')) {
            return [];
        }

        $pll_languages = pll_languages_list(['fields' => []]);
        $default_lang = pll_default_language();
        $languages = [];

        foreach ($pll_languages as $lang) {
            $languages[] = [
                'code' => $lang->slug,
                'name' => $lang->name,
                'native_name' => $lang->name,
                'default' => $lang->slug === $default_lang,
                'flag' => $lang->flag_url ?? ''
            ];
        }

        return $languages;
    }

    /**
     * TranslatePress dillerini getir
     *
     * @return array
     */
    private function get_translatepress_languages() {
        $trp_settings = get_option('trp_settings', []);
        $languages = [];

        if (isset($trp_settings['publish-languages'])) {
            foreach ($trp_settings['publish-languages'] as $code) {
                $languages[] = [
                    'code' => $code,
                    'name' => $this->get_language_name($code),
                    'native_name' => $this->get_language_name($code),
                    'default' => $code === ($trp_settings['default-language'] ?? $code),
                    'flag' => ''
                ];
            }
        }

        return $languages;
    }

    /**
     * Dil kodundan dil adını getir
     *
     * @param string $code Dil kodu
     * @return string
     */
    private function get_language_name($code) {
        $language_names = [
            'tr_TR' => 'Türkçe',
            'tr' => 'Türkçe',
            'en_US' => 'English',
            'en_GB' => 'English (UK)',
            'en' => 'English',
            'de_DE' => 'Deutsch',
            'de' => 'Deutsch',
            'fr_FR' => 'Français',
            'fr' => 'Français',
            'es_ES' => 'Español',
            'es' => 'Español',
            'it_IT' => 'Italiano',
            'it' => 'Italiano',
            'pt_BR' => 'Português (Brasil)',
            'pt_PT' => 'Português',
            'nl_NL' => 'Nederlands',
            'nl' => 'Nederlands',
            'ru_RU' => 'Русский',
            'ru' => 'Русский',
            'ar' => 'العربية',
            'zh_CN' => '中文 (简体)',
            'zh_TW' => '中文 (繁體)',
            'ja' => '日本語',
            'ko_KR' => '한국어'
        ];

        return $language_names[$code] ?? $code;
    }

    /**
     * Mevcut dili getir
     *
     * @return string
     */
    public function get_current_language() {
        switch ($this->active_plugin) {
            case 'wpml':
                return apply_filters('wpml_current_language', null) ?: get_locale();

            case 'polylang':
                return pll_current_language() ?: get_locale();

            case 'translatepress':
                global $TRP_LANGUAGE;
                return $TRP_LANGUAGE ?: get_locale();

            default:
                return get_locale();
        }
    }

    /**
     * Varsayılan dili getir
     *
     * @return string
     */
    public function get_default_language() {
        switch ($this->active_plugin) {
            case 'wpml':
                return apply_filters('wpml_default_language', null) ?: get_locale();

            case 'polylang':
                return pll_default_language() ?: get_locale();

            case 'translatepress':
                $trp_settings = get_option('trp_settings', []);
                return $trp_settings['default-language'] ?? get_locale();

            default:
                return get_locale();
        }
    }

    /**
     * Siparişin dilini getir
     *
     * @param string $language Varsayılan dil
     * @param WC_Order $order Sipariş objesi
     * @return string
     */
    public function get_order_language($language, $order) {
        if (!$order) {
            return $language;
        }

        switch ($this->active_plugin) {
            case 'wpml':
                $order_language = $order->get_meta('wpml_language');
                if ($order_language) {
                    return $order_language;
                }
                break;

            case 'polylang':
                $order_language = $order->get_meta('_pll_language');
                if ($order_language) {
                    return $order_language;
                }
                if (function_exists('pll_get_post_language')) {
                    $lang = pll_get_post_language($order->get_id());
                    if ($lang) {
                        return $lang;
                    }
                }
                break;

            case 'translatepress':
                $customer_locale = $order->get_meta('_locale');
                if ($customer_locale) {
                    return $customer_locale;
                }
                break;
        }

        $wc_locale = $order->get_meta('_order_locale');
        if ($wc_locale) {
            return $wc_locale;
        }

        return $language;
    }

    /**
     * Belirli bir dile geçiş yap
     *
     * @param string $language Dil kodu
     */
    public function switch_language($language) {
        if (!$language) {
            return;
        }

        switch ($this->active_plugin) {
            case 'wpml':
                do_action('wpml_switch_language', $language);
                break;

            case 'polylang':
                if (function_exists('PLL')) {
                    PLL()->curlang = PLL()->model->get_language($language);
                }
                break;

            case 'translatepress':
                global $TRP_LANGUAGE;
                $TRP_LANGUAGE = $language;
                break;
        }

        // WordPress locale'ini değiştir
        switch_to_locale($language);
    }

    /**
     * Önceki dile geri dön
     */
    public function restore_language() {
        restore_previous_locale();

        switch ($this->active_plugin) {
            case 'wpml':
                do_action('wpml_switch_language', $this->get_default_language());
                break;

            case 'polylang':
                if (function_exists('PLL')) {
                    PLL()->curlang = PLL()->model->get_language(pll_default_language());
                }
                break;
        }
    }

    /**
     * Mesaj göndermeden önce sipariş diline geç
     *
     * @param string $phone Telefon numarası
     * @param WC_Order $order Sipariş objesi
     */
    public function switch_to_order_language($phone, $order) {
        if (!$order || !$this->is_multilingual()) {
            return;
        }

        $order_language = apply_filters('wwa_order_language', $this->get_default_language(), $order);
        $this->switch_language($order_language);
    }

    /**
     * Çevrilmiş şablonu getir
     *
     * @param string $template Şablon
     * @param string $status Sipariş durumu
     * @return string
     */
    public function get_translated_template($template, $status) {
        if (!$this->is_multilingual()) {
            return $template;
        }

        $current_lang = $this->get_current_language();
        $templates = get_option('wwa_templates', []);

        $lang_key = $status . '_' . $current_lang;
        if (isset($templates[$lang_key]) && !empty($templates[$lang_key])) {
            return $templates[$lang_key];
        }

        return $template;
    }

    /**
     * Şablon kaydetme için dil seçenekleri ekle
     *
     * @param array $settings Ayarlar
     * @return array
     */
    public function add_language_options($settings) {
        if (!$this->is_multilingual()) {
            return $settings;
        }

        $settings['languages'] = $this->get_languages();
        $settings['current_language'] = $this->get_current_language();
        $settings['multilingual_plugin'] = $this->active_plugin;

        return $settings;
    }

    /**
     * REST API ayarlarına i18n bilgisi ekle
     *
     * @param array $settings Ayarlar
     * @return array
     */
    public function add_i18n_settings($settings) {
        $settings['i18n'] = [
            'enabled' => $this->is_multilingual(),
            'plugin' => $this->active_plugin,
            'languages' => $this->get_languages(),
            'current' => $this->get_current_language(),
            'default' => $this->get_default_language()
        ];

        return $settings;
    }

    /**
     * Belirli bir dil için şablon getir veya kaydet
     *
     * @param string $status Sipariş durumu
     * @param string $language Dil kodu
     * @param string|null $template Kaydedilecek şablon (null ise sadece getir)
     * @return string|bool
     */
    public function template_for_language($status, $language, $template = null) {
        $templates = get_option('wwa_templates', []);
        $key = $status . '_' . $language;

        if ($template !== null) {
            $templates[$key] = $template;
            return update_option('wwa_templates', $templates);
        }

        if (isset($templates[$key]) && !empty($templates[$key])) {
            return $templates[$key];
        }

        return $templates[$status] ?? '';
    }

    /**
     * Tüm diller için şablonları getir
     *
     * @param string $status Sipariş durumu
     * @return array
     */
    public function get_all_language_templates($status) {
        $templates = get_option('wwa_templates', []);
        $languages = $this->get_languages();
        $result = [];

        $default_template = $templates[$status] ?? '';

        foreach ($languages as $lang) {
            $key = $status . '_' . $lang['code'];
            $result[$lang['code']] = [
                'language' => $lang,
                'template' => $templates[$key] ?? $default_template
            ];
        }

        return $result;
    }

    /**
     * String'i çevir (WPML/Polylang string translation)
     *
     * @param string $text Metin
     * @param string $name String adı
     * @param string $context Bağlam
     * @return string
     */
    public function translate_string($text, $name, $context = 'woo-whatsapp') {
        switch ($this->active_plugin) {
            case 'wpml':
                return apply_filters('wpml_translate_single_string', $text, $context, $name);

            case 'polylang':
                if (function_exists('pll__')) {
                    return pll__($text);
                }
                break;
        }

        return $text;
    }

    /**
     * String'i çeviri için kaydet
     *
     * @param string $text Metin
     * @param string $name String adı
     * @param string $context Bağlam
     */
    public function register_string($text, $name, $context = 'woo-whatsapp') {
        switch ($this->active_plugin) {
            case 'wpml':
                do_action('wpml_register_single_string', $context, $name, $text);
                break;

            case 'polylang':
                if (function_exists('pll_register_string')) {
                    pll_register_string($name, $text, $context);
                }
                break;
        }
    }

    /**
     * Dil bayrak URL'sini getir
     *
     * @param string $code Dil kodu
     * @return string
     */
    public function get_flag_url($code) {
        $languages = $this->get_languages();

        foreach ($languages as $lang) {
            if ($lang['code'] === $code && !empty($lang['flag'])) {
                return $lang['flag'];
            }
        }

        $flag_emojis = [
            'tr' => '🇹🇷',
            'tr_TR' => '🇹🇷',
            'en' => '🇬🇧',
            'en_US' => '🇺🇸',
            'en_GB' => '🇬🇧',
            'de' => '🇩🇪',
            'de_DE' => '🇩🇪',
            'fr' => '🇫🇷',
            'fr_FR' => '🇫🇷',
            'es' => '🇪🇸',
            'es_ES' => '🇪🇸',
            'it' => '🇮🇹',
            'it_IT' => '🇮🇹',
            'nl' => '🇳🇱',
            'nl_NL' => '🇳🇱',
            'pt_BR' => '🇧🇷',
            'pt_PT' => '🇵🇹',
            'ru' => '🇷🇺',
            'ru_RU' => '🇷🇺',
            'ar' => '🇸🇦',
            'zh_CN' => '🇨🇳',
            'zh_TW' => '🇹🇼',
            'ja' => '🇯🇵',
            'ko_KR' => '🇰🇷'
        ];

        return $flag_emojis[$code] ?? '🌐';
    }
}
