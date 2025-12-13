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

const Chatbot = () => {
    const [rules, setRules] = useState([]);
    const [conversations, setConversations] = useState([]);
    const [settings, setSettings] = useState({});
    const [stats, setStats] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);
    const [activeTab, setActiveTab] = useState('rules');
    const [showRuleModal, setShowRuleModal] = useState(false);
    const [editingRule, setEditingRule] = useState(null);
    const [selectedConversation, setSelectedConversation] = useState(null);
    const [conversationHistory, setConversationHistory] = useState([]);

    const [ruleForm, setRuleForm] = useState({
        name: '',
        keywords: '',
        match_type: 'contains',
        response: '',
        response_type: 'text',
        actions: [],
        is_active: true,
        priority: 10
    });

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        setLoading(true);
        try {
            const [rulesRes, conversationsRes, settingsRes, statsRes] = await Promise.all([
                apiFetch({ path: '/wwa/v1/chatbot/rules' }),
                apiFetch({ path: '/wwa/v1/chatbot/conversations' }),
                apiFetch({ path: '/wwa/v1/chatbot/settings' }),
                apiFetch({ path: '/wwa/v1/chatbot/stats' })
            ]);
            setRules(rulesRes || []);
            setConversations(conversationsRes || []);
            setSettings(settingsRes || {});
            setStats(statsRes || {});
        } catch (error) {
            setMessage({ type: 'error', text: 'Veriler yüklenirken hata oluştu' });
        }
        setLoading(false);
    };

    const saveSettings = async () => {
        setSaving(true);
        try {
            await apiFetch({
                path: '/wwa/v1/chatbot/settings',
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

    const openNewRule = () => {
        setRuleForm({
            name: '',
            keywords: '',
            match_type: 'contains',
            response: '',
            response_type: 'text',
            actions: [],
            is_active: true,
            priority: 10
        });
        setEditingRule(null);
        setShowRuleModal(true);
    };

    const openEditRule = (rule) => {
        setRuleForm({
            name: rule.name || '',
            keywords: Array.isArray(rule.keywords) ? rule.keywords.join(', ') : rule.keywords,
            match_type: rule.match_type || 'contains',
            response: rule.response || '',
            response_type: rule.response_type || 'text',
            actions: rule.actions || [],
            is_active: rule.status === 'active' || rule.is_active === '1' || rule.is_active === true,
            priority: rule.priority || 10
        });
        setEditingRule(rule);
        setShowRuleModal(true);
    };

    const saveRule = async () => {
        if (!ruleForm.keywords || !ruleForm.response) {
            setMessage({ type: 'error', text: 'Anahtar kelime ve yanıt gerekli' });
            return;
        }

        setSaving(true);
        try {
            const data = {
                ...ruleForm,
                keywords: ruleForm.keywords.split(',').map(k => k.trim()).filter(k => k)
            };

            if (editingRule) {
                await apiFetch({
                    path: `/wwa/v1/chatbot/rules/${editingRule.id}`,
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                setMessage({ type: 'success', text: 'Kural güncellendi' });
            } else {
                await apiFetch({
                    path: '/wwa/v1/chatbot/rules',
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                setMessage({ type: 'success', text: 'Kural oluşturuldu' });
            }
            setShowRuleModal(false);
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: error.message || 'Hata oluştu' });
        }
        setSaving(false);
    };

    const deleteRule = async (ruleId) => {
        if (!confirm('Bu kuralı silmek istediğinize emin misiniz?')) return;

        try {
            await apiFetch({
                path: `/wwa/v1/chatbot/rules/${ruleId}`,
                method: 'DELETE'
            });
            setMessage({ type: 'success', text: 'Kural silindi' });
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: 'Kural silinemedi' });
        }
    };

    const toggleRule = async (ruleId) => {
        try {
            await apiFetch({
                path: `/wwa/v1/chatbot/rules/${ruleId}/toggle`,
                method: 'POST'
            });
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: 'Durum güncellenemedi' });
        }
    };

    const viewConversation = async (phone) => {
        setSelectedConversation(phone);
        try {
            const history = await apiFetch({
                path: `/wwa/v1/chatbot/conversations/${encodeURIComponent(phone)}`
            });
            setConversationHistory(history || []);
        } catch (error) {
            setMessage({ type: 'error', text: 'Konuşma geçmişi yüklenemedi' });
        }
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('tr-TR');
    };

    const getMatchTypeLabel = (type) => {
        const labels = {
            'exact': 'Tam Eşleşme',
            'contains': 'İçerir',
            'starts_with': 'İle Başlar',
            'ends_with': 'İle Biter',
            'regex': 'Regex'
        };
        return labels[type] || type;
    };

    const getResponseTypeLabel = (type) => {
        const labels = {
            'text': 'Metin',
            'order_status': 'Sipariş Durumu',
            'tracking_info': 'Kargo Takip',
            'order_list': 'Sipariş Listesi'
        };
        return labels[type] || type;
    };

    if (loading) {
        return (
            <div className="wwa-loading">
                <Spinner />
                <p>Yükleniyor...</p>
            </div>
        );
    }

    return (
        <div className="wwa-chatbot">
            {message && (
                <Notice
                    status={message.type}
                    onRemove={() => setMessage(null)}
                    isDismissible
                >
                    {message.text}
                </Notice>
            )}

            <h2>WhatsApp Chatbot</h2>

            {/* İstatistikler */}
            <div className="wwa-stats-grid">
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="format-chat" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.total_messages || 0}</span>
                                <span className="wwa-stat-label">Toplam Mesaj</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="admin-users" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.unique_users || 0}</span>
                                <span className="wwa-stat-label">Benzersiz Kullanıcı</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="yes-alt" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.auto_replies || 0}</span>
                                <span className="wwa-stat-label">Otomatik Yanıt</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
                <Card className="wwa-stat-card">
                    <CardBody>
                        <div className="wwa-stat">
                            <Dashicon icon="chart-area" />
                            <div className="wwa-stat-content">
                                <span className="wwa-stat-value">{stats.response_rate || 0}%</span>
                                <span className="wwa-stat-label">Yanıt Oranı</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
            </div>

            <TabPanel
                className="wwa-tab-panel"
                activeClass="is-active"
                tabs={[
                    { name: 'rules', title: 'Kurallar', className: 'wwa-tab' },
                    { name: 'conversations', title: 'Konuşmalar', className: 'wwa-tab' },
                    { name: 'settings', title: 'Ayarlar', className: 'wwa-tab' }
                ]}
                onSelect={(tabName) => setActiveTab(tabName)}
            >
                {(tab) => (
                    <>
                        {/* Kurallar Tab */}
                        {tab.name === 'rules' && (
                            <div className="wwa-rules-tab">
                                <div className="wwa-tab-header">
                                    <Button isPrimary onClick={openNewRule}>
                                        <Dashicon icon="plus-alt" /> Yeni Kural Ekle
                                    </Button>
                                </div>

                                {rules.length === 0 ? (
                                    <Card>
                                        <CardBody>
                                            <div className="wwa-empty-state">
                                                <Dashicon icon="admin-comments" size={48} />
                                                <h3>Henüz kural yok</h3>
                                                <p>Müşteri mesajlarına otomatik yanıt vermek için kurallar oluşturun.</p>
                                                <Button isPrimary onClick={openNewRule}>
                                                    İlk Kuralınızı Oluşturun
                                                </Button>
                                            </div>
                                        </CardBody>
                                    </Card>
                                ) : (
                                    <div className="wwa-rules-list">
                                        {rules.map(rule => (
                                            <Card key={rule.id} className={`wwa-rule-card ${rule.status === 'active' ? 'active' : 'inactive'}`}>
                                                <CardBody>
                                                    <div className="wwa-rule-content">
                                                        <div className="wwa-rule-info">
                                                            <div className="wwa-rule-header">
                                                                <span className={`status-dot ${rule.status === 'active' ? 'active' : 'inactive'}`}></span>
                                                                <h4>{rule.name || 'İsimsiz Kural'}</h4>
                                                                <span className="priority-badge">Öncelik: {rule.priority}</span>
                                                            </div>
                                                            <div className="wwa-rule-meta">
                                                                <span className="keywords">
                                                                    <Dashicon icon="tag" />
                                                                    {Array.isArray(rule.keywords) ? rule.keywords.join(', ') : rule.keywords}
                                                                </span>
                                                                <span className="match-type">
                                                                    <Dashicon icon="search" />
                                                                    {getMatchTypeLabel(rule.match_type)}
                                                                </span>
                                                                <span className="response-type">
                                                                    <Dashicon icon="format-aside" />
                                                                    {getResponseTypeLabel(rule.response_type)}
                                                                </span>
                                                            </div>
                                                            <div className="wwa-rule-response">
                                                                <p>{rule.response?.substring(0, 100)}{rule.response?.length > 100 ? '...' : ''}</p>
                                                            </div>
                                                        </div>
                                                        <div className="wwa-rule-actions">
                                                            <Button
                                                                isSmall
                                                                onClick={() => toggleRule(rule.id)}
                                                            >
                                                                <Dashicon icon={rule.status === 'active' ? 'controls-pause' : 'controls-play'} />
                                                            </Button>
                                                            <Button
                                                                isSmall
                                                                onClick={() => openEditRule(rule)}
                                                            >
                                                                <Dashicon icon="edit" />
                                                            </Button>
                                                            <Button
                                                                isSmall
                                                                isDestructive
                                                                onClick={() => deleteRule(rule.id)}
                                                            >
                                                                <Dashicon icon="trash" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </CardBody>
                                            </Card>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Konuşmalar Tab */}
                        {tab.name === 'conversations' && (
                            <div className="wwa-conversations-tab">
                                {conversations.length === 0 ? (
                                    <Card>
                                        <CardBody>
                                            <div className="wwa-empty-state">
                                                <Dashicon icon="format-chat" size={48} />
                                                <h3>Henüz konuşma yok</h3>
                                                <p>Müşteriler mesaj gönderdiğinde burada görünecekler.</p>
                                            </div>
                                        </CardBody>
                                    </Card>
                                ) : (
                                    <div className="wwa-conversations-layout">
                                        <div className="wwa-conversation-list">
                                            {conversations.map(conv => (
                                                <Card
                                                    key={conv.phone}
                                                    className={`wwa-conversation-card ${selectedConversation === conv.phone ? 'selected' : ''}`}
                                                    onClick={() => viewConversation(conv.phone)}
                                                >
                                                    <CardBody>
                                                        <div className="wwa-conv-info">
                                                            <Dashicon icon="smartphone" />
                                                            <div>
                                                                <strong>{conv.phone}</strong>
                                                                <span className="last-message">{conv.last_message?.substring(0, 50)}...</span>
                                                                <span className="message-time">{formatDate(conv.last_activity)}</span>
                                                            </div>
                                                            <span className="message-count">{conv.message_count}</span>
                                                        </div>
                                                    </CardBody>
                                                </Card>
                                            ))}
                                        </div>

                                        <div className="wwa-conversation-history">
                                            {selectedConversation ? (
                                                <>
                                                    <div className="wwa-chat-header">
                                                        <h4>{selectedConversation}</h4>
                                                    </div>
                                                    <div className="wwa-chat-messages">
                                                        {conversationHistory.map((msg, idx) => (
                                                            <div key={idx} className={`wwa-chat-message ${msg.direction}`}>
                                                                <div className="message-bubble">
                                                                    <p>{msg.message}</p>
                                                                    <span className="message-time">{formatDate(msg.created_at)}</span>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </>
                                            ) : (
                                                <div className="wwa-no-conversation">
                                                    <Dashicon icon="admin-comments" />
                                                    <p>Görüntülemek için bir konuşma seçin</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Ayarlar Tab */}
                        {tab.name === 'settings' && (
                            <Card className="wwa-settings-card">
                                <CardBody>
                                    <ToggleControl
                                        label="Chatbot Aktif"
                                        help="WhatsApp mesajlarına otomatik yanıt ver"
                                        checked={settings.enabled === 'yes'}
                                        onChange={(value) => setSettings({ ...settings, enabled: value ? 'yes' : 'no' })}
                                    />

                                    <TextareaControl
                                        label="Hoşgeldin Mesajı"
                                        value={settings.welcome_message || ''}
                                        onChange={(value) => setSettings({ ...settings, welcome_message: value })}
                                        placeholder="İlk kez mesaj atan müşterilere gönderilecek mesaj (boş bırakılırsa gönderilmez)"
                                        rows={3}
                                    />

                                    <TextareaControl
                                        label="Varsayılan Yanıt"
                                        value={settings.fallback_message || ''}
                                        onChange={(value) => setSettings({ ...settings, fallback_message: value })}
                                        placeholder="Hiçbir kural eşleşmediğinde gönderilecek mesaj"
                                        rows={3}
                                    />

                                    <TextControl
                                        label="Yanıt Gecikmesi (saniye)"
                                        type="number"
                                        value={settings.delay_seconds || 2}
                                        onChange={(value) => setSettings({ ...settings, delay_seconds: parseInt(value) })}
                                        help="Daha doğal görünmesi için yanıt göndermeden önce beklenecek süre"
                                    />

                                    <hr />

                                    <h3>Çalışma Saatleri</h3>
                                    <ToggleControl
                                        label="Çalışma Saatlerini Etkinleştir"
                                        checked={settings.working_hours_enabled === 'yes'}
                                        onChange={(value) => setSettings({ ...settings, working_hours_enabled: value ? 'yes' : 'no' })}
                                    />

                                    {settings.working_hours_enabled === 'yes' && (
                                        <>
                                            <div className="wwa-working-hours">
                                                <TextControl
                                                    label="Başlangıç"
                                                    type="time"
                                                    value={settings.working_hours_start || '09:00'}
                                                    onChange={(value) => setSettings({ ...settings, working_hours_start: value })}
                                                />
                                                <TextControl
                                                    label="Bitiş"
                                                    type="time"
                                                    value={settings.working_hours_end || '18:00'}
                                                    onChange={(value) => setSettings({ ...settings, working_hours_end: value })}
                                                />
                                            </div>
                                            <TextareaControl
                                                label="Çalışma Saatleri Dışı Mesajı"
                                                value={settings.outside_hours_message || ''}
                                                onChange={(value) => setSettings({ ...settings, outside_hours_message: value })}
                                                placeholder="Çalışma saatleri dışında gönderilecek mesaj"
                                                rows={3}
                                            />
                                        </>
                                    )}

                                    <hr />

                                    <ToggleControl
                                        label="Admin Bildirimi"
                                        help="Chatbot yanıt veremediğinde admin'e bildirim gönder"
                                        checked={settings.notify_admin === 'yes'}
                                        onChange={(value) => setSettings({ ...settings, notify_admin: value ? 'yes' : 'no' })}
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

            {/* Kural Düzenleme Modal */}
            {showRuleModal && (
                <Modal
                    title={editingRule ? 'Kuralı Düzenle' : 'Yeni Kural Oluştur'}
                    onRequestClose={() => setShowRuleModal(false)}
                    className="wwa-rule-modal"
                >
                    <TextControl
                        label="Kural Adı"
                        value={ruleForm.name}
                        onChange={(value) => setRuleForm({ ...ruleForm, name: value })}
                        placeholder="Örn: Sipariş Durumu Sorgulama"
                    />

                    <TextControl
                        label="Anahtar Kelimeler"
                        value={ruleForm.keywords}
                        onChange={(value) => setRuleForm({ ...ruleForm, keywords: value })}
                        placeholder="sipariş, durum, nerede (virgülle ayırın)"
                        help="Birden fazla kelime için virgül kullanın"
                    />

                    <SelectControl
                        label="Eşleşme Tipi"
                        value={ruleForm.match_type}
                        options={[
                            { label: 'İçerir', value: 'contains' },
                            { label: 'Tam Eşleşme', value: 'exact' },
                            { label: 'İle Başlar', value: 'starts_with' },
                            { label: 'İle Biter', value: 'ends_with' },
                            { label: 'Regex', value: 'regex' }
                        ]}
                        onChange={(value) => setRuleForm({ ...ruleForm, match_type: value })}
                    />

                    <SelectControl
                        label="Yanıt Tipi"
                        value={ruleForm.response_type}
                        options={[
                            { label: 'Metin', value: 'text' },
                            { label: 'Sipariş Durumu (Otomatik)', value: 'order_status' },
                            { label: 'Kargo Takip (Otomatik)', value: 'tracking_info' },
                            { label: 'Sipariş Listesi (Otomatik)', value: 'order_list' }
                        ]}
                        onChange={(value) => setRuleForm({ ...ruleForm, response_type: value })}
                        help="Otomatik seçenekler müşterinin son siparişine göre dinamik içerik oluşturur"
                    />

                    <TextareaControl
                        label="Yanıt Mesajı"
                        value={ruleForm.response}
                        onChange={(value) => setRuleForm({ ...ruleForm, response: value })}
                        placeholder={ruleForm.response_type === 'text'
                            ? "Yanıt mesajınızı yazın..."
                            : "Dinamik içerikten önce/sonra gösterilecek metin (opsiyonel)"}
                        rows={4}
                    />

                    <TextControl
                        label="Öncelik"
                        type="number"
                        value={ruleForm.priority}
                        onChange={(value) => setRuleForm({ ...ruleForm, priority: parseInt(value) })}
                        help="Düşük sayı = yüksek öncelik (birden fazla kural eşleşirse önceliğe göre sıralanır)"
                    />

                    <ToggleControl
                        label="Aktif"
                        checked={ruleForm.is_active}
                        onChange={(value) => setRuleForm({ ...ruleForm, is_active: value })}
                    />

                    <div className="wwa-modal-footer">
                        <Button isSecondary onClick={() => setShowRuleModal(false)}>
                            İptal
                        </Button>
                        <Button isPrimary onClick={saveRule} isBusy={saving}>
                            {saving ? 'Kaydediliyor...' : (editingRule ? 'Güncelle' : 'Oluştur')}
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default Chatbot;
