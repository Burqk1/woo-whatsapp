import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../utils/api';

const Dashboard = ({ showNotification }) => {
    const [stats, setStats] = useState(null);
    const [recentLogs, setRecentLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [testPhone, setTestPhone] = useState('');
    const [testMessage, setTestMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [apiStatus, setApiStatus] = useState(null);

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        try {
            const [statsData, logsData, settingsData] = await Promise.all([
                apiFetch('/logs/stats'),
                apiFetch('/logs?per_page=5'),
                apiFetch('/settings')
            ]);
            setStats(statsData);
            setRecentLogs(logsData.logs || []);

            // API yapılandırma durumunu kontrol et
            const provider = settingsData.api_provider || 'whatsapp_business';
            let configured = false;

            switch (provider) {
                case 'whatsapp_business':
                    configured = settingsData.api_token_set && settingsData.phone_number_id;
                    break;
                case 'twilio':
                    configured = settingsData.twilio_account_sid && settingsData.twilio_auth_token_set && settingsData.twilio_phone_number;
                    break;
                case 'ultramsg':
                    configured = settingsData.ultramsg_instance_id && settingsData.ultramsg_token_set;
                    break;
                case 'wati':
                    configured = settingsData.wati_api_url && settingsData.wati_api_token_set;
                    break;
            }

            setApiStatus({
                isConfigured: configured,
                provider: provider,
                debugMode: settingsData.debug_mode === 'yes'
            });
        } catch (error) {
            console.error('Dashboard fetch error:', error);
            showNotification('error', 'Veriler yüklenirken hata oluştu');
        } finally {
            setLoading(false);
        }
    };

    const handleTestMessage = async () => {
        if (!testPhone) {
            showNotification('error', 'Lütfen telefon numarası girin');
            return;
        }

        setSending(true);
        try {
            const response = await apiFetch('/test', {
                method: 'POST',
                body: JSON.stringify({
                    phone: testPhone,
                    message: testMessage || 'Bu bir test mesajıdır. Woo WhatsApp Bildirimcisi başarıyla yapılandırıldı!'
                })
            });

            if (response.success) {
                showNotification('success', response.simulated
                    ? 'Test mesajı simüle edildi (Debug modu aktif)'
                    : 'Test mesajı gönderildi!'
                );
                setTestPhone('');
                setTestMessage('');
                fetchData(); // Stats'ı yenile
            } else {
                showNotification('error', response.message || 'Mesaj gönderilemedi');
            }
        } catch (error) {
            showNotification('error', 'Hata: ' + error.message);
        } finally {
            setSending(false);
        }
    };

    const handleTestConnection = async () => {
        setSending(true);
        try {
            const response = await apiFetch('/test-connection', { method: 'POST' });
            if (response.success) {
                showNotification('success', response.simulated
                    ? 'Bağlantı simüle edildi (Debug modu aktif)'
                    : 'API bağlantısı başarılı!'
                );
            } else {
                showNotification('error', response.message || 'Bağlantı başarısız');
            }
        } catch (error) {
            showNotification('error', 'Bağlantı hatası: ' + error.message);
        } finally {
            setSending(false);
        }
    };

    const handleRepairTables = async () => {
        setSending(true);
        try {
            const response = await apiFetch('/repair-tables', { method: 'POST' });
            if (response.success) {
                showNotification('success', response.message);
                // Sayfayı yenile
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification('error', response.message || 'Tablolar oluşturulamadı');
            }
        } catch (error) {
            showNotification('error', 'Hata: ' + error.message);
        } finally {
            setSending(false);
        }
    };

    if (loading) {
        return (
            <div className="wwa-loading">
                <div className="wwa-spinner"></div>
            </div>
        );
    }

    // API durumunu apiStatus state'inden al (taze veri)
    const isConfigured = apiStatus?.isConfigured ?? window.wwaSettings?.isConfigured;
    const debugMode = apiStatus?.debugMode ?? window.wwaSettings?.debugMode;

    return (
        <div className="wwa-dashboard">
            {/* Status Alert */}
            {!isConfigured && (
                <div className="wwa-alert warning">
                    <strong>Uyarı:</strong> WhatsApp API henüz yapılandırılmadı.
                    Lütfen <a href="#/settings">Ayarlar</a> sayfasından API bilgilerinizi girin.
                </div>
            )}

            {debugMode && (
                <div className="wwa-alert info">
                    <strong>Debug Modu Aktif:</strong> Mesajlar gerçekte gönderilmiyor, simüle ediliyor.
                </div>
            )}

            {/* Stats Grid */}
            <div className="wwa-stats-grid">
                <div className="wwa-stat-card">
                    <div className="wwa-stat-number">{stats?.total || 0}</div>
                    <div className="wwa-stat-label">Toplam Mesaj</div>
                </div>
                <div className="wwa-stat-card sent">
                    <div className="wwa-stat-number">{stats?.sent || 0}</div>
                    <div className="wwa-stat-label">Gönderildi</div>
                </div>
                <div className="wwa-stat-card failed">
                    <div className="wwa-stat-number">{stats?.failed || 0}</div>
                    <div className="wwa-stat-label">Başarısız</div>
                </div>
                <div className="wwa-stat-card pending">
                    <div className="wwa-stat-number">{stats?.pending || 0}</div>
                    <div className="wwa-stat-label">Bekliyor</div>
                </div>
            </div>

            {/* Time-based Stats */}
            <div className="wwa-card">
                <h3 className="wwa-card-title">Zaman Bazlı İstatistikler</h3>
                <div className="wwa-stats-grid" style={{ marginTop: '15px' }}>
                    <div className="wwa-stat-mini">
                        <strong>{stats?.today || 0}</strong>
                        <span>Bugün</span>
                    </div>
                    <div className="wwa-stat-mini">
                        <strong>{stats?.this_week || 0}</strong>
                        <span>Bu Hafta</span>
                    </div>
                    <div className="wwa-stat-mini">
                        <strong>{stats?.this_month || 0}</strong>
                        <span>Bu Ay</span>
                    </div>
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                {/* Test Message */}
                <div className="wwa-card">
                    <h3 className="wwa-card-title">Test Mesajı Gönder</h3>

                    <div className="wwa-form-group">
                        <label>Telefon Numarası</label>
                        <input
                            type="text"
                            value={testPhone}
                            onChange={(e) => setTestPhone(e.target.value)}
                            placeholder="+90 5XX XXX XXXX"
                        />
                        <p className="wwa-form-help">Ülke kodu ile birlikte girin</p>
                    </div>

                    <div className="wwa-form-group">
                        <label>Mesaj (Opsiyonel)</label>
                        <textarea
                            value={testMessage}
                            onChange={(e) => setTestMessage(e.target.value)}
                            placeholder="Boş bırakırsanız varsayılan test mesajı gönderilir..."
                            rows={3}
                        />
                    </div>

                    <div className="wwa-btn-group">
                        <button
                            className="wwa-btn wwa-btn-primary"
                            onClick={handleTestMessage}
                            disabled={sending || !testPhone}
                        >
                            {sending ? 'Gönderiliyor...' : '📤 Test Mesajı Gönder'}
                        </button>
                        <button
                            className="wwa-btn wwa-btn-secondary"
                            onClick={handleTestConnection}
                            disabled={sending}
                        >
                            🔗 Bağlantıyı Test Et
                        </button>
                    </div>
                </div>

                {/* Recent Activity */}
                <div className="wwa-card">
                    <h3 className="wwa-card-title">Son Aktiviteler</h3>

                    {recentLogs.length > 0 ? (
                        <div className="wwa-table-wrapper">
                            <table className="wwa-table">
                                <thead>
                                    <tr>
                                        <th>Telefon</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentLogs.map((log) => (
                                        <tr key={log.id}>
                                            <td>{log.phone}</td>
                                            <td>
                                                <span className={`wwa-badge ${log.status}`}>
                                                    {log.status === 'sent' && 'Gönderildi'}
                                                    {log.status === 'failed' && 'Başarısız'}
                                                    {log.status === 'pending' && 'Bekliyor'}
                                                </span>
                                            </td>
                                            <td>{new Date(log.created_at).toLocaleString('tr-TR')}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p style={{ color: '#666', textAlign: 'center', padding: '30px' }}>
                            Henüz mesaj gönderilmedi
                        </p>
                    )}

                    {recentLogs.length > 0 && (
                        <div style={{ marginTop: '15px', textAlign: 'center' }}>
                            <a href="#/logs" className="wwa-btn wwa-btn-secondary">
                                Tüm Logları Görüntüle →
                            </a>
                        </div>
                    )}
                </div>
            </div>

            {/* Quick Info */}
            <div className="wwa-card" style={{ marginTop: '20px' }}>
                <h3 className="wwa-card-title">Hızlı Bilgi</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px', marginTop: '15px' }}>
                    <div>
                        <strong>API Durumu:</strong>
                        <span style={{ marginLeft: '10px' }}>
                            {isConfigured ? (
                                <span style={{ color: '#28a745' }}>✓ Yapılandırıldı</span>
                            ) : (
                                <span style={{ color: '#dc3545' }}>✗ Yapılandırılmadı</span>
                            )}
                        </span>
                    </div>
                    <div>
                        <strong>Debug Modu:</strong>
                        <span style={{ marginLeft: '10px' }}>
                            {debugMode ? 'Aktif' : 'Kapalı'}
                        </span>
                    </div>
                    <div>
                        <strong>Versiyon:</strong>
                        <span style={{ marginLeft: '10px' }}>{window.wwaSettings?.version || '2.0.0'}</span>
                    </div>
                </div>
            </div>

            {/* Database Repair */}
            <div className="wwa-card" style={{ marginTop: '20px', background: '#fff3cd', borderColor: '#ffc107' }}>
                <h3 className="wwa-card-title">Veritabanı Onarımı</h3>
                <p style={{ color: '#856404', marginBottom: '15px' }}>
                    Pro özelliklerde (Flow Builder, Sepet Kurtarma, Stok Bildirimi) hata alıyorsanız,
                    veritabanı tablolarını oluşturmak için aşağıdaki butona tıklayın.
                </p>
                <button
                    className="wwa-btn wwa-btn-primary"
                    onClick={handleRepairTables}
                    disabled={sending}
                    style={{ background: '#856404', borderColor: '#856404' }}
                >
                    {sending ? 'Onarılıyor...' : '🔧 Tabloları Onar / Oluştur'}
                </button>
            </div>
        </div>
    );
};

export default Dashboard;
