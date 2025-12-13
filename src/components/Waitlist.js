import { useState, useEffect } from '@wordpress/element';
import {
    Button,
    Card,
    CardBody,
    TextControl,
    TextareaControl,
    SelectControl,
    ToggleControl,
    Spinner,
    Notice,
    Dashicon,
    TabPanel,
    Modal
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

// Hazır Bildirim Şablonları
const notificationTemplates = [
    {
        id: 'basic',
        name: 'Basit Bildirim',
        icon: '📦',
        description: 'Kısa ve öz stok bildirimi',
        category: 'basic',
        template: `Merhaba! 👋

Beklediğiniz ürün stoğa girdi!

📦 {ürün_adı}
💰 Fiyat: {ürün_fiyat}

🛒 Hemen satın al: {ürün_linki}`
    },
    {
        id: 'exciting',
        name: 'Heyecanlı Bildirim',
        icon: '🎉',
        description: 'Müşteriyi heyecanlandıran mesaj',
        category: 'basic',
        template: `🎉 HARIKA HABER!

Uzun zamandır beklediğiniz ürün nihayet stoklarda!

⭐ {ürün_adı}
💰 Sadece {ürün_fiyat}

Bu fırsatı kaçırmayın, stoklar sınırlı!
👉 {ürün_linki}

İyi alışverişler! 🛍️`
    },
    {
        id: 'urgency',
        name: 'Aciliyet Bildirimi',
        icon: '⚡',
        description: 'Aciliyet duygusu yaratan mesaj',
        category: 'sales',
        template: `⚡ ACİL BİLDİRİM!

{ürün_adı} tekrar stokta!

⏰ Stoklar çok hızlı tükeniyor!
💰 Fiyat: {ürün_fiyat}

Son {stok_adedi} ürün kaldı!
Hemen sipariş ver: {ürün_linki}

Kaçırma! 🏃‍♂️`
    },
    {
        id: 'discount',
        name: 'İndirimli Bildirim',
        icon: '💸',
        description: 'İndirim vurgusu yapan mesaj',
        category: 'sales',
        template: `🔥 ÖZEL FIRSAT!

Beklediğiniz ürün hem stoğa girdi hem de indirimde!

{ürün_adı}
🏷️ Önceki Fiyat: ~~{eski_fiyat}~~
💰 YENİ FİYAT: {ürün_fiyat}

Bu fırsatı kaçırmayın!
👉 {ürün_linki}

Kayıtlı müşterilere özel fiyat! 🌟`
    },
    {
        id: 'premium',
        name: 'Premium Bildirim',
        icon: '👑',
        description: 'VIP müşteri hissi veren mesaj',
        category: 'premium',
        template: `👑 VIP BİLDİRİM

Değerli Müşterimiz,

Listenizdeki ürün stoklara eklendi:

✨ {ürün_adı}
💎 Fiyat: {ürün_fiyat}

Öncelikli müşterimiz olarak sizin için ayırdık!

🎯 Sipariş için: {ürün_linki}

Bizi tercih ettiğiniz için teşekkür ederiz!`
    },
    {
        id: 'friendly',
        name: 'Samimi Bildirim',
        icon: '🤗',
        description: 'Dostça ve samimi mesaj',
        category: 'premium',
        template: `Selam! 🤗

Seni hatırladık! Daha önce bekleme listesine eklediğin ürün geldi!

{ürün_adı}

Fiyatı şu an {ürün_fiyat}

İstersen hemen bir göz at 👀
{ürün_linki}

Sorularınız varsa bize yazabilirsiniz! 💬`
    },
    {
        id: 'limited',
        name: 'Sınırlı Stok',
        icon: '🏷️',
        description: 'Sınırlı stok vurgulu mesaj',
        category: 'sales',
        template: `🚨 STOK ALARMI!

Çok beklenen ürün geldi ama stok çok sınırlı!

📦 {ürün_adı}
💰 {ürün_fiyat}
📊 Stok: Sadece {stok_adedi} adet!

İlk gelen alır!
🔗 {ürün_linki}

Hızlı ol! ⚡`
    },
    {
        id: 'seasonal',
        name: 'Sezonluk Bildirim',
        icon: '🌸',
        description: 'Sezon/kampanya temalı mesaj',
        category: 'premium',
        template: `🌟 SEZONA ÖZEL!

Tam da aradığınız ürün stoklara geldi!

{ürün_adı}

✨ Sezon fiyatı: {ürün_fiyat}

Bu sezonun en çok aranan ürünlerinden biri!
Kaçırmadan alın: {ürün_linki}

Mutlu alışverişler! 🛒`
    }
];

const Waitlist = () => {
    const [entries, setEntries] = useState([]);
    const [stats, setStats] = useState({});
    const [settings, setSettings] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);
    const [filter, setFilter] = useState({ status: '', product_id: '', page: 1 });
    const [showTemplates, setShowTemplates] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState('all');

    useEffect(() => {
        loadData();
    }, [filter]);

    const loadData = async () => {
        setLoading(true);
        try {
            const [entriesRes, statsRes, settingsRes] = await Promise.all([
                apiFetch({
                    path: `/wwa/v1/waitlist?status=${filter.status}&product_id=${filter.product_id}&page=${filter.page}`
                }),
                apiFetch({ path: '/wwa/v1/waitlist/stats' }),
                apiFetch({ path: '/wwa/v1/waitlist/settings' })
            ]);
            setEntries(entriesRes.entries || []);
            setStats(statsRes || {});
            setSettings(settingsRes || {});
        } catch (error) {
            setMessage({ type: 'error', text: 'Veriler yüklenirken hata oluştu' });
        }
        setLoading(false);
    };

    const saveSettings = async () => {
        setSaving(true);
        try {
            await apiFetch({
                path: '/wwa/v1/waitlist/settings',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(settings)
            });
            setMessage({ type: 'success', text: 'Ayarlar kaydedildi' });
        } catch (error) {
            setMessage({ type: 'error', text: 'Ayarlar kaydedilemedi: ' + error.message });
        }
        setSaving(false);
    };

    const notifyEntry = async (entryId) => {
        try {
            const response = await apiFetch({
                path: `/wwa/v1/waitlist/${entryId}/notify`,
                method: 'POST'
            });
            setMessage({ type: response.success ? 'success' : 'error', text: response.message });
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: 'Bildirim gönderilemedi' });
        }
    };

    const deleteEntry = async (entryId) => {
        if (!confirm('Bu kaydı silmek istediğinize emin misiniz?')) return;

        try {
            await apiFetch({
                path: `/wwa/v1/waitlist/${entryId}`,
                method: 'DELETE'
            });
            setMessage({ type: 'success', text: 'Kayıt silindi' });
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: 'Kayıt silinemedi' });
        }
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('tr-TR');
    };

    const getStatusLabel = (status) => {
        const labels = {
            'waiting': 'Bekliyor',
            'notified': 'Bildirildi',
            'converted': 'Satın Aldı'
        };
        return labels[status] || status;
    };

    const useTemplate = (template) => {
        setSettings({ ...settings, notification_template: template.template });
        setShowTemplates(false);
        setMessage({ type: 'success', text: `"${template.name}" şablonu uygulandı!` });
    };

    const filteredTemplates = selectedCategory === 'all'
        ? notificationTemplates
        : notificationTemplates.filter(t => t.category === selectedCategory);

    if (loading && !entries.length) {
        return (
            <div className="wwa-loading">
                <Spinner />
                <p>Yükleniyor...</p>
            </div>
        );
    }

    return (
        <div className="wwa-waitlist">
            {message && (
                <Notice
                    status={message.type}
                    onRemove={() => setMessage(null)}
                    isDismissible
                >
                    {message.text}
                </Notice>
            )}

            <h2>Stok Bildirimi (Waitlist)</h2>

            {/* İstatistikler */}
            <div className="wwa-stats-grid">
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="groups" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.total_subscribers || 0}</span>
                                <span className="wwa-stat-label">Toplam Abone</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="clock" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.waiting || 0}</span>
                                <span className="wwa-stat-label">Bekleyen</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="megaphone" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.notified || 0}</span>
                                <span className="wwa-stat-label">Bildirildi</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="cart" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.converted || 0}</span>
                                <span className="wwa-stat-label">Dönüşüm</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
            </div>

            <TabPanel
                className="wwa-tab-panel"
                activeClass="is-active"
                tabs={[
                    { name: 'entries', title: 'Aboneler', className: 'wwa-tab' },
                    { name: 'settings', title: 'Ayarlar', className: 'wwa-tab' }
                ]}
            >
                {(tab) => (
                    <>
                        {tab.name === 'entries' && (
                            <div className="wwa-entries-tab">
                                <div className="wwa-filter-bar">
                                    <SelectControl
                                        value={filter.status}
                                        options={[
                                            { label: 'Tüm Durumlar', value: '' },
                                            { label: 'Bekliyor', value: 'waiting' },
                                            { label: 'Bildirildi', value: 'notified' },
                                            { label: 'Satın Aldı', value: 'converted' }
                                        ]}
                                        onChange={(value) => setFilter({ ...filter, status: value, page: 1 })}
                                    />
                                </div>

                                {entries.length === 0 ? (
                                    <Card>
                                        <CardBody>
                                            <div className="wwa-empty-state">
                                                <Dashicon icon="bell" size={48} />
                                                <h3>Henüz abone yok</h3>
                                                <p>Stokta olmayan ürünler için müşteriler bildirim almak istediğinde burada görünecekler.</p>
                                            </div>
                                        </CardBody>
                                    </Card>
                                ) : (
                                    <table className="wwa-table">
                                        <thead>
                                            <tr>
                                                <th>Ürün</th>
                                                <th>Telefon</th>
                                                <th>E-posta</th>
                                                <th>Durum</th>
                                                <th>Kayıt Tarihi</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {entries.map(entry => (
                                                <tr key={entry.id}>
                                                    <td>
                                                        <div className="wwa-product-info">
                                                            {entry.product_image && (
                                                                <img src={entry.product_image} alt="" className="product-thumb" />
                                                            )}
                                                            <span>{entry.product_name || `Ürün #${entry.product_id}`}</span>
                                                        </div>
                                                    </td>
                                                    <td>{entry.phone || '-'}</td>
                                                    <td>{entry.email || '-'}</td>
                                                    <td>
                                                        <span className={`status-badge ${entry.status}`}>
                                                            {getStatusLabel(entry.status)}
                                                        </span>
                                                    </td>
                                                    <td>{formatDate(entry.created_at)}</td>
                                                    <td>
                                                        <div className="wwa-action-buttons">
                                                            {entry.status === 'waiting' && entry.phone && (
                                                                <Button
                                                                    isSmall
                                                                    isPrimary
                                                                    onClick={() => notifyEntry(entry.id)}
                                                                    title="Bildirim Gönder"
                                                                >
                                                                    <Dashicon icon="megaphone" />
                                                                </Button>
                                                            )}
                                                            <Button
                                                                isSmall
                                                                isDestructive
                                                                onClick={() => deleteEntry(entry.id)}
                                                                title="Sil"
                                                            >
                                                                <Dashicon icon="trash" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}

                                {/* Ürünlere göre gruplandırma */}
                                {stats.products_waiting && stats.products_waiting.length > 0 && (
                                    <Card className="wwa-products-waiting">
                                        <CardBody>
                                            <h3>Bekleyen Ürünler</h3>
                                            <div className="wwa-products-grid">
                                                {stats.products_waiting.map(product => (
                                                    <div key={product.product_id} className="wwa-product-card">
                                                        {product.image && <img src={product.image} alt="" />}
                                                        <div className="product-info">
                                                            <strong>{product.name}</strong>
                                                            <span>{product.subscriber_count} kişi bekliyor</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </CardBody>
                                    </Card>
                                )}
                            </div>
                        )}

                        {tab.name === 'settings' && (
                            <Card className="wwa-settings-card">
                                <CardBody>
                                    <ToggleControl
                                        label="Stok Bildirimi Aktif"
                                        help="Stokta olmayan ürünlerde bildirim formu göster"
                                        checked={settings.enabled === 'yes'}
                                        onChange={(value) => setSettings({ ...settings, enabled: value ? 'yes' : 'no' })}
                                    />

                                    <hr />

                                    <h3>Form Ayarları</h3>
                                    <TextControl
                                        label="Form Başlığı"
                                        value={settings.form_title || ''}
                                        onChange={(value) => setSettings({ ...settings, form_title: value })}
                                        placeholder="Stok bildirimi al"
                                    />

                                    <TextareaControl
                                        label="Form Açıklaması"
                                        value={settings.form_description || ''}
                                        onChange={(value) => setSettings({ ...settings, form_description: value })}
                                        placeholder="Ürün stoğa girdiğinde bilgilendirilmek için kayıt olun."
                                        rows={2}
                                    />

                                    <TextareaControl
                                        label="Başarı Mesajı"
                                        value={settings.success_message || ''}
                                        onChange={(value) => setSettings({ ...settings, success_message: value })}
                                        placeholder="Kaydınız alındı! Ürün stoğa girdiğinde size haber vereceğiz."
                                        rows={2}
                                    />

                                    <hr />

                                    <h3>Bildirim Ayarları</h3>
                                    <ToggleControl
                                        label="Otomatik Bildirim"
                                        help="Ürün stoğa girdiğinde otomatik olarak WhatsApp bildirimi gönder"
                                        checked={settings.auto_notify === 'yes'}
                                        onChange={(value) => setSettings({ ...settings, auto_notify: value ? 'yes' : 'no' })}
                                    />

                                    <div className="wwa-template-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                                        <label style={{ fontWeight: '600' }}>Bildirim Şablonu</label>
                                        <Button
                                            isSecondary
                                            onClick={() => setShowTemplates(true)}
                                            icon="layout"
                                        >
                                            Hazır Şablonlar
                                        </Button>
                                    </div>

                                    <TextareaControl
                                        value={settings.notification_template || ''}
                                        onChange={(value) => setSettings({ ...settings, notification_template: value })}
                                        placeholder={`Merhaba!

Beklediğiniz ürün stoğa girdi!

{ürün_adı}
Fiyat: {ürün_fiyat}

Hemen satın almak için: {ürün_linki}`}
                                        rows={8}
                                        help="Kullanılabilir değişkenler: {ürün_adı}, {ürün_fiyat}, {ürün_linki}, {ürün_resmi}, {stok_adedi}, {eski_fiyat}"
                                    />

                                    <div className="wwa-settings-footer">
                                        <Button isPrimary onClick={saveSettings} isBusy={saving}>
                                            {saving ? 'Kaydediliyor...' : 'Ayarları Kaydet'}
                                        </Button>
                                    </div>
                                </CardBody>
                            </Card>
                        )}
                    </>
                )}
            </TabPanel>

            {/* Şablon Seçme Modal */}
            {showTemplates && (
                <Modal
                    title="Hazır Bildirim Şablonları"
                    onRequestClose={() => setShowTemplates(false)}
                    className="wwa-templates-modal"
                >
                    <div className="wwa-templates-intro">
                        <p>Stok bildirimi için profesyonel şablonlardan birini seçin.</p>
                    </div>

                    {/* Kategori Filtreleri */}
                    <div className="wwa-template-categories">
                        <Button
                            isSmall
                            isPrimary={selectedCategory === 'all'}
                            isSecondary={selectedCategory !== 'all'}
                            onClick={() => setSelectedCategory('all')}
                        >
                            Tümü
                        </Button>
                        <Button
                            isSmall
                            isPrimary={selectedCategory === 'basic'}
                            isSecondary={selectedCategory !== 'basic'}
                            onClick={() => setSelectedCategory('basic')}
                        >
                            📦 Temel
                        </Button>
                        <Button
                            isSmall
                            isPrimary={selectedCategory === 'sales'}
                            isSecondary={selectedCategory !== 'sales'}
                            onClick={() => setSelectedCategory('sales')}
                        >
                            💸 Satış Odaklı
                        </Button>
                        <Button
                            isSmall
                            isPrimary={selectedCategory === 'premium'}
                            isSecondary={selectedCategory !== 'premium'}
                            onClick={() => setSelectedCategory('premium')}
                        >
                            👑 Premium
                        </Button>
                    </div>

                    {/* Şablon Kartları */}
                    <div className="wwa-templates-grid">
                        {filteredTemplates.map(template => (
                            <Card key={template.id} className="wwa-template-card" onClick={() => useTemplate(template)}>
                                <CardBody>
                                    <div className="wwa-template-icon">{template.icon}</div>
                                    <h3>{template.name}</h3>
                                    <p>{template.description}</p>
                                    <div className="wwa-template-meta">
                                        <span>{template.category === 'basic' ? 'Temel' : template.category === 'sales' ? 'Satış' : 'Premium'}</span>
                                    </div>
                                </CardBody>
                            </Card>
                        ))}
                    </div>

                    <style>{`
                        .wwa-templates-modal {
                            max-width: 1000px !important;
                            width: 95% !important;
                        }
                        .wwa-templates-modal .components-modal__content {
                            max-width: 100% !important;
                            padding: 24px !important;
                        }
                        .wwa-templates-intro {
                            text-align: center;
                            margin-bottom: 24px;
                            color: #666;
                        }
                        .wwa-template-categories {
                            display: flex;
                            gap: 10px;
                            margin-bottom: 24px;
                            justify-content: center;
                        }
                        .wwa-templates-grid {
                            display: grid !important;
                            grid-template-columns: repeat(3, 1fr) !important;
                            gap: 16px !important;
                            max-height: 60vh;
                            overflow-y: auto;
                            padding: 5px;
                        }
                        @media (max-width: 900px) {
                            .wwa-templates-grid {
                                grid-template-columns: repeat(2, 1fr) !important;
                            }
                        }
                        @media (max-width: 600px) {
                            .wwa-templates-grid {
                                grid-template-columns: 1fr !important;
                            }
                        }
                        .wwa-templates-modal .wwa-template-card {
                            cursor: pointer;
                            transition: all 0.2s;
                            border: 2px solid transparent !important;
                            margin: 0 !important;
                        }
                        .wwa-templates-modal .wwa-template-card:hover {
                            border-color: #25D366 !important;
                            transform: translateY(-2px);
                            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                        }
                        .wwa-templates-modal .wwa-template-icon {
                            font-size: 36px;
                            margin-bottom: 12px;
                            text-align: center;
                        }
                        .wwa-templates-modal .wwa-template-card h3 {
                            font-size: 15px;
                            margin: 0 0 8px 0;
                            text-align: center;
                        }
                        .wwa-templates-modal .wwa-template-card p {
                            font-size: 12px;
                            color: #666;
                            margin: 0 0 10px 0;
                            line-height: 1.4;
                            text-align: center;
                        }
                        .wwa-templates-modal .wwa-template-meta {
                            font-size: 11px;
                            color: #999;
                            text-align: center;
                        }
                        .wwa-templates-modal .components-card__body {
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            padding: 20px !important;
                        }
                    `}</style>
                </Modal>
            )}
        </div>
    );
};

export default Waitlist;
