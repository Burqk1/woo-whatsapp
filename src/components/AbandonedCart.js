import { useState, useEffect } from '@wordpress/element';
import {
    Button,
    Card,
    CardBody,
    CardHeader,
    TextControl,
    TextareaControl,
    SelectControl,
    ToggleControl,
    Spinner,
    Notice,
    Dashicon,
    Modal,
    TabPanel
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

// Hazır Sepet Kurtarma Şablonları
const cartRecoveryTemplates = [
    {
        id: 'friendly',
        name: 'Dostça Hatırlatma',
        icon: '👋',
        description: 'Samimi ve dostça bir hatırlatma',
        category: 'basic',
        template: `Merhaba {müşteri_adı}! 👋

Sepetinizde güzel ürünler var! 🛒

📦 {ürün_sayısı} ürün sizi bekliyor
💰 Toplam: {sepet_tutarı}

Alışverişinizi tamamlamak için:
🔗 {kurtarma_linki}

Sorularınız varsa bize yazabilirsiniz! 💬`
    },
    {
        id: 'urgency',
        name: 'Aciliyet Mesajı',
        icon: '⏰',
        description: 'Aciliyet duygusu yaratan hatırlatma',
        category: 'sales',
        template: `⏰ Sepetiniz sizi bekliyor!

{müşteri_adı}, sepetinizdeki ürünler tükenmek üzere!

🛒 {ürün_sayısı} ürün
💰 {sepet_tutarı}

Stoklar sınırlı! Hemen tamamlayın:
👉 {kurtarma_linki}

Kaçırmayın! 🏃‍♂️`
    },
    {
        id: 'discount',
        name: 'İndirimli Teklif',
        icon: '💸',
        description: 'Kupon kodlu hatırlatma',
        category: 'sales',
        template: `🎁 ÖZEL FIRSAT!

{müşteri_adı}, sepetiniz için özel indirim!

🛒 Sepetinizde {ürün_sayısı} ürün var
💰 Toplam: {sepet_tutarı}

🏷️ İndirim Kodunuz: {kupon_kodu}

Şimdi siparişi tamamla ve indirimden faydalan!
👉 {kurtarma_linki}

Bu fırsat sınırlı sürelidir! ⏳`
    },
    {
        id: 'help',
        name: 'Yardım Teklifi',
        icon: '🤝',
        description: 'Yardım odaklı mesaj',
        category: 'premium',
        template: `Merhaba {müşteri_adı}!

Sepetinizi tamamlarken bir sorun mu yaşadınız? 🤔

Yardımcı olmaktan memnuniyet duyarız!

📦 Sepetinizde {ürün_sayısı} ürün bekliyor
💰 Tutar: {sepet_tutarı}

Sorularınız için bize yazabilirsiniz.
📞 WhatsApp: Buradan ulaşın

Siparişi tamamla: {kurtarma_linki}`
    },
    {
        id: 'lastchance',
        name: 'Son Şans',
        icon: '🚨',
        description: 'Son fırsat mesajı',
        category: 'sales',
        template: `🚨 SON ŞANS!

{müşteri_adı}, sepetinizi silmek üzereyiz!

⏰ 24 saat içinde işlem yapmazsanız sepetiniz silinecek.

🛒 Ürünler: {ürün_listesi}
💰 Toplam: {sepet_tutarı}

Hemen tamamla: {kurtarma_linki}

Bu son hatırlatmamız! ⚠️`
    },
    {
        id: 'personal',
        name: 'Kişisel Mesaj',
        icon: '💝',
        description: 'Kişiselleştirilmiş samimi mesaj',
        category: 'premium',
        template: `Sevgili {müşteri_adı},

Sizi özledik! 💝

Geçen sefer baktığınız ürünler hâlâ sepetinizde:

{ürün_listesi}

Toplam: {sepet_tutarı}

Size özel fırsatlarımız var! ✨
Sipariş için: {kurtarma_linki}

Bizi tercih ettiğiniz için teşekkürler! 🙏`
    },
    {
        id: 'simple',
        name: 'Basit Hatırlatma',
        icon: '📦',
        description: 'Kısa ve öz mesaj',
        category: 'basic',
        template: `Merhaba {müşteri_adı}!

Sepetinizde {ürün_sayısı} ürün bekliyor.
Toplam: {sepet_tutarı}

Siparişi tamamla: {kurtarma_linki}`
    },
    {
        id: 'exclusive',
        name: 'VIP Teklif',
        icon: '👑',
        description: 'VIP müşteri hissi veren mesaj',
        category: 'premium',
        template: `👑 VIP MÜŞTERİMİZ

Değerli {müşteri_adı},

Özel müşterilerimiz için sepet koruma süresini uzattık!

✨ Sepetiniz: {ürün_sayısı} ürün
💎 Değeri: {sepet_tutarı}

VIP avantajınız: {kupon_kodu}

Öncelikli sipariş hattı:
🎯 {kurtarma_linki}

Sadece sizin için! 🌟`
    }
];

const AbandonedCart = () => {
    const [carts, setCarts] = useState([]);
    const [stats, setStats] = useState({});
    const [settings, setSettings] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);
    const [activeTab, setActiveTab] = useState('carts');
    const [selectedCart, setSelectedCart] = useState(null);
    const [showCartModal, setShowCartModal] = useState(false);
    const [showTemplates, setShowTemplates] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState('all');
    const [filter, setFilter] = useState({ status: '', page: 1 });

    useEffect(() => {
        loadData();
    }, [filter]);

    const loadData = async () => {
        setLoading(true);
        try {
            const [cartsRes, statsRes, settingsRes] = await Promise.all([
                apiFetch({ path: `/wwa/v1/abandoned-carts?status=${filter.status}&page=${filter.page}` }),
                apiFetch({ path: '/wwa/v1/abandoned-carts/stats' }),
                apiFetch({ path: '/wwa/v1/abandoned-carts/settings' })
            ]);
            setCarts(cartsRes.carts || []);
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
                path: '/wwa/v1/abandoned-carts/settings',
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

    const sendReminder = async (cartId) => {
        try {
            const response = await apiFetch({
                path: `/wwa/v1/abandoned-carts/${cartId}/send-reminder`,
                method: 'POST'
            });
            setMessage({ type: response.success ? 'success' : 'error', text: response.message });
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: 'Hatırlatma gönderilemedi' });
        }
    };

    const deleteCart = async (cartId) => {
        if (!confirm('Bu sepeti silmek istediğinize emin misiniz?')) return;

        try {
            await apiFetch({
                path: `/wwa/v1/abandoned-carts/${cartId}`,
                method: 'DELETE'
            });
            setMessage({ type: 'success', text: 'Sepet silindi' });
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: 'Sepet silinemedi' });
        }
    };

    const viewCart = async (cartId) => {
        try {
            const cart = await apiFetch({ path: `/wwa/v1/abandoned-carts/${cartId}` });
            setSelectedCart(cart);
            setShowCartModal(true);
        } catch (error) {
            setMessage({ type: 'error', text: 'Sepet detayları yüklenemedi' });
        }
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount || 0);
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('tr-TR');
    };

    const getStatusLabel = (status) => {
        const labels = {
            'abandoned': 'Terk Edilmiş',
            'pending': 'Beklemede',
            'recovered': 'Kurtarıldı',
            'converted': 'Dönüştürüldü'
        };
        return labels[status] || status;
    };

    const useTemplate = (template) => {
        setSettings({ ...settings, template: template.template });
        setShowTemplates(false);
        setMessage({ type: 'success', text: `"${template.name}" şablonu uygulandı!` });
    };

    const filteredTemplates = selectedCategory === 'all'
        ? cartRecoveryTemplates
        : cartRecoveryTemplates.filter(t => t.category === selectedCategory);

    if (loading && !carts.length) {
        return (
            <div className="wwa-loading">
                <Spinner />
                <p>Yükleniyor...</p>
            </div>
        );
    }

    return (
        <div className="wwa-abandoned-cart">
            {message && (
                <Notice
                    status={message.type}
                    onRemove={() => setMessage(null)}
                    isDismissible
                >
                    {message.text}
                </Notice>
            )}

            <h2>Terk Edilmiş Sepetler</h2>

            {/* İstatistikler */}
            <div className="wwa-stats-grid">
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="cart" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.total_abandoned || 0}</span>
                                <span className="wwa-stat-label">Terk Edilmiş Sepet</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="yes-alt" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.recovered || 0}</span>
                                <span className="wwa-stat-label">Kurtarılan</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="chart-pie" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.recovery_rate || 0}%</span>
                                <span className="wwa-stat-label">Kurtarma Oranı</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="money-alt" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{formatCurrency(stats.recovered_value)}</span>
                                <span className="wwa-stat-label">Kurtarılan Değer</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
            </div>

            <TabPanel
                className="wwa-tab-panel"
                activeClass="is-active"
                tabs={[
                    { name: 'carts', title: 'Sepetler', className: 'wwa-tab' },
                    { name: 'settings', title: 'Ayarlar', className: 'wwa-tab' }
                ]}
                onSelect={(tabName) => setActiveTab(tabName)}
            >
                {(tab) => (
                    <>
                        {tab.name === 'carts' && (
                            <div className="wwa-carts-tab">
                                <div className="wwa-filter-bar">
                                    <SelectControl
                                        value={filter.status}
                                        options={[
                                            { label: 'Tüm Durumlar', value: '' },
                                            { label: 'Terk Edilmiş', value: 'abandoned' },
                                            { label: 'Kurtarıldı', value: 'recovered' },
                                            { label: 'Beklemede', value: 'pending' }
                                        ]}
                                        onChange={(value) => setFilter({ ...filter, status: value, page: 1 })}
                                    />
                                </div>

                                {carts.length === 0 ? (
                                    <Card>
                                        <CardBody>
                                            <div className="wwa-empty-state">
                                                <Dashicon icon="cart" size={48} />
                                                <h3>Terk edilmiş sepet yok</h3>
                                                <p>Müşterileriniz sepetlerini terk ettiğinde burada görünecekler.</p>
                                            </div>
                                        </CardBody>
                                    </Card>
                                ) : (
                                    <table className="wwa-table">
                                        <thead>
                                            <tr>
                                                <th>Müşteri</th>
                                                <th>Telefon</th>
                                                <th>Sepet Tutarı</th>
                                                <th>Ürün Sayısı</th>
                                                <th>Durum</th>
                                                <th>Hatırlatma</th>
                                                <th>Tarih</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {carts.map(cart => (
                                                <tr key={cart.id}>
                                                    <td>
                                                        {cart.customer_name || cart.email || 'Anonim'}
                                                        {cart.user_id && <span className="badge member">Üye</span>}
                                                    </td>
                                                    <td>{cart.phone || '-'}</td>
                                                    <td><strong>{formatCurrency(cart.cart_total)}</strong></td>
                                                    <td>{cart.item_count || '-'}</td>
                                                    <td>
                                                        <span className={`status-badge ${cart.status}`}>
                                                            {getStatusLabel(cart.status)}
                                                        </span>
                                                    </td>
                                                    <td>{cart.reminder_count || 0}</td>
                                                    <td>{formatDate(cart.created_at)}</td>
                                                    <td>
                                                        <div className="wwa-action-buttons">
                                                            <Button
                                                                isSmall
                                                                onClick={() => viewCart(cart.id)}
                                                            >
                                                                <Dashicon icon="visibility" />
                                                            </Button>
                                                            {cart.status === 'abandoned' && cart.phone && (
                                                                <Button
                                                                    isSmall
                                                                    isPrimary
                                                                    onClick={() => sendReminder(cart.id)}
                                                                >
                                                                    <Dashicon icon="email-alt" />
                                                                </Button>
                                                            )}
                                                            <Button
                                                                isSmall
                                                                isDestructive
                                                                onClick={() => deleteCart(cart.id)}
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
                            </div>
                        )}

                        {tab.name === 'settings' && (
                            <Card className="wwa-settings-card">
                                <CardBody>
                                    <ToggleControl
                                        label="Terk Edilmiş Sepet Takibi"
                                        help="Müşteri sepetlerini takip et ve terk edildiğinde bildirim gönder"
                                        checked={settings.enabled === 'yes'}
                                        onChange={(value) => setSettings({ ...settings, enabled: value ? 'yes' : 'no' })}
                                    />

                                    <TextControl
                                        label="Bekleme Süresi (dakika)"
                                        type="number"
                                        value={settings.threshold_minutes || 15}
                                        onChange={(value) => setSettings({ ...settings, threshold_minutes: parseInt(value) })}
                                        help="Sepet bu süre sonra terk edilmiş sayılır"
                                    />

                                    <hr />

                                    <h3>Hatırlatma Zamanlaması</h3>
                                    <p className="wwa-help-text">Hatırlatma mesajlarının gönderileceği süreleri belirleyin (dakika cinsinden)</p>

                                    <div className="wwa-reminder-delays">
                                        {(settings.reminder_delays || [60, 1440, 4320]).map((delay, index) => (
                                            <TextControl
                                                key={index}
                                                label={`${index + 1}. Hatırlatma`}
                                                type="number"
                                                value={delay}
                                                onChange={(value) => {
                                                    const newDelays = [...(settings.reminder_delays || [])];
                                                    newDelays[index] = parseInt(value);
                                                    setSettings({ ...settings, reminder_delays: newDelays });
                                                }}
                                                help={`${delay} dakika = ${Math.round(delay / 60)} saat`}
                                            />
                                        ))}
                                    </div>

                                    <hr />

                                    <h3>Kupon Ayarları</h3>
                                    <ToggleControl
                                        label="Kurtarma Kuponu"
                                        help="Hatırlatma mesajlarında indirim kuponu gönder"
                                        checked={settings.coupon_enabled === 'yes'}
                                        onChange={(value) => setSettings({ ...settings, coupon_enabled: value ? 'yes' : 'no' })}
                                    />

                                    {settings.coupon_enabled === 'yes' && (
                                        <>
                                            <SelectControl
                                                label="Kupon Tipi"
                                                value={settings.coupon_type || 'percent'}
                                                options={[
                                                    { label: 'Yüzde İndirim', value: 'percent' },
                                                    { label: 'Sabit Tutar', value: 'fixed_cart' }
                                                ]}
                                                onChange={(value) => setSettings({ ...settings, coupon_type: value })}
                                            />
                                            <TextControl
                                                label="İndirim Miktarı"
                                                type="number"
                                                value={settings.coupon_amount || 10}
                                                onChange={(value) => setSettings({ ...settings, coupon_amount: parseInt(value) })}
                                            />
                                        </>
                                    )}

                                    <hr />

                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                                        <h3 style={{ margin: 0 }}>Mesaj Şablonu</h3>
                                        <Button
                                            isSecondary
                                            onClick={() => setShowTemplates(true)}
                                            icon="layout"
                                        >
                                            Hazır Şablonlar
                                        </Button>
                                    </div>
                                    <TextareaControl
                                        value={settings.template || ''}
                                        onChange={(value) => setSettings({ ...settings, template: value })}
                                        placeholder={`Merhaba {müşteri_adı}!

Sepetinizde {ürün_sayısı} adet ürün sizi bekliyor.

Sepet Tutarı: {sepet_tutarı}

Hemen alışverişinizi tamamlayın: {kurtarma_linki}

{kupon_kodu ? 'Özel indirim kodunuz: ' + kupon_kodu : ''}`}
                                        rows={10}
                                        help="Kullanılabilir değişkenler: {müşteri_adı}, {ürün_sayısı}, {sepet_tutarı}, {kurtarma_linki}, {kupon_kodu}, {ürün_listesi}"
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

            {/* Sepet Detay Modal */}
            {showCartModal && selectedCart && (
                <Modal
                    title="Sepet Detayları"
                    onRequestClose={() => setShowCartModal(false)}
                    className="wwa-cart-modal"
                >
                    <div className="wwa-cart-detail">
                        <div className="wwa-cart-info">
                            <h3>Müşteri Bilgileri</h3>
                            <p><strong>Ad:</strong> {selectedCart.customer_name || '-'}</p>
                            <p><strong>E-posta:</strong> {selectedCart.email || '-'}</p>
                            <p><strong>Telefon:</strong> {selectedCart.phone || '-'}</p>
                            <p><strong>Durum:</strong> {getStatusLabel(selectedCart.status)}</p>
                            <p><strong>Oluşturulma:</strong> {formatDate(selectedCart.created_at)}</p>
                            {selectedCart.recovered_at && (
                                <p><strong>Kurtarılma:</strong> {formatDate(selectedCart.recovered_at)}</p>
                            )}
                        </div>

                        <div className="wwa-cart-items">
                            <h3>Sepet İçeriği</h3>
                            {selectedCart.cart_contents ? (
                                <table className="wwa-mini-table">
                                    <thead>
                                        <tr>
                                            <th>Ürün</th>
                                            <th>Miktar</th>
                                            <th>Fiyat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {JSON.parse(selectedCart.cart_contents).map((item, idx) => (
                                            <tr key={idx}>
                                                <td>{item.name}</td>
                                                <td>{item.quantity}</td>
                                                <td>{formatCurrency(item.line_total)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colSpan="2"><strong>Toplam:</strong></td>
                                            <td><strong>{formatCurrency(selectedCart.cart_total)}</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            ) : (
                                <p>Sepet içeriği mevcut değil.</p>
                            )}
                        </div>

                        <div className="wwa-cart-recovery">
                            <h3>Kurtarma Linki</h3>
                            <TextControl
                                value={selectedCart.recovery_url || ''}
                                readOnly
                            />
                            <Button
                                isSecondary
                                onClick={() => navigator.clipboard.writeText(selectedCart.recovery_url)}
                            >
                                <Dashicon icon="clipboard" /> Kopyala
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Şablon Seçme Modal */}
            {showTemplates && (
                <Modal
                    title="Hazır Sepet Kurtarma Şablonları"
                    onRequestClose={() => setShowTemplates(false)}
                    className="wwa-templates-modal"
                >
                    <div className="wwa-templates-intro">
                        <p>Terk edilmiş sepet hatırlatmaları için profesyonel şablonlardan birini seçin.</p>
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

export default AbandonedCart;
