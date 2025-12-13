import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../utils/api';

const Settings = ({ showNotification, onLicenseChange }) => {
    const [settings, setSettings] = useState({});
    const [providers, setProviders] = useState({});
    const [orderStatuses, setOrderStatuses] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [activeSection, setActiveSection] = useState('general');

    // Test connection states
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [testPhone, setTestPhone] = useState('');
    const [sendingTest, setSendingTest] = useState(false);
    const [webhookUrl, setWebhookUrl] = useState('');

    // License states
    const [license, setLicense] = useState(null);
    const [licenseKey, setLicenseKey] = useState('');
    const [activating, setActivating] = useState(false);

    // Language states
    const [languageInfo, setLanguageInfo] = useState({ enabled: false, languages: [] });
    const [customLanguages, setCustomLanguages] = useState([]);
    const [newLangCode, setNewLangCode] = useState('');
    const [newLangName, setNewLangName] = useState('');

    useEffect(() => {
        fetchData();
        fetchLicense();
        fetchLanguages();
        // Set webhook URL
        const siteUrl = window.wwaSettings?.site_url || window.location.origin;
        setWebhookUrl(`${siteUrl}/wp-json/wwa/v1/webhook`);
    }, []);

    const fetchData = async () => {
        try {
            const [settingsData, providersData, statusesData] = await Promise.all([
                apiFetch('/settings'),
                apiFetch('/providers'),
                apiFetch('/order-statuses')
            ]);
            setSettings(settingsData);
            setProviders(providersData);
            setOrderStatuses(statusesData);
        } catch (error) {
            showNotification('error', 'Ayarlar yüklenemedi: ' + error.message);
        } finally {
            setLoading(false);
        }
    };

    const fetchLicense = async () => {
        try {
            const response = await apiFetch('/license');
            setLicense(response);
            // Parent component'a da bildir
            if (onLicenseChange) {
                onLicenseChange(response);
            }
        } catch (error) {
            console.error('License fetch error:', error);
        }
    };

    const fetchLanguages = async () => {
        try {
            const response = await apiFetch('/languages');
            setLanguageInfo(response);
            // Özel dilleri de yükle
            const customLangs = await apiFetch('/settings').then(s => s.custom_languages || []);
            setCustomLanguages(customLangs);
        } catch (error) {
            console.error('Languages fetch error:', error);
        }
    };

    const addCustomLanguage = () => {
        if (!newLangCode || !newLangName) {
            showNotification('error', 'Dil kodu ve adı gerekli');
            return;
        }
        if (customLanguages.find(l => l.code === newLangCode)) {
            showNotification('error', 'Bu dil kodu zaten mevcut');
            return;
        }
        const newLang = { code: newLangCode, name: newLangName, native_name: newLangName };
        const updated = [...customLanguages, newLang];
        setCustomLanguages(updated);
        updateSetting('custom_languages', updated);
        setNewLangCode('');
        setNewLangName('');
        showNotification('success', 'Dil eklendi');
    };

    const removeCustomLanguage = (code) => {
        const updated = customLanguages.filter(l => l.code !== code);
        setCustomLanguages(updated);
        updateSetting('custom_languages', updated);
        showNotification('success', 'Dil kaldırıldı');
    };

    const activateLicense = async () => {
        if (!licenseKey) {
            showNotification('error', 'Lütfen lisans anahtarını girin');
            return;
        }
        setActivating(true);
        try {
            const response = await apiFetch('/license', {
                method: 'POST',
                body: JSON.stringify({ license_key: licenseKey })
            });
            if (response.success) {
                showNotification('success', response.message);
                setLicenseKey('');
                const newLicense = {
                    is_valid: true,
                    status: 'active',
                    type: response.license?.type || 'lifetime',
                    is_lifetime: true,
                    pro_features: {
                        flow_builder: true,
                        abandoned_cart: true,
                        chatbot: true,
                        waitlist: true,
                        analytics: true,
                        multi_language: true
                    }
                };
                setLicense(newLicense);
                if (onLicenseChange) {
                    onLicenseChange(newLicense);
                }
            } else {
                showNotification('error', response.message);
            }
        } catch (error) {
            showNotification('error', 'Aktivasyon hatası: ' + error.message);
        } finally {
            setActivating(false);
        }
    };

    const deactivateLicense = async () => {
        if (!confirm('Lisansı deaktif etmek istediğinize emin misiniz?')) return;
        try {
            const response = await apiFetch('/license', { method: 'DELETE' });
            if (response.success) {
                showNotification('success', response.message);
                // Lisansı hemen sıfırla
                const emptyLicense = { is_valid: false, type: 'free' };
                setLicense(emptyLicense);
                // Parent component'a hemen bildir
                if (onLicenseChange) {
                    onLicenseChange(emptyLicense);
                }
            } else {
                showNotification('error', response.message);
            }
        } catch (error) {
            showNotification('error', 'Deaktivasyon hatası: ' + error.message);
        }
    };

    // Test API connection
    const testConnection = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const response = await apiFetch('/test-connection', {
                method: 'POST'
            });
            setTestResult(response);
            if (response.success) {
                showNotification('success', 'API bağlantısı başarılı!');
            } else {
                showNotification('error', response.message || 'Bağlantı hatası');
            }
        } catch (error) {
            setTestResult({ success: false, message: error.message });
            showNotification('error', 'Test hatası: ' + error.message);
        } finally {
            setTesting(false);
        }
    };

    // Send test message
    const sendTestMessage = async () => {
        if (!testPhone) {
            showNotification('error', 'Lütfen test telefon numarası girin');
            return;
        }
        setSendingTest(true);
        try {
            const response = await apiFetch('/send-test-message', {
                method: 'POST',
                body: JSON.stringify({
                    phone: testPhone,
                    message: '🎉 Woo WhatsApp Pro test mesajı başarılı!\n\nAPI bağlantınız çalışıyor.'
                })
            });
            if (response.success) {
                showNotification('success', 'Test mesajı gönderildi!');
            } else {
                showNotification('error', response.message || 'Mesaj gönderilemedi');
            }
        } catch (error) {
            showNotification('error', 'Gönderim hatası: ' + error.message);
        } finally {
            setSendingTest(false);
        }
    };

    // Copy webhook URL
    const copyWebhookUrl = () => {
        navigator.clipboard.writeText(webhookUrl).then(() => {
            showNotification('success', 'Webhook URL kopyalandı!');
        }).catch(() => {
            showNotification('error', 'Kopyalama hatası');
        });
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            console.log('Saving settings:', settings);
            const response = await apiFetch('/settings', {
                method: 'POST',
                body: JSON.stringify(settings)
            });
            console.log('Save response:', response);

            if (response.success) {
                showNotification('success', 'Ayarlar kaydedildi!');
                // Verification bilgisini kontrol et
                if (response.verification) {
                    console.log('Verification from DB:', response.verification);
                }
            } else {
                showNotification('error', response.message || 'Kaydetme hatası');
            }
        } catch (error) {
            console.error('Save error:', error);
            showNotification('error', 'Hata: ' + error.message);
        } finally {
            setSaving(false);
        }
    };

    const updateSetting = (key, value) => {
        setSettings(prev => ({ ...prev, [key]: value }));
    };

    const toggleOrderStatus = (status) => {
        const currentStatuses = settings.order_statuses || [];
        const newStatuses = currentStatuses.includes(status)
            ? currentStatuses.filter(s => s !== status)
            : [...currentStatuses, status];
        updateSetting('order_statuses', newStatuses);
    };

    if (loading) {
        return (
            <div className="wwa-loading">
                <div className="wwa-spinner"></div>
            </div>
        );
    }

    const currentProvider = providers[settings.api_provider] || {};

    return (
        <div className="wwa-settings">
            {/* Section Tabs */}
            <div className="wwa-section-tabs">
                {['general', 'api', 'notifications', 'advanced', 'license'].map(section => (
                    <button
                        key={section}
                        className={`wwa-section-tab ${activeSection === section ? 'active' : ''}`}
                        onClick={() => setActiveSection(section)}
                    >
                        {section === 'general' && '⚙️ Genel'}
                        {section === 'api' && '🔌 API Ayarları'}
                        {section === 'notifications' && '🔔 Bildirimler'}
                        {section === 'advanced' && '🛠️ Gelişmiş'}
                        {section === 'license' && '🔑 Lisans'}
                    </button>
                ))}
            </div>

            {/* General Section */}
            {activeSection === 'general' && (
                <div className="wwa-card">
                    <h3 className="wwa-card-title">Genel Ayarlar</h3>

                    <div className="wwa-form-group">
                        <label>Plugin Durumu</label>
                        <div className="wwa-toggle">
                            <div
                                className={`wwa-toggle-switch ${settings.enabled === 'yes' ? 'active' : ''}`}
                                onClick={() => updateSetting('enabled', settings.enabled === 'yes' ? 'no' : 'yes')}
                            />
                            <span className="wwa-toggle-label">
                                {settings.enabled === 'yes' ? 'Aktif' : 'Devre Dışı'}
                            </span>
                        </div>
                        <p className="wwa-form-help">Plugin kapatılırsa hiçbir bildirim gönderilmez</p>
                    </div>

                    <div className="wwa-form-group">
                        <label>Yönetici Telefon Numarası</label>
                        <input
                            type="text"
                            value={settings.admin_phone || ''}
                            onChange={(e) => updateSetting('admin_phone', e.target.value)}
                            placeholder="+90 5XX XXX XXXX"
                        />
                        <p className="wwa-form-help">Yönetici bildirimleri bu numaraya gönderilir</p>
                    </div>
                </div>
            )}

            {/* API Section */}
            {activeSection === 'api' && (
                <div className="wwa-card">
                    <h3 className="wwa-card-title">API Yapılandırması</h3>

                    <div className="wwa-form-group">
                        <label>API Sağlayıcısı</label>
                        <div className="wwa-provider-grid">
                            {Object.entries(providers).map(([key, provider]) => (
                                <div
                                    key={key}
                                    className={`wwa-provider-card ${settings.api_provider === key ? 'selected' : ''}`}
                                    onClick={() => updateSetting('api_provider', key)}
                                >
                                    <div className="wwa-provider-name">{provider.name}</div>
                                    <div className="wwa-provider-desc">{provider.description}</div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* WhatsApp Business API Fields */}
                    {settings.api_provider === 'whatsapp_business' && (
                        <>
                            <div className="wwa-form-group">
                                <label>Access Token</label>
                                <input
                                    type="password"
                                    value={settings.api_token || ''}
                                    onChange={(e) => updateSetting('api_token', e.target.value)}
                                    placeholder="EAAxxxxxxx..."
                                />
                                <p className="wwa-form-help">
                                    Meta Business Suite'den alınan erişim tokenı
                                    <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener noreferrer" style={{ marginLeft: '5px' }}>
                                        Nasıl alınır? →
                                    </a>
                                </p>
                            </div>
                            <div className="wwa-form-group">
                                <label>Phone Number ID</label>
                                <input
                                    type="text"
                                    value={settings.phone_number_id || ''}
                                    onChange={(e) => updateSetting('phone_number_id', e.target.value)}
                                    placeholder="1234567890123456"
                                />
                            </div>
                        </>
                    )}

                    {/* Twilio Fields */}
                    {settings.api_provider === 'twilio' && (
                        <>
                            <div className="wwa-form-group">
                                <label>Account SID</label>
                                <input
                                    type="text"
                                    value={settings.twilio_account_sid || ''}
                                    onChange={(e) => updateSetting('twilio_account_sid', e.target.value)}
                                    placeholder="ACxxxxxxx..."
                                />
                            </div>
                            <div className="wwa-form-group">
                                <label>Auth Token</label>
                                <input
                                    type="password"
                                    value={settings.twilio_auth_token || ''}
                                    onChange={(e) => updateSetting('twilio_auth_token', e.target.value)}
                                    placeholder="xxxxxxx..."
                                />
                            </div>
                            <div className="wwa-form-group">
                                <label>WhatsApp Phone Number</label>
                                <input
                                    type="text"
                                    value={settings.twilio_phone_number || ''}
                                    onChange={(e) => updateSetting('twilio_phone_number', e.target.value)}
                                    placeholder="+1234567890"
                                />
                            </div>
                        </>
                    )}

                    {/* Ultramsg Fields */}
                    {settings.api_provider === 'ultramsg' && (
                        <>
                            <div className="wwa-form-group">
                                <label>Instance ID</label>
                                <input
                                    type="text"
                                    value={settings.ultramsg_instance_id || ''}
                                    onChange={(e) => updateSetting('ultramsg_instance_id', e.target.value)}
                                    placeholder="instance123"
                                />
                            </div>
                            <div className="wwa-form-group">
                                <label>Token</label>
                                <input
                                    type="password"
                                    value={settings.ultramsg_token || ''}
                                    onChange={(e) => updateSetting('ultramsg_token', e.target.value)}
                                    placeholder="token123..."
                                />
                            </div>
                        </>
                    )}

                    {/* WATI Fields */}
                    {settings.api_provider === 'wati' && (
                        <>
                            <div className="wwa-form-group">
                                <label>API URL</label>
                                <input
                                    type="url"
                                    value={settings.wati_api_url || ''}
                                    onChange={(e) => updateSetting('wati_api_url', e.target.value)}
                                    placeholder="https://live-server-xxxxx.wati.io"
                                />
                            </div>
                            <div className="wwa-form-group">
                                <label>API Token</label>
                                <input
                                    type="password"
                                    value={settings.wati_api_token || ''}
                                    onChange={(e) => updateSetting('wati_api_token', e.target.value)}
                                    placeholder="Bearer token..."
                                />
                            </div>
                        </>
                    )}

                    {currentProvider.docs_url && (
                        <div className="wwa-alert info" style={{ marginTop: '20px' }}>
                            📚 <a href={currentProvider.docs_url} target="_blank" rel="noopener noreferrer">
                                {currentProvider.name} API Dokümantasyonu
                            </a>
                        </div>
                    )}

                    {/* Connection Test Section */}
                    <hr style={{ margin: '30px 0', border: 'none', borderTop: '1px solid #ddd' }} />

                    <h4>🔌 Bağlantı Testi</h4>
                    <p className="wwa-form-help" style={{ marginBottom: '15px' }}>
                        API bilgilerinizi girdikten sonra bağlantıyı test edin
                    </p>

                    <div className="wwa-btn-group" style={{ marginBottom: '20px' }}>
                        <button
                            className="wwa-btn wwa-btn-secondary"
                            onClick={testConnection}
                            disabled={testing}
                        >
                            {testing ? '⏳ Test ediliyor...' : '🔍 Bağlantıyı Test Et'}
                        </button>
                    </div>

                    {testResult && (
                        <div className={`wwa-alert ${testResult.success ? 'success' : 'error'}`} style={{ marginBottom: '20px' }}>
                            {testResult.success ? '✅ ' : '❌ '}
                            {testResult.message}
                            {testResult.details && (
                                <div style={{ marginTop: '10px', fontSize: '12px', opacity: 0.8 }}>
                                    <strong>Detaylar:</strong>
                                    <pre style={{ margin: '5px 0', whiteSpace: 'pre-wrap' }}>
                                        {JSON.stringify(testResult.details, null, 2)}
                                    </pre>
                                </div>
                            )}
                        </div>
                    )}

                    {testResult?.success && (
                        <div className="wwa-form-group">
                            <label>Test Mesajı Gönder</label>
                            <div style={{ display: 'flex', gap: '10px' }}>
                                <input
                                    type="text"
                                    value={testPhone}
                                    onChange={(e) => setTestPhone(e.target.value)}
                                    placeholder="+90 5XX XXX XXXX"
                                    style={{ flex: 1 }}
                                />
                                <button
                                    className="wwa-btn wwa-btn-primary"
                                    onClick={sendTestMessage}
                                    disabled={sendingTest || !testPhone}
                                >
                                    {sendingTest ? '📤 Gönderiliyor...' : '📤 Gönder'}
                                </button>
                            </div>
                            <p className="wwa-form-help">WhatsApp numaranıza test mesajı gönderin</p>
                        </div>
                    )}

                    {/* Webhook URL Section */}
                    <hr style={{ margin: '30px 0', border: 'none', borderTop: '1px solid #ddd' }} />

                    <h4>🔗 Webhook URL (Chatbot için)</h4>
                    <p className="wwa-form-help" style={{ marginBottom: '15px' }}>
                        Gelen mesajları almak için bu URL'yi API sağlayıcınızın Webhook ayarlarına ekleyin
                    </p>

                    {/* Sağlayıcıya göre doğru webhook URL */}
                    <div style={{ marginBottom: '15px' }}>
                        <label style={{ display: 'block', marginBottom: '8px', fontWeight: '500' }}>
                            {settings.api_provider === 'ultramsg' && 'Ultramsg Webhook URL:'}
                            {settings.api_provider === 'twilio' && 'Twilio Webhook URL:'}
                            {settings.api_provider === 'whatsapp_business' && 'Meta Webhook URL:'}
                            {settings.api_provider === 'wati' && 'WATI Webhook URL:'}
                            {!settings.api_provider && 'Webhook URL:'}
                        </label>
                        <div className="wwa-webhook-url" style={{
                            display: 'flex',
                            gap: '10px',
                            alignItems: 'center',
                            background: '#e8f5e9',
                            padding: '12px 15px',
                            borderRadius: '6px',
                            border: '1px solid #25D366'
                        }}>
                            <code style={{ flex: 1, wordBreak: 'break-all', fontSize: '13px', fontWeight: '500' }}>
                                {settings.api_provider === 'ultramsg' && `${webhookUrl}/ultramsg`}
                                {settings.api_provider === 'twilio' && `${webhookUrl}/twilio`}
                                {settings.api_provider === 'whatsapp_business' && webhookUrl}
                                {settings.api_provider === 'wati' && webhookUrl}
                                {!settings.api_provider && webhookUrl}
                            </code>
                            <button
                                className="wwa-btn wwa-btn-secondary"
                                onClick={() => {
                                    let url = webhookUrl;
                                    if (settings.api_provider === 'ultramsg') url += '/ultramsg';
                                    if (settings.api_provider === 'twilio') url += '/twilio';
                                    navigator.clipboard.writeText(url).then(() => {
                                        showNotification('success', 'Webhook URL kopyalandı!');
                                    });
                                }}
                                style={{ whiteSpace: 'nowrap' }}
                            >
                                📋 Kopyala
                            </button>
                        </div>
                    </div>

                    <div className="wwa-alert info" style={{ marginBottom: '15px' }}>
                        💡 <strong>İpucu:</strong> Webhook, müşterilerden gelen WhatsApp mesajlarını almanızı sağlar.
                        Chatbot özelliğini kullanmak için webhook'u yapılandırmanız gerekir.
                    </div>

                    {/* Sağlayıcıya göre kurulum talimatları */}
                    {settings.api_provider === 'ultramsg' && (
                        <div style={{ background: '#fff3e0', padding: '15px', borderRadius: '8px', marginBottom: '15px' }}>
                            <strong>📝 Ultramsg Webhook Kurulumu:</strong>
                            <ol style={{ margin: '10px 0 0', paddingLeft: '20px', lineHeight: '1.8' }}>
                                <li><a href="https://ultramsg.com" target="_blank" rel="noopener noreferrer">ultramsg.com</a> paneline giriş yapın</li>
                                <li>Sol menüden <strong>"Webhook"</strong> seçin</li>
                                <li>Webhook URL alanına yukarıdaki URL'yi yapıştırın</li>
                                <li><strong>"Enable"</strong> butonuna tıklayarak aktifleştirin</li>
                                <li>Events kısmında <strong>"messages"</strong> seçili olsun</li>
                            </ol>
                        </div>
                    )}

                    {settings.api_provider === 'twilio' && (
                        <div style={{ background: '#fff3e0', padding: '15px', borderRadius: '8px', marginBottom: '15px' }}>
                            <strong>📝 Twilio Webhook Kurulumu:</strong>
                            <ol style={{ margin: '10px 0 0', paddingLeft: '20px', lineHeight: '1.8' }}>
                                <li><a href="https://console.twilio.com" target="_blank" rel="noopener noreferrer">Twilio Console</a>'a giriş yapın</li>
                                <li>Messaging → Settings → WhatsApp Sandbox Settings</li>
                                <li>"When a message comes in" alanına yukarıdaki URL'yi yapıştırın</li>
                                <li>HTTP Method olarak <strong>POST</strong> seçin</li>
                            </ol>
                        </div>
                    )}

                    {/* Hosting Güvenlik Uyarısı */}
                    <div style={{ background: '#ffebee', padding: '15px', borderRadius: '8px', border: '1px solid #f44336' }}>
                        <strong>⚠️ Önemli: Webhook "Forbidden" Hatası Alıyorsanız</strong>
                        <p style={{ margin: '10px 0', fontSize: '13px', lineHeight: '1.6' }}>
                            Bazı hostingler (ModSecurity/WAF) webhook POST isteklerini engelleyebilir.
                            Bu durumda hosting panelinizden aşağıdaki ayarları yapmanız gerekir:
                        </p>
                        <details style={{ marginTop: '10px' }}>
                            <summary style={{ cursor: 'pointer', fontWeight: '500', color: '#c62828' }}>
                                🔧 Çözüm Adımları (tıklayın)
                            </summary>
                            <div style={{ marginTop: '10px', padding: '10px', background: '#fff', borderRadius: '4px' }}>
                                <p><strong>Seçenek 1: Hosting Panelinden</strong></p>
                                <ol style={{ margin: '5px 0 15px', paddingLeft: '20px', fontSize: '12px', lineHeight: '1.8' }}>
                                    <li>cPanel/Plesk'e giriş yapın</li>
                                    <li><strong>"ModSecurity"</strong> veya <strong>"WAF"</strong> ayarını bulun</li>
                                    <li>Webhook URL'sini whitelist'e ekleyin veya geçici olarak kapatın</li>
                                </ol>

                                <p><strong>Seçenek 2: .htaccess ile</strong></p>
                                <p style={{ fontSize: '12px', marginBottom: '5px' }}>
                                    WordPress ana dizinindeki <code>.htaccess</code> dosyasının en başına ekleyin:
                                </p>
                                <pre style={{
                                    background: '#263238',
                                    color: '#aed581',
                                    padding: '10px',
                                    borderRadius: '4px',
                                    fontSize: '11px',
                                    overflow: 'auto',
                                    whiteSpace: 'pre-wrap'
                                }}>{`# Woo WhatsApp Webhook için ModSecurity bypass
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/wp-json/wwa/v1/webhook [NC]
    RewriteRule .* - [E=noabort:1,L]
</IfModule>

<IfModule mod_security2.c>
    <LocationMatch "/wp-json/wwa/v1/webhook">
        SecRuleEngine Off
    </LocationMatch>
</IfModule>`}</pre>

                                <p style={{ marginTop: '15px' }}><strong>Seçenek 3: Hosting Desteğine Yazın</strong></p>
                                <p style={{ fontSize: '12px', color: '#666' }}>
                                    "POST isteklerini <code>/wp-json/wwa/v1/webhook/ultramsg</code> endpoint'ine izin vermek istiyorum.
                                    ModSecurity bu URL'yi engelliyor. Lütfen bu URL için güvenlik kuralını devre dışı bırakın."
                                </p>
                            </div>
                        </details>
                    </div>
                </div>
            )}

            {/* Notifications Section */}
            {activeSection === 'notifications' && (
                <div className="wwa-card">
                    <h3 className="wwa-card-title">Bildirim Ayarları</h3>

                    <div className="wwa-form-group">
                        <label>Müşteriye Bildirim Gönder</label>
                        <div className="wwa-toggle">
                            <div
                                className={`wwa-toggle-switch ${settings.send_to_customer === 'yes' ? 'active' : ''}`}
                                onClick={() => updateSetting('send_to_customer', settings.send_to_customer === 'yes' ? 'no' : 'yes')}
                            />
                            <span className="wwa-toggle-label">
                                {settings.send_to_customer === 'yes' ? 'Aktif' : 'Kapalı'}
                            </span>
                        </div>
                    </div>

                    <div className="wwa-form-group">
                        <label>Yöneticiye Bildirim Gönder</label>
                        <div className="wwa-toggle">
                            <div
                                className={`wwa-toggle-switch ${settings.send_to_admin === 'yes' ? 'active' : ''}`}
                                onClick={() => updateSetting('send_to_admin', settings.send_to_admin === 'yes' ? 'no' : 'yes')}
                            />
                            <span className="wwa-toggle-label">
                                {settings.send_to_admin === 'yes' ? 'Aktif' : 'Kapalı'}
                            </span>
                        </div>
                    </div>

                    <div className="wwa-form-group">
                        <label>Bildirim Gönderilecek Sipariş Durumları</label>
                        <p className="wwa-form-help" style={{ marginBottom: '10px' }}>
                            Hangi sipariş durumlarında WhatsApp bildirimi gönderilsin?
                        </p>
                        <div className="wwa-checkbox-group">
                            {Object.entries(orderStatuses).map(([status, label]) => (
                                <label
                                    key={status}
                                    className={`wwa-checkbox ${(settings.order_statuses || []).includes(status) ? 'checked' : ''}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={(settings.order_statuses || []).includes(status)}
                                        onChange={() => toggleOrderStatus(status)}
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Advanced Section */}
            {activeSection === 'advanced' && (
                <div className="wwa-card">
                    <h3 className="wwa-card-title">Gelişmiş Ayarlar</h3>

                    <div className="wwa-form-group">
                        <label>Debug Modu</label>
                        <div className="wwa-toggle">
                            <div
                                className={`wwa-toggle-switch ${settings.debug_mode === 'yes' ? 'active' : ''}`}
                                onClick={() => updateSetting('debug_mode', settings.debug_mode === 'yes' ? 'no' : 'yes')}
                            />
                            <span className="wwa-toggle-label">
                                {settings.debug_mode === 'yes' ? 'Aktif' : 'Kapalı'}
                            </span>
                        </div>
                        <p className="wwa-form-help">
                            Aktifken mesajlar gerçekte gönderilmez, sadece simüle edilir ve loglanır
                        </p>
                    </div>

                    <div className="wwa-form-group">
                        <label>Log Saklama Süresi (Gün)</label>
                        <input
                            type="number"
                            value={settings.log_retention_days || 30}
                            onChange={(e) => updateSetting('log_retention_days', parseInt(e.target.value))}
                            min="1"
                            max="365"
                        />
                        <p className="wwa-form-help">Bu süreden eski loglar otomatik silinir</p>
                    </div>

                    <hr style={{ margin: '30px 0', border: 'none', borderTop: '1px solid #ddd' }} />

                    <h4>🌐 Dil Ayarları</h4>
                    <p className="wwa-form-help" style={{ marginBottom: '15px' }}>
                        Çoklu dil desteği ile farklı dillerde şablon tanımlayabilirsiniz.
                        {languageInfo.enabled && languageInfo.plugin && (
                            <span style={{ display: 'block', marginTop: '5px', color: '#25D366' }}>
                                ✓ {languageInfo.plugin.toUpperCase()} eklentisi algılandı - otomatik dil desteği aktif
                            </span>
                        )}
                    </p>

                    {/* Mevcut Diller */}
                    {(languageInfo.languages?.length > 0 || customLanguages.length > 0) && (
                        <div style={{ marginBottom: '20px' }}>
                            <label style={{ display: 'block', marginBottom: '10px', fontWeight: '500' }}>Aktif Diller:</label>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                                {/* WPML/Polylang'dan gelen diller */}
                                {languageInfo.languages?.map(lang => (
                                    <div key={lang.code} style={{
                                        padding: '8px 12px',
                                        background: lang.default ? '#e8f5e9' : '#f5f5f5',
                                        border: lang.default ? '1px solid #25D366' : '1px solid #ddd',
                                        borderRadius: '20px',
                                        fontSize: '13px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '6px'
                                    }}>
                                        {lang.flag && <img src={lang.flag} alt="" style={{ width: '16px', height: '12px' }} />}
                                        <span>{lang.native_name || lang.name}</span>
                                        <code style={{ fontSize: '10px', opacity: 0.7 }}>({lang.code})</code>
                                        {lang.default && <span style={{ fontSize: '10px', color: '#25D366' }}>✓ varsayılan</span>}
                                    </div>
                                ))}
                                {/* Manuel eklenen diller */}
                                {customLanguages.map(lang => (
                                    <div key={lang.code} style={{
                                        padding: '8px 12px',
                                        background: '#fff3e0',
                                        border: '1px solid #ff9800',
                                        borderRadius: '20px',
                                        fontSize: '13px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '6px'
                                    }}>
                                        <span>{lang.name}</span>
                                        <code style={{ fontSize: '10px', opacity: 0.7 }}>({lang.code})</code>
                                        <button
                                            onClick={() => removeCustomLanguage(lang.code)}
                                            style={{
                                                background: 'none',
                                                border: 'none',
                                                cursor: 'pointer',
                                                color: '#f44336',
                                                fontSize: '14px',
                                                padding: '0 0 0 4px'
                                            }}
                                            title="Dili kaldır"
                                        >×</button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Manuel Dil Ekleme */}
                    <div style={{ background: '#f5f5f5', padding: '15px', borderRadius: '8px' }}>
                        <label style={{ display: 'block', marginBottom: '10px', fontWeight: '500' }}>
                            ➕ Manuel Dil Ekle
                        </label>
                        <p style={{ fontSize: '12px', color: '#666', marginBottom: '10px' }}>
                            WPML/Polylang kullanmıyorsanız manuel olarak dil ekleyebilirsiniz
                        </p>
                        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                            <input
                                type="text"
                                placeholder="Dil kodu (örn: en)"
                                value={newLangCode}
                                onChange={(e) => setNewLangCode(e.target.value.toLowerCase())}
                                style={{ width: '120px', padding: '8px 12px' }}
                                maxLength={5}
                            />
                            <input
                                type="text"
                                placeholder="Dil adı (örn: English)"
                                value={newLangName}
                                onChange={(e) => setNewLangName(e.target.value)}
                                style={{ flex: 1, minWidth: '150px', padding: '8px 12px' }}
                            />
                            <button
                                className="wwa-btn wwa-btn-primary"
                                onClick={addCustomLanguage}
                                style={{ padding: '8px 16px' }}
                            >
                                Ekle
                            </button>
                        </div>
                        <p style={{ fontSize: '11px', color: '#999', marginTop: '8px' }}>
                            Yaygın kodlar: tr (Türkçe), en (English), de (Deutsch), fr (Français), ar (العربية), ru (Русский)
                        </p>
                    </div>

                    <div className="wwa-alert info" style={{ marginTop: '15px' }}>
                        💡 Dil ekledikten sonra <strong>Şablonlar</strong> sayfasından her dil için ayrı mesaj şablonu tanımlayabilirsiniz.
                    </div>

                    <hr style={{ margin: '30px 0', border: 'none', borderTop: '1px solid #ddd' }} />

                    <h4>Veri Yönetimi</h4>

                    <div className="wwa-btn-group" style={{ marginTop: '15px' }}>
                        <button
                            className="wwa-btn wwa-btn-secondary"
                            onClick={async () => {
                                try {
                                    const response = await apiFetch('/export');
                                    const dataStr = JSON.stringify(response.data, null, 2);
                                    const blob = new Blob([dataStr], { type: 'application/json' });
                                    const url = URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    a.href = url;
                                    a.download = 'woo-whatsapp-settings.json';
                                    a.click();
                                    showNotification('success', 'Ayarlar dışa aktarıldı');
                                } catch (error) {
                                    showNotification('error', 'Dışa aktarma hatası');
                                }
                            }}
                        >
                            📥 Ayarları Dışa Aktar
                        </button>
                        <button
                            className="wwa-btn wwa-btn-secondary"
                            onClick={() => {
                                const input = document.createElement('input');
                                input.type = 'file';
                                input.accept = '.json';
                                input.onchange = async (e) => {
                                    const file = e.target.files[0];
                                    if (file) {
                                        const reader = new FileReader();
                                        reader.onload = async (event) => {
                                            try {
                                                const data = JSON.parse(event.target.result);
                                                await apiFetch('/import', {
                                                    method: 'POST',
                                                    body: JSON.stringify({ data })
                                                });
                                                showNotification('success', 'Ayarlar içe aktarıldı');
                                                fetchData();
                                            } catch (error) {
                                                showNotification('error', 'İçe aktarma hatası');
                                            }
                                        };
                                        reader.readAsText(file);
                                    }
                                };
                                input.click();
                            }}
                        >
                            📤 Ayarları İçe Aktar
                        </button>
                    </div>
                </div>
            )}

            {/* License Section */}
            {activeSection === 'license' && (
                <div className="wwa-card">
                    <h3 className="wwa-card-title">🔑 Lisans Yönetimi</h3>

                    {license?.is_valid ? (
                        <>
                            <div className="wwa-alert success" style={{ marginBottom: '20px' }}>
                                ✅ Lisansınız aktif! Tüm Pro özelliklerini ömür boyu kullanabilirsiniz.
                            </div>

                            <div style={{
                                background: 'linear-gradient(135deg, #25D366 0%, #128C7E 100%)',
                                padding: '20px',
                                borderRadius: '12px',
                                color: 'white',
                                marginBottom: '20px'
                            }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <div>
                                        <h4 style={{ margin: 0, fontSize: '18px' }}>♾️ Ömür Boyu Lisans</h4>
                                        <p style={{ margin: '5px 0 0', opacity: 0.9 }}>
                                            Sınırsız erişim - süre sınırı yok
                                        </p>
                                    </div>
                                    <div style={{ textAlign: 'right' }}>
                                        <div style={{ fontSize: '32px' }}>∞</div>
                                        <div style={{ fontSize: '12px', opacity: 0.8 }}>lifetime</div>
                                    </div>
                                </div>
                            </div>

                            <div style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(2, 1fr)',
                                gap: '20px',
                                marginBottom: '20px'
                            }}>
                                <div style={{ background: '#f5f5f5', padding: '15px', borderRadius: '8px' }}>
                                    <strong>Lisans Anahtarı</strong>
                                    <p style={{ margin: '5px 0 0', fontFamily: 'monospace' }}>{license.license_key}</p>
                                </div>
                                <div style={{ background: '#f5f5f5', padding: '15px', borderRadius: '8px' }}>
                                    <strong>Lisans Türü</strong>
                                    <p style={{ margin: '5px 0 0' }}>♾️ Ömür Boyu (Lifetime)</p>
                                </div>
                                <div style={{ background: '#f5f5f5', padding: '15px', borderRadius: '8px' }}>
                                    <strong>Aktivasyon Tarihi</strong>
                                    <p style={{ margin: '5px 0 0' }}>{license.activated_at || '-'}</p>
                                </div>
                                <div style={{ background: '#e8f5e9', padding: '15px', borderRadius: '8px' }}>
                                    <strong>Durum</strong>
                                    <p style={{ margin: '5px 0 0', color: '#25D366' }}>✓ Süresiz Aktif</p>
                                </div>
                            </div>

                            <hr style={{ margin: '25px 0', border: 'none', borderTop: '1px solid #ddd' }} />

                            <h4>Pro Özellikler</h4>
                            <div style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(3, 1fr)',
                                gap: '10px',
                                marginTop: '15px'
                            }}>
                                {[
                                    { key: 'flow_builder', label: 'Flow Builder' },
                                    { key: 'abandoned_cart', label: 'Sepet Kurtarma' },
                                    { key: 'chatbot', label: 'Chatbot' },
                                    { key: 'waitlist', label: 'Stok Bildirimi' },
                                    { key: 'analytics', label: 'Analitik' },
                                    { key: 'multi_language', label: 'Çoklu Dil' }
                                ].map(feature => (
                                    <div key={feature.key} style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '8px',
                                        padding: '10px',
                                        background: license.pro_features?.[feature.key] ? '#e8f5e9' : '#f5f5f5',
                                        borderRadius: '6px'
                                    }}>
                                        <span style={{ color: license.pro_features?.[feature.key] ? '#25D366' : '#999' }}>
                                            {license.pro_features?.[feature.key] ? '✓' : '✗'}
                                        </span>
                                        {feature.label}
                                    </div>
                                ))}
                            </div>

                            <div className="wwa-btn-group" style={{ marginTop: '25px', display: 'flex', gap: '10px' }}>
                                <button
                                    className="wwa-btn wwa-btn-secondary"
                                    onClick={deactivateLicense}
                                    style={{ color: '#dc3545', borderColor: '#dc3545' }}
                                >
                                    🔓 Lisansı Deaktif Et
                                </button>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="wwa-alert warning" style={{ marginBottom: '20px' }}>
                                ⚠️ Aktif lisansınız yok. Pro özellikleri kullanmak için lisans aktifleştirin.
                            </div>

                            <div className="wwa-form-group">
                                <label>Lisans Anahtarı (Envato Purchase Code)</label>
                                <div style={{ display: 'flex', gap: '10px' }}>
                                    <input
                                        type="text"
                                        value={licenseKey}
                                        onChange={(e) => setLicenseKey(e.target.value)}
                                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                        style={{ flex: 1, fontFamily: 'monospace', letterSpacing: '1px' }}
                                    />
                                    <button
                                        className="wwa-btn wwa-btn-primary"
                                        onClick={activateLicense}
                                        disabled={activating || !licenseKey}
                                    >
                                        {activating ? '⏳ Aktifleştiriliyor...' : '🔐 Aktifleştir'}
                                    </button>
                                </div>
                                <p className="wwa-form-help">
                                    CodeCanyon satın alma kodunuzu (Purchase Code) girin. Envato hesabınızdan bulabilirsiniz.
                                </p>
                            </div>

                            <hr style={{ margin: '25px 0', border: 'none', borderTop: '1px solid #ddd' }} />

                            <h4>Pro Özellikleri Nelerdir?</h4>
                            <div style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(2, 1fr)',
                                gap: '15px',
                                marginTop: '15px'
                            }}>
                                <div style={{ padding: '15px', background: '#f5f5f5', borderRadius: '8px' }}>
                                    <strong>🔄 Flow Builder</strong>
                                    <p style={{ margin: '5px 0 0', fontSize: '13px', color: '#666' }}>
                                        Gelişmiş otomasyon akışları oluşturun
                                    </p>
                                </div>
                                <div style={{ padding: '15px', background: '#f5f5f5', borderRadius: '8px' }}>
                                    <strong>🛒 Sepet Kurtarma</strong>
                                    <p style={{ margin: '5px 0 0', fontSize: '13px', color: '#666' }}>
                                        Terk edilmiş sepetleri WhatsApp ile kurtarın
                                    </p>
                                </div>
                                <div style={{ padding: '15px', background: '#f5f5f5', borderRadius: '8px' }}>
                                    <strong>🤖 Chatbot</strong>
                                    <p style={{ margin: '5px 0 0', fontSize: '13px', color: '#666' }}>
                                        Otomatik müşteri yanıtları
                                    </p>
                                </div>
                                <div style={{ padding: '15px', background: '#f5f5f5', borderRadius: '8px' }}>
                                    <strong>🔔 Stok Bildirimi</strong>
                                    <p style={{ margin: '5px 0 0', fontSize: '13px', color: '#666' }}>
                                        Ürün stoğa girdiğinde bildirim
                                    </p>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            )}

            {/* Save Button */}
            {activeSection !== 'license' && (
                <div className="wwa-btn-group" style={{ marginTop: '20px' }}>
                    <button
                        className="wwa-btn wwa-btn-primary"
                        onClick={handleSave}
                        disabled={saving}
                    >
                        {saving ? '💾 Kaydediliyor...' : '💾 Ayarları Kaydet'}
                    </button>
                </div>
            )}

            <style>{`
                .wwa-section-tabs {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 20px;
                    flex-wrap: wrap;
                }
                .wwa-section-tab {
                    padding: 10px 20px;
                    border: 1px solid #ddd;
                    background: #fff;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    transition: all 0.2s;
                }
                .wwa-section-tab:hover {
                    border-color: #25D366;
                }
                .wwa-section-tab.active {
                    background: #25D366;
                    color: #fff;
                    border-color: #25D366;
                }
            `}</style>
        </div>
    );
};

export default Settings;
