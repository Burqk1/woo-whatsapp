import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../utils/api';

const Templates = ({ showNotification }) => {
    const [templates, setTemplates] = useState({});
    const [placeholders, setPlaceholders] = useState({});
    const [orderStatuses, setOrderStatuses] = useState({});
    const [selectedTemplate, setSelectedTemplate] = useState('processing');
    const [preview, setPreview] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    // Multi-language states
    const [languages, setLanguages] = useState({ enabled: false, languages: [] });
    const [selectedLanguage, setSelectedLanguage] = useState('');
    const [languageTemplates, setLanguageTemplates] = useState({});

    useEffect(() => {
        fetchData();
    }, []);

    useEffect(() => {
        if (templates[selectedTemplate]) {
            generatePreview(templates[selectedTemplate]);
        }
    }, [selectedTemplate, templates]);

    const fetchData = async () => {
        try {
            const [templatesData, placeholdersData, statusesData, languagesData] = await Promise.all([
                apiFetch('/templates'),
                apiFetch('/placeholders'),
                apiFetch('/order-statuses'),
                apiFetch('/languages')
            ]);
            setTemplates(templatesData);
            setPlaceholders(placeholdersData);
            setOrderStatuses(statusesData);

            // Set up multi-language support
            if (languagesData) {
                setLanguages(languagesData);
                if (languagesData.enabled && languagesData.languages?.length > 0) {
                    const defaultLang = languagesData.languages.find(l => l.default);
                    setSelectedLanguage(defaultLang?.code || languagesData.languages[0].code);
                }
            }
        } catch (error) {
            showNotification('error', 'Şablonlar yüklenemedi: ' + error.message);
        } finally {
            setLoading(false);
        }
    };

    // Load language-specific templates when template or language changes
    useEffect(() => {
        if (languages.enabled && selectedTemplate && selectedLanguage) {
            loadLanguageTemplate();
        }
    }, [selectedTemplate, selectedLanguage, languages.enabled]);

    const loadLanguageTemplate = async () => {
        try {
            const response = await apiFetch(`/templates/${selectedTemplate}/language/${selectedLanguage}`);
            setLanguageTemplates(prev => ({
                ...prev,
                [`${selectedTemplate}_${selectedLanguage}`]: response.template
            }));
        } catch (error) {
            console.error('Language template load error:', error);
        }
    };

    const generatePreview = async (template) => {
        try {
            const response = await apiFetch('/templates/preview', {
                method: 'POST',
                body: JSON.stringify({ template })
            });
            setPreview(response.preview || template);
        } catch (error) {
            setPreview(template);
        }
    };

    const handleTemplateChange = (value) => {
        if (languages.enabled && selectedLanguage) {
            // Multi-language mode: store by language
            setLanguageTemplates(prev => ({
                ...prev,
                [`${selectedTemplate}_${selectedLanguage}`]: value
            }));
        } else {
            // Single language mode
            setTemplates(prev => ({
                ...prev,
                [selectedTemplate]: value
            }));
        }
    };

    // Get current template value (either from language templates or default)
    const getCurrentTemplate = () => {
        if (languages.enabled && selectedLanguage) {
            const langKey = `${selectedTemplate}_${selectedLanguage}`;
            return languageTemplates[langKey] ?? templates[selectedTemplate] ?? '';
        }
        return templates[selectedTemplate] ?? '';
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            if (languages.enabled && selectedLanguage) {
                // Save language-specific template
                const langKey = `${selectedTemplate}_${selectedLanguage}`;
                const template = languageTemplates[langKey] ?? '';

                const response = await apiFetch(`/templates/${selectedTemplate}/language/${selectedLanguage}`, {
                    method: 'POST',
                    body: JSON.stringify({ template })
                });

                if (response.success) {
                    showNotification('success', `Şablon kaydedildi (${selectedLanguage})`);
                } else {
                    showNotification('error', response.message || 'Kaydetme hatası');
                }
            } else {
                // Save all templates (single language mode)
                const response = await apiFetch('/templates', {
                    method: 'POST',
                    body: JSON.stringify(templates)
                });

                if (response.success) {
                    showNotification('success', 'Şablonlar kaydedildi!');
                } else {
                    showNotification('error', response.message || 'Kaydetme hatası');
                }
            }
        } catch (error) {
            showNotification('error', 'Hata: ' + error.message);
        } finally {
            setSaving(false);
        }
    };

    const insertPlaceholder = (placeholder) => {
        const textarea = document.getElementById('template-textarea');
        if (textarea) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const currentValue = getCurrentTemplate();
            const newValue = currentValue.substring(0, start) + placeholder + currentValue.substring(end);
            handleTemplateChange(newValue);

            // Cursor pozisyonunu ayarla
            setTimeout(() => {
                textarea.focus();
                textarea.setSelectionRange(start + placeholder.length, start + placeholder.length);
            }, 10);
        }
    };

    const resetTemplate = () => {
        if (confirm('Bu şablonu varsayılana sıfırlamak istediğinize emin misiniz?')) {
            // Varsayılan şablonları API'den çek
            apiFetch('/templates').then(defaults => {
                setTemplates(prev => ({
                    ...prev,
                    [selectedTemplate]: defaults[selectedTemplate] || ''
                }));
                showNotification('success', 'Şablon varsayılana sıfırlandı');
            });
        }
    };

    if (loading) {
        return (
            <div className="wwa-loading">
                <div className="wwa-spinner"></div>
            </div>
        );
    }

    // Şablon kategorileri
    const templateCategories = {
        'Müşteri Şablonları': ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'shipped'],
        'Admin Şablonları': ['admin_new_order', 'admin_cancelled', 'low_stock'],
        'Diğer': ['custom']
    };

    const getTemplateLabel = (key) => {
        const labels = {
            'pending': '⏳ Beklemede',
            'processing': '📦 İşleniyor',
            'on-hold': '⏸️ Bekletiliyor',
            'completed': '✅ Tamamlandı',
            'cancelled': '❌ İptal Edildi',
            'refunded': '💰 İade Edildi',
            'failed': '❗ Başarısız',
            'shipped': '🚚 Kargoya Verildi',
            'admin_new_order': '🛒 Admin: Yeni Sipariş',
            'admin_cancelled': '❌ Admin: İptal',
            'low_stock': '⚠️ Düşük Stok Uyarısı',
            'custom': '✏️ Özel Mesaj'
        };
        return labels[key] || key;
    };

    return (
        <div className="wwa-templates">
            <div className="wwa-template-editor">
                {/* Sol Panel - Editor */}
                <div>
                    {/* Şablon Seçici */}
                    <div className="wwa-card" style={{ marginBottom: '20px' }}>
                        <h3 className="wwa-card-title">Şablon Seçin</h3>
                        <select
                            className="wwa-template-select"
                            value={selectedTemplate}
                            onChange={(e) => setSelectedTemplate(e.target.value)}
                            style={{ width: '100%', padding: '12px', fontSize: '15px', marginTop: '10px' }}
                        >
                            {Object.entries(templateCategories).map(([category, items]) => (
                                <optgroup key={category} label={category}>
                                    {items.map(key => (
                                        <option key={key} value={key}>
                                            {getTemplateLabel(key)}
                                        </option>
                                    ))}
                                </optgroup>
                            ))}
                        </select>

                        {/* Multi-language Selector */}
                        {languages.enabled && languages.languages?.length > 1 && (
                            <div style={{ marginTop: '15px' }}>
                                <label style={{ display: 'block', marginBottom: '8px', fontWeight: '500' }}>
                                    🌐 Dil Seçin
                                </label>
                                <div className="wwa-language-tabs" style={{
                                    display: 'flex',
                                    gap: '8px',
                                    flexWrap: 'wrap'
                                }}>
                                    {languages.languages.map(lang => (
                                        <button
                                            key={lang.code}
                                            className={`wwa-language-tab ${selectedLanguage === lang.code ? 'active' : ''}`}
                                            onClick={() => setSelectedLanguage(lang.code)}
                                            style={{
                                                padding: '8px 16px',
                                                border: selectedLanguage === lang.code ? '2px solid #25D366' : '1px solid #ddd',
                                                borderRadius: '20px',
                                                background: selectedLanguage === lang.code ? '#e8f5e9' : '#fff',
                                                cursor: 'pointer',
                                                fontSize: '13px',
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: '6px'
                                            }}
                                        >
                                            {lang.flag && <img src={lang.flag} alt="" style={{ width: '16px', height: '12px' }} />}
                                            {lang.native_name || lang.name}
                                            {lang.default && <span style={{ fontSize: '10px', opacity: 0.7 }}>(varsayılan)</span>}
                                        </button>
                                    ))}
                                </div>
                                <p style={{ fontSize: '12px', color: '#666', marginTop: '8px' }}>
                                    {languages.plugin === 'wpml' && '🔌 WPML ile entegre'}
                                    {languages.plugin === 'polylang' && '🔌 Polylang ile entegre'}
                                    {languages.plugin === 'translatepress' && '🔌 TranslatePress ile entegre'}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Editor */}
                    <div className="wwa-card">
                        <div className="wwa-card-header">
                            <h3 className="wwa-card-title">{getTemplateLabel(selectedTemplate)}</h3>
                            <button
                                className="wwa-btn wwa-btn-secondary"
                                onClick={resetTemplate}
                                style={{ fontSize: '12px', padding: '5px 10px' }}
                            >
                                🔄 Sıfırla
                            </button>
                        </div>

                        <textarea
                            id="template-textarea"
                            value={getCurrentTemplate()}
                            onChange={(e) => handleTemplateChange(e.target.value)}
                            placeholder="Mesaj şablonunu buraya yazın..."
                            style={{
                                width: '100%',
                                minHeight: '250px',
                                padding: '15px',
                                fontFamily: 'monospace',
                                fontSize: '14px',
                                lineHeight: '1.6',
                                border: '1px solid #ddd',
                                borderRadius: '6px',
                                resize: 'vertical'
                            }}
                        />

                        <div className="wwa-btn-group" style={{ marginTop: '15px' }}>
                            <button
                                className="wwa-btn wwa-btn-primary"
                                onClick={handleSave}
                                disabled={saving}
                            >
                                {saving ? '💾 Kaydediliyor...' : '💾 Şablonları Kaydet'}
                            </button>
                            <button
                                className="wwa-btn wwa-btn-secondary"
                                onClick={() => generatePreview(getCurrentTemplate())}
                            >
                                👁️ Önizleme
                            </button>
                        </div>
                    </div>

                    {/* Preview */}
                    <div className="wwa-card" style={{ marginTop: '20px' }}>
                        <h3 className="wwa-card-title">📱 WhatsApp Önizleme</h3>
                        <div className="wwa-preview-panel" style={{ marginTop: '15px' }}>
                            <div className="wwa-preview-bubble">
                                {preview || 'Önizleme için şablon yazın...'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Sağ Panel - Placeholders */}
                <div>
                    <div className="wwa-card">
                        <h3 className="wwa-card-title">📋 Kullanılabilir Değişkenler</h3>
                        <p style={{ fontSize: '13px', color: '#666', marginBottom: '15px' }}>
                            Değişkene tıklayarak şablona ekleyebilirsiniz
                        </p>

                        <div className="wwa-placeholder-panel">
                            {Object.entries(placeholders).map(([group, items]) => (
                                <div key={group} className="wwa-placeholder-group">
                                    <div className="wwa-placeholder-group-title">
                                        {group === 'müşteri' && '👤 Müşteri'}
                                        {group === 'sipariş' && '📦 Sipariş'}
                                        {group === 'tutar' && '💰 Tutar'}
                                        {group === 'ürün' && '🛍️ Ürün'}
                                        {group === 'kargo' && '🚚 Kargo'}
                                        {group === 'mağaza' && '🏪 Mağaza'}
                                    </div>
                                    {Object.entries(items).map(([placeholder, description]) => (
                                        <div
                                            key={placeholder}
                                            className="wwa-placeholder-item"
                                            onClick={() => insertPlaceholder(placeholder)}
                                        >
                                            <span className="wwa-placeholder-code">{placeholder}</span>
                                            <span className="wwa-placeholder-desc">{description}</span>
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Tips */}
                    <div className="wwa-card" style={{ marginTop: '20px' }}>
                        <h3 className="wwa-card-title">💡 İpuçları</h3>
                        <ul style={{ fontSize: '13px', color: '#666', paddingLeft: '20px', lineHeight: '1.8' }}>
                            <li>Değişkenler süslü parantez içinde yazılır: <code>{'{müşteri_adı}'}</code></li>
                            <li>Yeni satır için Enter tuşunu kullanın</li>
                            <li>Emoji kullanabilirsiniz 🎉</li>
                            <li>Mesajlar WhatsApp karakter limitine dikkat edin (4096 karakter)</li>
                            <li>Her sipariş durumu için farklı mesaj ayarlayabilirsiniz</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Templates;
