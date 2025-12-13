import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../utils/api';

const Logs = ({ showNotification }) => {
    const [logs, setLogs] = useState([]);
    const [stats, setStats] = useState({});
    const [loading, setLoading] = useState(true);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [filters, setFilters] = useState({
        status: '',
        recipient_type: ''
    });
    const [selectedLog, setSelectedLog] = useState(null);

    useEffect(() => {
        fetchLogs();
        fetchStats();
    }, [currentPage, filters]);

    const fetchLogs = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                page: currentPage,
                per_page: 15,
                ...filters
            });

            const response = await apiFetch(`/logs?${params}`);
            setLogs(response.logs || []);
            setTotalPages(response.pages || 1);
        } catch (error) {
            showNotification('error', 'Loglar yüklenemedi: ' + error.message);
        } finally {
            setLoading(false);
        }
    };

    const fetchStats = async () => {
        try {
            const response = await apiFetch('/logs/stats');
            setStats(response);
        } catch (error) {
            console.error('Stats yüklenemedi:', error);
        }
    };

    const handleResend = async (logId) => {
        try {
            const response = await apiFetch(`/logs/${logId}/resend`, {
                method: 'POST'
            });

            if (response.success) {
                showNotification('success', 'Mesaj yeniden gönderildi!');
                fetchLogs();
                fetchStats();
            } else {
                showNotification('error', response.message || 'Gönderim başarısız');
            }
        } catch (error) {
            showNotification('error', 'Hata: ' + error.message);
        }
    };

    const handleDelete = async (logId) => {
        if (!confirm('Bu logu silmek istediğinize emin misiniz?')) {
            return;
        }

        try {
            const response = await apiFetch(`/logs/${logId}`, {
                method: 'DELETE'
            });

            if (response.success) {
                showNotification('success', 'Log silindi');
                fetchLogs();
                fetchStats();
            } else {
                showNotification('error', response.message || 'Silme başarısız');
            }
        } catch (error) {
            showNotification('error', 'Hata: ' + error.message);
        }
    };

    const handleClearAll = async () => {
        if (!confirm('TÜM logları silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')) {
            return;
        }

        try {
            const response = await apiFetch('/logs/clear', {
                method: 'POST'
            });

            if (response.success) {
                showNotification('success', 'Tüm loglar silindi');
                fetchLogs();
                fetchStats();
            } else {
                showNotification('error', response.message || 'Silme başarısız');
            }
        } catch (error) {
            showNotification('error', 'Hata: ' + error.message);
        }
    };

    const getStatusBadge = (status) => {
        const labels = {
            sent: { text: 'Gönderildi', class: 'sent' },
            failed: { text: 'Başarısız', class: 'failed' },
            pending: { text: 'Bekliyor', class: 'pending' }
        };
        const badge = labels[status] || { text: status, class: 'pending' };
        return <span className={`wwa-badge ${badge.class}`}>{badge.text}</span>;
    };

    const getRecipientBadge = (type) => {
        const labels = {
            customer: { text: 'Müşteri', class: 'customer' },
            admin: { text: 'Admin', class: 'admin' }
        };
        const badge = labels[type] || { text: type, class: 'customer' };
        return <span className={`wwa-badge ${badge.class}`}>{badge.text}</span>;
    };

    const truncateMessage = (message, length = 50) => {
        if (message.length <= length) return message;
        return message.substring(0, length) + '...';
    };

    return (
        <div className="wwa-logs">
            {/* Stats Mini */}
            <div className="wwa-stats-grid" style={{ marginBottom: '20px' }}>
                <div className="wwa-stat-card" style={{ padding: '15px' }}>
                    <div className="wwa-stat-number" style={{ fontSize: '28px' }}>{stats.total || 0}</div>
                    <div className="wwa-stat-label">Toplam</div>
                </div>
                <div className="wwa-stat-card sent" style={{ padding: '15px' }}>
                    <div className="wwa-stat-number" style={{ fontSize: '28px' }}>{stats.sent || 0}</div>
                    <div className="wwa-stat-label">Gönderildi</div>
                </div>
                <div className="wwa-stat-card failed" style={{ padding: '15px' }}>
                    <div className="wwa-stat-number" style={{ fontSize: '28px' }}>{stats.failed || 0}</div>
                    <div className="wwa-stat-label">Başarısız</div>
                </div>
                <div className="wwa-stat-card pending" style={{ padding: '15px' }}>
                    <div className="wwa-stat-number" style={{ fontSize: '28px' }}>{stats.pending || 0}</div>
                    <div className="wwa-stat-label">Bekliyor</div>
                </div>
            </div>

            {/* Filters & Actions */}
            <div className="wwa-card">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '15px' }}>
                    <div style={{ display: 'flex', gap: '15px', flexWrap: 'wrap' }}>
                        <select
                            value={filters.status}
                            onChange={(e) => {
                                setFilters(prev => ({ ...prev, status: e.target.value }));
                                setCurrentPage(1);
                            }}
                            style={{ padding: '8px 15px', borderRadius: '6px', border: '1px solid #ddd' }}
                        >
                            <option value="">Tüm Durumlar</option>
                            <option value="sent">Gönderildi</option>
                            <option value="failed">Başarısız</option>
                            <option value="pending">Bekliyor</option>
                        </select>

                        <select
                            value={filters.recipient_type}
                            onChange={(e) => {
                                setFilters(prev => ({ ...prev, recipient_type: e.target.value }));
                                setCurrentPage(1);
                            }}
                            style={{ padding: '8px 15px', borderRadius: '6px', border: '1px solid #ddd' }}
                        >
                            <option value="">Tüm Alıcılar</option>
                            <option value="customer">Müşteri</option>
                            <option value="admin">Admin</option>
                        </select>

                        <button
                            className="wwa-btn wwa-btn-secondary"
                            onClick={() => {
                                setFilters({ status: '', recipient_type: '' });
                                setCurrentPage(1);
                            }}
                        >
                            🔄 Sıfırla
                        </button>
                    </div>

                    <div style={{ display: 'flex', gap: '10px' }}>
                        <button
                            className="wwa-btn wwa-btn-secondary"
                            onClick={() => {
                                fetchLogs();
                                fetchStats();
                            }}
                        >
                            🔃 Yenile
                        </button>
                        <button
                            className="wwa-btn wwa-btn-danger"
                            onClick={handleClearAll}
                        >
                            🗑️ Tümünü Temizle
                        </button>
                    </div>
                </div>
            </div>

            {/* Logs Table */}
            <div className="wwa-card" style={{ marginTop: '20px' }}>
                {loading ? (
                    <div className="wwa-loading">
                        <div className="wwa-spinner"></div>
                    </div>
                ) : logs.length > 0 ? (
                    <>
                        <div className="wwa-table-wrapper">
                            <table className="wwa-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Sipariş</th>
                                        <th>Telefon</th>
                                        <th>Mesaj</th>
                                        <th>Alıcı</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.map(log => (
                                        <tr key={log.id}>
                                            <td>#{log.id}</td>
                                            <td>
                                                {log.order_id ? (
                                                    <a
                                                        href={`${window.wwaSettings?.adminUrl}post.php?post=${log.order_id}&action=edit`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        #{log.order_id}
                                                    </a>
                                                ) : '-'}
                                            </td>
                                            <td>{log.phone}</td>
                                            <td>
                                                <span
                                                    style={{ cursor: 'pointer' }}
                                                    onClick={() => setSelectedLog(log)}
                                                    title="Tıklayarak tam mesajı görün"
                                                >
                                                    {truncateMessage(log.message)}
                                                </span>
                                            </td>
                                            <td>{getRecipientBadge(log.recipient_type)}</td>
                                            <td>{getStatusBadge(log.status)}</td>
                                            <td>
                                                {new Date(log.created_at).toLocaleString('tr-TR', {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit'
                                                })}
                                            </td>
                                            <td>
                                                <div className="wwa-actions">
                                                    <button
                                                        className="wwa-action-btn"
                                                        onClick={() => setSelectedLog(log)}
                                                        title="Detayları Gör"
                                                    >
                                                        👁️
                                                    </button>
                                                    {log.status === 'failed' && (
                                                        <button
                                                            className="wwa-action-btn"
                                                            onClick={() => handleResend(log.id)}
                                                            title="Yeniden Gönder"
                                                        >
                                                            🔄
                                                        </button>
                                                    )}
                                                    <button
                                                        className="wwa-action-btn danger"
                                                        onClick={() => handleDelete(log.id)}
                                                        title="Sil"
                                                    >
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="wwa-pagination">
                                <button
                                    onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                                    disabled={currentPage === 1}
                                >
                                    ← Önceki
                                </button>
                                <span className="wwa-pagination-info">
                                    Sayfa {currentPage} / {totalPages}
                                </span>
                                <button
                                    onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                                    disabled={currentPage === totalPages}
                                >
                                    Sonraki →
                                </button>
                            </div>
                        )}
                    </>
                ) : (
                    <div style={{ textAlign: 'center', padding: '50px', color: '#666' }}>
                        <div style={{ fontSize: '48px', marginBottom: '15px' }}>📭</div>
                        <p>Henüz mesaj logu bulunmuyor</p>
                    </div>
                )}
            </div>

            {/* Log Detail Modal */}
            {selectedLog && (
                <div
                    style={{
                        position: 'fixed',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        background: 'rgba(0,0,0,0.5)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 100000
                    }}
                    onClick={() => setSelectedLog(null)}
                >
                    <div
                        style={{
                            background: '#fff',
                            borderRadius: '12px',
                            padding: '30px',
                            maxWidth: '600px',
                            width: '90%',
                            maxHeight: '80vh',
                            overflow: 'auto'
                        }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                            <h3 style={{ margin: 0 }}>📋 Log Detayı #{selectedLog.id}</h3>
                            <button
                                onClick={() => setSelectedLog(null)}
                                style={{
                                    background: 'none',
                                    border: 'none',
                                    fontSize: '24px',
                                    cursor: 'pointer'
                                }}
                            >
                                ×
                            </button>
                        </div>

                        <div style={{ display: 'grid', gap: '15px' }}>
                            <div>
                                <strong>Sipariş:</strong>{' '}
                                {selectedLog.order_id ? `#${selectedLog.order_id}` : '-'}
                            </div>
                            <div>
                                <strong>Telefon:</strong> {selectedLog.phone}
                            </div>
                            <div>
                                <strong>Alıcı Tipi:</strong> {getRecipientBadge(selectedLog.recipient_type)}
                            </div>
                            <div>
                                <strong>Durum Tipi:</strong> {selectedLog.status_type || '-'}
                            </div>
                            <div>
                                <strong>Gönderim Durumu:</strong> {getStatusBadge(selectedLog.status)}
                            </div>
                            <div>
                                <strong>Oluşturulma:</strong>{' '}
                                {new Date(selectedLog.created_at).toLocaleString('tr-TR')}
                            </div>
                            {selectedLog.sent_at && (
                                <div>
                                    <strong>Gönderilme:</strong>{' '}
                                    {new Date(selectedLog.sent_at).toLocaleString('tr-TR')}
                                </div>
                            )}
                            {selectedLog.message_id && (
                                <div>
                                    <strong>Mesaj ID:</strong> {selectedLog.message_id}
                                </div>
                            )}
                            <div>
                                <strong>Mesaj:</strong>
                                <div
                                    style={{
                                        background: '#f5f5f5',
                                        padding: '15px',
                                        borderRadius: '8px',
                                        marginTop: '10px',
                                        whiteSpace: 'pre-wrap',
                                        fontFamily: 'monospace',
                                        fontSize: '13px'
                                    }}
                                >
                                    {selectedLog.message}
                                </div>
                            </div>
                            {selectedLog.api_response && (
                                <div>
                                    <strong>API Yanıtı:</strong>
                                    <pre
                                        style={{
                                            background: '#f5f5f5',
                                            padding: '15px',
                                            borderRadius: '8px',
                                            marginTop: '10px',
                                            overflow: 'auto',
                                            fontSize: '12px'
                                        }}
                                    >
                                        {typeof selectedLog.api_response === 'string'
                                            ? selectedLog.api_response
                                            : JSON.stringify(JSON.parse(selectedLog.api_response || '{}'), null, 2)
                                        }
                                    </pre>
                                </div>
                            )}
                        </div>

                        <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
                            {selectedLog.status === 'failed' && (
                                <button
                                    className="wwa-btn wwa-btn-primary"
                                    onClick={() => {
                                        handleResend(selectedLog.id);
                                        setSelectedLog(null);
                                    }}
                                >
                                    🔄 Yeniden Gönder
                                </button>
                            )}
                            <button
                                className="wwa-btn wwa-btn-secondary"
                                onClick={() => setSelectedLog(null)}
                            >
                                Kapat
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Logs;
