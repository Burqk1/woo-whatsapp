# Woo WhatsApp Bildirimcisi

WooCommerce siparişleri için WhatsApp bildirimleri gönderen bir WordPress eklentisi. Sipariş bildirimlerinin yanında terk edilmiş sepet hatırlatmaları, stok bildirimi (bekleme listesi), anahtar kelimeli chatbot ve basit bir otomasyon (flow) editörü de içerir. Yönetim paneli React ile yazılmıştır ve WordPress admin içinde tek sayfa uygulama olarak açılır.

Eklenti başlığındaki sürüm: **3.0.2**. Arayüz metinleri ağırlıkla Türkçedir; `languages/` altında `en_US` çevirisi ve bir `.pot` dosyası bulunur.

> `readme.txt` dosyası WordPress.org formatındadır ve satış/dağıtım için yazılmıştır. Bu README ise koddaki mevcut durumu anlatır; iki dosya arasında farklar varsa (aşağıdaki "Bilinen farklar" bölümüne bakın) kod esas alınmalıdır.

## Gereksinimler

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+ (WooCommerce yoksa eklenti çalışmaz, admin'de uyarı gösterir)
- Kaynaktan derlemek için Node.js ve npm (`@wordpress/scripts`)

Eklenti WooCommerce HPOS (`custom_order_tables`) uyumluluğunu bildirir.

## Özellikler

### Temel (lisans gerektirmeyen)

- **Sipariş durum bildirimleri**: `woocommerce_order_status_changed` üzerinden, ayarlarda seçilen durumlar için müşteriye mesaj gönderir. WooCommerce'in tüm durumları listelenir, ayrıca eklentiye özel bir `shipped` (Kargoya Verildi) durumu vardır.
- **Yönetici bildirimleri**: sipariş `processing` durumuna geçince yeni sipariş, `cancelled` olunca iptal mesajı yönetici numarasına gider. Düşük stok ve stok tükendi uyarıları da yöneticiye gönderilir.
- **Kargo takip mesajı**: sipariş düzenleme ekranında `_wwa_tracking_number` alanı doldurulduğunda `shipped` şablonu bir kez gönderilir. Takip numarası ayrıca WooCommerce Shipment Tracking ve YITH Order Tracking meta alanlarından da okunur.
- **Manuel mesaj**: sipariş detay sayfasına müşteriye WhatsApp mesajı gönderme kutusu eklenir.
- **Şablonlar ve yer tutucular**: her durum için ayrı şablon. Yer tutucular Türkçedir, örneğin `{müşteri_adı}`, `{sipariş_no}`, `{toplam_tutar}`, `{ürün_listesi}`, `{kargo_takip}`, `{teslimat_adresi}`, `{mağaza_adı}`, `{sipariş_url}`, `{kupon_kodu}`. Tam liste `includes/class-wwa-template-parser.php` içindedir; `wwa_template_values` filtresiyle yenileri eklenebilir.
- **Mesaj logları**: gönderilen her mesaj `wp_wwa_message_logs` tablosuna yazılır; panelden listelenebilir, tekrar gönderilebilir, silinebilir. Eski loglar günlük cron ile `wwa_log_retention_days` (varsayılan 30) gününe göre temizlenir.
- **Debug / simülasyon modu**: `wwa_debug_mode` açıkken mesajlar gerçekten gönderilmez, yalnızca loglanır. Bağlantı testi bu modu atlayıp gerçek mesaj yollar.
- **Bağlantı testi**: yönetici numarasına bir test mesajı gönderir.
- **Kurulum sihirbazı**: ilk açılışta adım adım API ve bildirim ayarları.
- **Ayar içe/dışa aktarma** (JSON).

### Pro (lisans gerektiren)

Aşağıdaki sekmeler ve ilgili REST uçları aktif lisans olmadan kilitlidir:

- **Flow Builder**: tetikleyici + eylem zinciri. Tetikleyiciler: yeni sipariş, sipariş durumu değişimi, sepet terk edildi, ürün stoğa girdi, webhook alındı. Eylemler: WhatsApp mesajı gönder, bekle, koşul kontrolü, sipariş notu ekle, kupon oluştur. Bekleyen adımlar `wp_wwa_scheduled_flows` tablosundan 5 dakikalık cron ile işlenir.
- **Sepet kurtarma**: sepet değişiklikleri izlenir, ödeme sayfasındaki e-posta/telefon alanları misafir kullanıcılar için de AJAX ile yakalanır (`assets/js/cart-tracking.js`). 15 dakika güncellenmeyen ve iletişim bilgisi olan sepetler "terk edilmiş" sayılır. Otomatik hatırlatma açıksa, ayarlanan gecikmeden (varsayılan 30 dk) sonra en fazla belirlenen sayıda (varsayılan 1) mesaj gönderilir. Mesaj, sepeti geri yükleyen imzalı bir kurtarma linki içerir. Sipariş tamamlanınca sepet "kurtarıldı" olarak işaretlenir.
- **Stok bildirimi / bekleme listesi**: stokta olmayan ürün sayfasına abonelik formu eklenir. Ürün tekrar stoğa girince abonelere e-posta, WhatsApp veya ikisiyle bildirim gider.
- **Chatbot**: gelen mesajlar anahtar kelime kurallarıyla eşleştirilir (`exact`, `contains`, `starts_with`, `ends_with`, `regex`). Sipariş durumu, kargo takip ve sipariş listesi gibi dinamik yanıtlar, müşteriyi telefon numarasından bularak üretilir. Hazır varsayılan kurallar yüklenebilir; konuşmalar loglanır.
- **Analitik ve raporlar**: özet ve mesaj istatistikleri, CSV dışa aktarma. "PDF" dışa aktarma aslında yazdırılabilir bir HTML rapor üretir; PDF'e tarayıcıdan kaydedilir.
- **Çoklu dil**: WPML, Polylang veya TranslatePress algılanırsa şablonlar dile göre ayrı tutulabilir ve siparişin diline göre şablon seçilir.

## Desteklenen WhatsApp sağlayıcıları

`includes/class-wwa-api-handler.php` içinde dört sağlayıcı uygulanmıştır:

| Sağlayıcı | Gerekli ayarlar |
|---|---|
| WhatsApp Business Cloud API (Meta, Graph API v18.0) | API Token, Phone Number ID (Business Account ID opsiyonel) |
| Twilio | Account SID, Auth Token, WhatsApp numarası |
| Ultramsg | Instance ID, Token |
| WATI | API URL, API Token |

Telefon numaraları gönderimden önce uluslararası biçime çevrilir; numarayı ülke koduyla girin.

## Kurulum

### Hazır paketten

Depodaki `woo-whatsapp.zip` derlenmiş `build/` klasörünü de içerir. WordPress admin'de **Eklentiler > Yeni Ekle > Eklenti Yükle** ile bu zip'i yükleyip etkinleştirin.

### Kaynaktan

`build/` klasörü `.gitignore` içinde olduğu için depoda yoktur. Klasörü `wp-content/plugins/woo-whatsapp/` altına kopyaladıktan sonra:

```bash
npm install
npm run build
```

Derleme yapılmazsa admin sayfası "Build dosyaları bulunamadı" uyarısı gösterir. Geliştirme sırasında `npm run start` ile izleme modunda derlenebilir.

Etkinleştirmede eklenti kendi tablolarını oluşturur (`wwa_message_logs`, `wwa_flows`, `wwa_flow_logs`, `wwa_scheduled_flows`, `wwa_abandoned_carts`, `wwa_waitlist`, `wwa_chatbot_rules`, `wwa_chatbot_conversations`) ve varsayılan Türkçe şablonları kaydeder. Eklenti silindiğinde `uninstall.php` ayarları, `_wwa_` ile başlayan post meta kayıtlarını ve tabloların çoğunu kaldırır; `wwa_flow_logs` ve `wwa_scheduled_flows` bu listede yok (listede bunun yerine var olmayan `wwa_scheduled_messages` geçiyor), bu iki tablo silme sonrasında kalır.

## Yapılandırma

1. Sol menüden **WhatsApp**'a girin; ilk açılışta kurulum sihirbazı çıkar.
2. **Ayarlar**'da sağlayıcıyı seçip kimlik bilgilerini girin, yönetici numarasını ekleyin ve bağlantıyı test edin.
3. Bildirim gönderilecek sipariş durumlarını ve müşteriye/yöneticiye gönderim seçeneklerini belirleyin.
4. **Şablonlar** sekmesinden mesajları düzenleyin; önizleme örnek verilerle yapılır.

### Webhook'lar (gelen mesajlar / chatbot)

Gelen mesajlar için şu REST uçları açıktır:

- `/wp-json/wwa/v1/webhook` (sağlayıcıyı içerikten algılar; GET isteği Meta doğrulaması içindir)
- `/wp-json/wwa/v1/webhook/whatsapp` (Meta)
- `/wp-json/wwa/v1/webhook/twilio`
- `/wp-json/wwa/v1/webhook/ultramsg`
- `/wp-json/wwa/v1/webhook/{key}` (Flow Builder'ın "Webhook Alındı" tetikleyicisi)

Meta doğrulaması `wwa_webhook_verify_token` ayarındaki değerle karşılaştırılır.

### Lisans

Pro özellikler `wwa_license_status` değeri `active` olduğunda açılır. Doğrulama `includes/class-wwa-rest-api.php` içindedir:

- `WWA_ENVATO_TOKEN` sabiti (veya `wwa_envato_token` ayarı) tanımlıysa, girilen kod Envato Purchase Code formatında olmalıdır ve Envato API (`/v3/market/author/sale`) ile doğrulanır. `WWA_ENVATO_ITEM_ID` tanımlıysa kodun o ürüne ait olduğu da kontrol edilir. Bu token satıcının kendi Envato kişisel token'ıdır, `wp-config.php` içinde tanımlanabilir.
- Token tanımlı değilse eklenti demo modunda çalışır ve yalnızca koda gömülü demo anahtarlarını kabul eder (`DEMO-1234-5678-DEMO`, `PRO1-2345-6789-ABCD`, `AGEN-CY12-3456-7890`).

Bu nedenle eklentiyi dağıtmadan önce demo anahtar mantığını gözden geçirmek gerekir; aksi halde herkes Pro özellikleri bu anahtarlarla açabilir.

## Proje yapısı

```
woo-whatsapp.php              Ana eklenti dosyası, tablo kurulumu, varsayılan ayarlar
admin/class-wwa-admin.php     Admin menüsü ve React uygulamasının yüklenmesi
includes/
  class-wwa-api-handler.php   Sağlayıcılara mesaj gönderimi
  class-wwa-woocommerce.php   Sipariş/stok hook'ları, manuel mesaj kutusu
  class-wwa-template-parser.php  Yer tutucuların doldurulması
  class-wwa-settings.php      Ayarlar, şablonlar, lisans kontrolü
  class-wwa-rest-api.php      wwa/v1 REST uçları (panelin tüm verisi buradan gelir)
  class-wwa-logger.php        Mesaj logları ve temizlik cron'u
  class-wwa-flow-builder.php  Otomasyon tetikleyicileri ve eylemleri
  class-wwa-abandoned-cart.php  Sepet takibi ve hatırlatmalar
  class-wwa-waitlist.php      Stok bildirimi formu ve bildirimler
  class-wwa-chatbot.php       Gelen mesaj webhook'ları ve kural eşleştirme
  class-wwa-i18n.php          WPML / Polylang / TranslatePress entegrasyonu
assets/js/cart-tracking.js    Ödeme sayfasında misafir bilgilerini yakalama
src/                          React admin paneli (@wordpress/scripts ile derlenir)
languages/                    .pot ve en_US çevirisi
documentation/index.html      İngilizce kurulum kılavuzu
readme.txt, kurulum.txt       WordPress.org / dağıtım metinleri
```

## Bilinen farklar (readme.txt ve kurulum.txt ile kod arasında)

- `readme.txt` ve `kurulum.txt` **360dialog** desteğinden bahseder, ancak API katmanında 360dialog uygulanmamıştır; yalnızca Meta, Twilio, Ultramsg ve WATI vardır.
- `kurulum.txt` İngilizce yer tutucular (`{customer_name}`, `{order_number}` vb.) listeler; kodda kullanılan yer tutucular Türkçedir (`{müşteri_adı}`, `{sipariş_no}` vb.).
- `readme.txt` değişiklik günlüğünde geçen "mesaj kuyruğu sistemi" kodda bulunmuyor.
- `readme.txt` sepet kurtarma mesajına kupon eklenebileceğini söyler; sepet kurtarma modülünde kupon desteği yok (kupon oluşturma yalnızca Flow Builder eylemi olarak mevcut).
- "PDF dışa aktarma" gerçek PDF değil, yazdırılabilir HTML üretir.
- Sürüm numaraları tutarsız: eklenti başlığı 3.0.2, `readme.txt` Stable tag 3.0.0, `package.json` 2.0.0.

## Lisans

Eklenti başlığı ve `package.json` GPL-2.0-or-later belirtir. Depoda ayrı bir LICENSE dosyası yoktur.
