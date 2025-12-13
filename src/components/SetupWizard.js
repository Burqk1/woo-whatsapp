import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../utils/api';

// Ülke kodları listesi
const countryCodes = [
    { code: '+90', country: 'Türkiye', flag: '🇹🇷' },
    { code: '+1', country: 'ABD/Kanada', flag: '🇺🇸' },
    { code: '+44', country: 'İngiltere', flag: '🇬🇧' },
    { code: '+49', country: 'Almanya', flag: '🇩🇪' },
    { code: '+33', country: 'Fransa', flag: '🇫🇷' },
    { code: '+31', country: 'Hollanda', flag: '🇳🇱' },
    { code: '+32', country: 'Belçika', flag: '🇧🇪' },
    { code: '+43', country: 'Avusturya', flag: '🇦🇹' },
    { code: '+41', country: 'İsviçre', flag: '🇨🇭' },
    { code: '+46', country: 'İsveç', flag: '🇸🇪' },
    { code: '+47', country: 'Norveç', flag: '🇳🇴' },
    { code: '+45', country: 'Danimarka', flag: '🇩🇰' },
    { code: '+358', country: 'Finlandiya', flag: '🇫🇮' },
    { code: '+48', country: 'Polonya', flag: '🇵🇱' },
    { code: '+39', country: 'İtalya', flag: '🇮🇹' },
    { code: '+34', country: 'İspanya', flag: '🇪🇸' },
    { code: '+351', country: 'Portekiz', flag: '🇵🇹' },
    { code: '+30', country: 'Yunanistan', flag: '🇬🇷' },
    { code: '+7', country: 'Rusya', flag: '🇷🇺' },
    { code: '+380', country: 'Ukrayna', flag: '🇺🇦' },
    { code: '+994', country: 'Azerbaycan', flag: '🇦🇿' },
    { code: '+993', country: 'Türkmenistan', flag: '🇹🇲' },
    { code: '+998', country: 'Özbekistan', flag: '🇺🇿' },
    { code: '+996', country: 'Kırgızistan', flag: '🇰🇬' },
    { code: '+7', country: 'Kazakistan', flag: '🇰🇿' },
    { code: '+971', country: 'BAE', flag: '🇦🇪' },
    { code: '+966', country: 'S. Arabistan', flag: '🇸🇦' },
    { code: '+974', country: 'Katar', flag: '🇶🇦' },
    { code: '+965', country: 'Kuveyt', flag: '🇰🇼' },
    { code: '+973', country: 'Bahreyn', flag: '🇧🇭' },
    { code: '+968', country: 'Umman', flag: '🇴🇲' },
    { code: '+962', country: 'Ürdün', flag: '🇯🇴' },
    { code: '+961', country: 'Lübnan', flag: '🇱🇧' },
    { code: '+972', country: 'İsrail', flag: '🇮🇱' },
    { code: '+20', country: 'Mısır', flag: '🇪🇬' },
    { code: '+212', country: 'Fas', flag: '🇲🇦' },
    { code: '+213', country: 'Cezayir', flag: '🇩🇿' },
    { code: '+216', country: 'Tunus', flag: '🇹🇳' },
    { code: '+91', country: 'Hindistan', flag: '🇮🇳' },
    { code: '+92', country: 'Pakistan', flag: '🇵🇰' },
    { code: '+86', country: 'Çin', flag: '🇨🇳' },
    { code: '+81', country: 'Japonya', flag: '🇯🇵' },
    { code: '+82', country: 'G. Kore', flag: '🇰🇷' },
    { code: '+61', country: 'Avustralya', flag: '🇦🇺' },
    { code: '+64', country: 'Yeni Zelanda', flag: '🇳🇿' },
    { code: '+55', country: 'Brezilya', flag: '🇧🇷' },
    { code: '+52', country: 'Meksika', flag: '🇲🇽' },
    { code: '+54', country: 'Arjantin', flag: '🇦🇷' },
    { code: '+57', country: 'Kolombiya', flag: '🇨🇴' },
    { code: '+27', country: 'G. Afrika', flag: '🇿🇦' },
];

const SetupWizard = ({ onComplete, onSkip }) => {
    const [currentStep, setCurrentStep] = useState(0);
    const [loading, setLoading] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [countryCode, setCountryCode] = useState('+90');
    const [phoneNumber, setPhoneNumber] = useState('');
    const [setupData, setSetupData] = useState({
        api_provider: 'whatsapp_business',
        api_token: '',
        phone_number_id: '',
        twilio_account_sid: '',
        twilio_auth_token: '',
        twilio_phone_number: '',
        ultramsg_instance_id: '',
        ultramsg_token: '',
        wati_api_url: '',
        wati_api_token: '',
        admin_phone: '',
        order_statuses: ['processing', 'completed', 'on-hold']
    });
    const [webhookInfo, setWebhookInfo] = useState({});

    // Telefon numarası değiştiğinde admin_phone'u güncelle
    useEffect(() => {
        if (phoneNumber) {
            setSetupData(prev => ({ ...prev, admin_phone: countryCode + phoneNumber }));
        }
    }, [countryCode, phoneNumber]);

    // Sadece rakam girişine izin ver
    const handlePhoneChange = (e) => {
        const value = e.target.value.replace(/\D/g, ''); // Sadece rakamları al
        setPhoneNumber(value);
    };

    useEffect(() => {
        loadSetupStatus();
    }, []);

    const loadSetupStatus = async () => {
        try {
            const status = await apiFetch('/setup/status');
            setWebhookInfo({
                url: status.webhook_url,
                verify_token: status.webhook_verify_token
            });
        } catch (error) {
            console.error('Setup status error:', error);
        }
    };

    const steps = [
        { id: 'welcome', title: 'Hosgeldiniz!', icon: '👋' },
        { id: 'api', title: 'API Baglantisi', icon: '🔌' },
        { id: 'phone', title: 'Telefon Numarasi', icon: '📱' },
        { id: 'notifications', title: 'Bildirimler', icon: '🔔' },
        { id: 'webhook', title: 'Webhook', icon: '🔗' },
        { id: 'complete', title: 'Tamamlandi!', icon: '✅' }
    ];

    const handleTestConnection = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const result = await apiFetch('/test-connection', {
                method: 'POST',
                body: JSON.stringify({ provider: setupData.api_provider })
            });
            setTestResult(result);
        } catch (error) {
            setTestResult({ success: false, message: error.message });
        }
        setTesting(false);
    };

    const handleComplete = async () => {
        setLoading(true);
        try {
            await apiFetch('/setup/complete', {
                method: 'POST',
                body: JSON.stringify({
                    api_provider: setupData.api_provider,
                    api_credentials: getApiCredentials(),
                    admin_phone: setupData.admin_phone,
                    order_statuses: setupData.order_statuses
                })
            });
            onComplete?.();
        } catch (error) {
            console.error('Setup error:', error);
        }
        setLoading(false);
    };

    const getApiCredentials = () => {
        switch (setupData.api_provider) {
            case 'whatsapp_business':
                return { api_token: setupData.api_token, phone_number_id: setupData.phone_number_id };
            case 'twilio':
                return { twilio_account_sid: setupData.twilio_account_sid, twilio_auth_token: setupData.twilio_auth_token, twilio_phone_number: setupData.twilio_phone_number };
            case 'ultramsg':
                return { ultramsg_instance_id: setupData.ultramsg_instance_id, ultramsg_token: setupData.ultramsg_token };
            case 'wati':
                return { wati_api_url: setupData.wati_api_url, wati_api_token: setupData.wati_api_token };
            default:
                return {};
        }
    };

    const orderStatuses = {
        'pending': 'Beklemede',
        'processing': 'Isleniyor',
        'on-hold': 'Bekletiliyor',
        'completed': 'Tamamlandi',
        'cancelled': 'Iptal Edildi',
        'refunded': 'Iade Edildi',
        'failed': 'Basarisiz'
    };

    const renderStepContent = () => {
        switch (steps[currentStep].id) {
            case 'welcome':
                return (
                    <div className="wwa-setup-welcome">
                        <h2>Woo WhatsApp Pro'ya Hosgeldiniz!</h2>
                        <p>WooCommerce magazaniz icin profesyonel WhatsApp bildirim sistemini birlikte kuralim. Bu sihirbaz size adim adim rehberlik edecek.</p>

                        <div className="wwa-feature-list">
                            <div className="wwa-feature-list-item">
                                <span>📱</span>
                                <span>Siparis Bildirimleri</span>
                            </div>
                            <div className="wwa-feature-list-item">
                                <span>🛒</span>
                                <span>Sepet Kurtarma</span>
                            </div>
                            <div className="wwa-feature-list-item">
                                <span>🤖</span>
                                <span>Otomatik Yanitlar</span>
                            </div>
                            <div className="wwa-feature-list-item">
                                <span>📊</span>
                                <span>Detayli Analitik</span>
                            </div>
                        </div>
                    </div>
                );

            case 'api':
                return (
                    <div>
                        <h2>API Saglayicinizi Secin</h2>
                        <p>WhatsApp mesajlari gondermek icin bir API saglayicisi secmeniz gerekiyor. Her saglayicinin kendi hesap gereksinimi vardir.</p>

                        <div className="wwa-provider-options">
                            {[
                                { id: 'whatsapp_business', name: 'WhatsApp Business API', desc: 'Resmi Meta API' },
                                { id: 'twilio', name: 'Twilio', desc: 'Mesaj basi odeme' },
                                { id: 'ultramsg', name: 'Ultramsg', desc: 'Kolay entegrasyon' },
                                { id: 'wati', name: 'WATI', desc: 'WhatsApp Business' }
                            ].map(provider => (
                                <label key={provider.id} className={`wwa-provider-option ${setupData.api_provider === provider.id ? 'selected' : ''}`}>
                                    <input
                                        type="radio"
                                        name="api_provider"
                                        value={provider.id}
                                        checked={setupData.api_provider === provider.id}
                                        onChange={(e) => setSetupData({ ...setupData, api_provider: e.target.value })}
                                    />
                                    <div className="provider-info">
                                        <div className="provider-name">{provider.name}</div>
                                        <div className="provider-desc">{provider.desc}</div>
                                    </div>
                                </label>
                            ))}
                        </div>

                        <div className="wwa-api-fields">
                            {setupData.api_provider === 'whatsapp_business' && (
                                <>
                                    <div className="wwa-wizard-form-group">
                                        <label>Access Token</label>
                                        <input
                                            type="password"
                                            value={setupData.api_token}
                                            onChange={(e) => setSetupData({ ...setupData, api_token: e.target.value })}
                                            placeholder="EAAxxxxxxx..."
                                        />
                                        <div className="input-hint">Meta Developer Console'dan alinir</div>
                                    </div>
                                    <div className="wwa-wizard-form-group">
                                        <label>Phone Number ID</label>
                                        <input
                                            type="text"
                                            value={setupData.phone_number_id}
                                            onChange={(e) => setSetupData({ ...setupData, phone_number_id: e.target.value })}
                                            placeholder="1234567890123456"
                                        />
                                    </div>
                                </>
                            )}

                            {setupData.api_provider === 'twilio' && (
                                <>
                                    <div className="wwa-wizard-form-group">
                                        <label>Account SID</label>
                                        <input
                                            type="text"
                                            value={setupData.twilio_account_sid}
                                            onChange={(e) => setSetupData({ ...setupData, twilio_account_sid: e.target.value })}
                                            placeholder="ACxxxxxxx..."
                                        />
                                    </div>
                                    <div className="wwa-wizard-form-group">
                                        <label>Auth Token</label>
                                        <input
                                            type="password"
                                            value={setupData.twilio_auth_token}
                                            onChange={(e) => setSetupData({ ...setupData, twilio_auth_token: e.target.value })}
                                        />
                                    </div>
                                    <div className="wwa-wizard-form-group">
                                        <label>WhatsApp Phone Number</label>
                                        <input
                                            type="text"
                                            value={setupData.twilio_phone_number}
                                            onChange={(e) => setSetupData({ ...setupData, twilio_phone_number: e.target.value })}
                                            placeholder="+1234567890"
                                        />
                                    </div>
                                </>
                            )}

                            {setupData.api_provider === 'ultramsg' && (
                                <>
                                    <div className="wwa-wizard-form-group">
                                        <label>Instance ID</label>
                                        <input
                                            type="text"
                                            value={setupData.ultramsg_instance_id}
                                            onChange={(e) => setSetupData({ ...setupData, ultramsg_instance_id: e.target.value })}
                                            placeholder="instance123"
                                        />
                                    </div>
                                    <div className="wwa-wizard-form-group">
                                        <label>Token</label>
                                        <input
                                            type="password"
                                            value={setupData.ultramsg_token}
                                            onChange={(e) => setSetupData({ ...setupData, ultramsg_token: e.target.value })}
                                        />
                                    </div>
                                </>
                            )}

                            {setupData.api_provider === 'wati' && (
                                <>
                                    <div className="wwa-wizard-form-group">
                                        <label>API URL</label>
                                        <input
                                            type="url"
                                            value={setupData.wati_api_url}
                                            onChange={(e) => setSetupData({ ...setupData, wati_api_url: e.target.value })}
                                            placeholder="https://live-server-xxxxx.wati.io"
                                        />
                                    </div>
                                    <div className="wwa-wizard-form-group">
                                        <label>API Token</label>
                                        <input
                                            type="password"
                                            value={setupData.wati_api_token}
                                            onChange={(e) => setSetupData({ ...setupData, wati_api_token: e.target.value })}
                                        />
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                );

            case 'phone':
                return (
                    <div>
                        <h2>Yonetici Telefon Numarasi</h2>
                        <p>Bu numara test mesajlari ve yonetici bildirimleri icin kullanilacak.</p>

                        <div className="wwa-wizard-form-group">
                            <label>Telefon Numarasi</label>
                            <div className="wwa-phone-input-group">
                                <select
                                    className="wwa-country-select"
                                    value={countryCode}
                                    onChange={(e) => setCountryCode(e.target.value)}
                                >
                                    {countryCodes.map((c, idx) => (
                                        <option key={idx} value={c.code}>
                                            {c.flag} {c.country} ({c.code})
                                        </option>
                                    ))}
                                </select>
                                <input
                                    type="tel"
                                    className="wwa-phone-number-input"
                                    value={phoneNumber}
                                    onChange={handlePhoneChange}
                                    placeholder="5XX XXX XXXX"
                                    maxLength="15"
                                />
                            </div>
                            <div className="input-hint">
                                {setupData.admin_phone && (
                                    <span>Tam numara: <strong>{setupData.admin_phone}</strong></span>
                                )}
                            </div>
                        </div>

                        <div style={{ marginTop: '25px' }}>
                            <button
                                className="wwa-btn wwa-btn-secondary"
                                onClick={handleTestConnection}
                                disabled={testing || !phoneNumber}
                            >
                                {testing ? 'Test Ediliyor...' : 'Baglanti Test Et'}
                            </button>

                            {testResult && (
                                <div className={`wwa-alert ${testResult.success ? 'success' : 'error'}`} style={{ marginTop: '15px' }}>
                                    {testResult.success ? '✅' : '❌'} {testResult.message}
                                </div>
                            )}
                        </div>
                    </div>
                );

            case 'notifications':
                return (
                    <div>
                        <h2>Bildirim Durumlari</h2>
                        <p>Hangi siparis durumlarinda WhatsApp bildirimi gonderilsin?</p>

                        <div className="wwa-checkbox-group" style={{ marginTop: '20px' }}>
                            {Object.entries(orderStatuses).map(([status, label]) => (
                                <label key={status} className={`wwa-checkbox ${setupData.order_statuses.includes(status) ? 'checked' : ''}`}>
                                    <input
                                        type="checkbox"
                                        checked={setupData.order_statuses.includes(status)}
                                        onChange={(e) => {
                                            const newStatuses = e.target.checked
                                                ? [...setupData.order_statuses, status]
                                                : setupData.order_statuses.filter(s => s !== status);
                                            setSetupData({ ...setupData, order_statuses: newStatuses });
                                        }}
                                    />
                                    <span>{label}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                );

            case 'webhook':
                return (
                    <div>
                        <h2>Webhook Ayarlari</h2>
                        <p>Chatbot ozelligini kullanmak icin API saglayicinizda asagidaki webhook URL'ini tanimlayin.</p>

                        <div className="wwa-wizard-form-group">
                            <label>Webhook URL</label>
                            <div className="wwa-webhook-display">
                                <code>{webhookInfo.url || 'Yuklenıyor...'}</code>
                                <button onClick={() => navigator.clipboard.writeText(webhookInfo.url)}>Kopyala</button>
                            </div>
                        </div>

                        <div className="wwa-wizard-form-group">
                            <label>Verify Token (Meta icin)</label>
                            <div className="wwa-webhook-display">
                                <code>{webhookInfo.verify_token || 'Yuklenıyor...'}</code>
                                <button onClick={() => navigator.clipboard.writeText(webhookInfo.verify_token)}>Kopyala</button>
                            </div>
                        </div>

                        <div className="wwa-alert info" style={{ marginTop: '20px' }}>
                            Bu adimi simdilik atlayabilirsiniz. Webhook ayarlarini daha sonra Ayarlar sayfasindan yapabilirsiniz.
                        </div>
                    </div>
                );

            case 'complete':
                return (
                    <div className="wwa-setup-success">
                        <div className="success-icon">🎉</div>
                        <h2>Tebrikler!</h2>
                        <p>Woo WhatsApp Pro basariyla yapilandirildi. Artik musterilerinize WhatsApp bildirimleri gondermeye hazirsiniz!</p>

                        <div className="wwa-card" style={{ marginTop: '30px', textAlign: 'left' }}>
                            <h4 style={{ marginTop: 0 }}>Yapilandirma Ozeti:</h4>
                            <ul style={{ margin: 0, paddingLeft: '20px' }}>
                                <li><strong>API:</strong> {setupData.api_provider}</li>
                                <li><strong>Yonetici Tel:</strong> {setupData.admin_phone || 'Belirtilmedi'}</li>
                                <li><strong>Bildirim Durumlari:</strong> {setupData.order_statuses.length} adet</li>
                            </ul>
                        </div>
                    </div>
                );

            default:
                return null;
        }
    };

    return (
        <div className="wwa-setup-wizard">
            {/* Sidebar */}
            <div className="wwa-setup-sidebar">
                <h3>Kurulum Adimlari</h3>
                <div className="wwa-setup-steps">
                    {steps.map((step, index) => (
                        <div
                            key={step.id}
                            className={`wwa-setup-step ${index === currentStep ? 'active' : ''} ${index < currentStep ? 'completed' : ''}`}
                        >
                            <div className="wwa-step-icon">{index < currentStep ? '✓' : step.icon}</div>
                            <div className="wwa-step-text">{step.title}</div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Content */}
            <div className="wwa-setup-content">
                {renderStepContent()}

                {/* Buttons */}
                <div className="wwa-wizard-buttons">
                    <div>
                        {currentStep === 0 && (
                            <button className="wwa-btn-wizard skip" onClick={onSkip}>
                                Simdilik Atla
                            </button>
                        )}
                        {currentStep > 0 && (
                            <button className="wwa-btn-wizard secondary" onClick={() => setCurrentStep(currentStep - 1)}>
                                ← Geri
                            </button>
                        )}
                    </div>

                    <div>
                        {currentStep < steps.length - 1 ? (
                            <button className="wwa-btn-wizard primary" onClick={() => setCurrentStep(currentStep + 1)}>
                                Devam Et →
                            </button>
                        ) : (
                            <button className="wwa-btn-wizard primary" onClick={handleComplete} disabled={loading}>
                                {loading ? 'Kaydediliyor...' : 'Kurulumu Tamamla ✓'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SetupWizard;
