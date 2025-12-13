import { useState, useEffect } from '@wordpress/element';
import {
    Button,
    Card,
    CardBody,
    CardHeader,
    SelectControl,
    Spinner,
    Notice,
    Dashicon
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Analytics = () => {
    const [overview, setOverview] = useState({});
    const [messageAnalytics, setMessageAnalytics] = useState({});
    const [conversionAnalytics, setConversionAnalytics] = useState({});
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [period, setPeriod] = useState('30days');
    const [exporting, setExporting] = useState(false);
    const [exportType, setExportType] = useState(null);

    useEffect(() => {
        loadData();
    }, [period]);

    // CSV Export
    const exportCSV = async (dataType = 'messages') => {
        setExporting(true);
        setExportType('csv');
        try {
            const response = await apiFetch({
                path: `/wwa/v1/reports/export/csv?type=${dataType}&period=${period}`,
                parse: false
            });
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `wwa-${dataType}-report-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            setMessage({ type: 'success', text: 'CSV raporu başarıyla indirildi!' });
        } catch (error) {
            setMessage({ type: 'error', text: 'CSV dışa aktarma hatası: ' + error.message });
        }
        setExporting(false);
        setExportType(null);
    };

    const exportPDF = async () => {
        setExporting(true);
        setExportType('pdf');
        try {
            const response = await apiFetch({
                path: `/wwa/v1/reports/export/pdf?period=${period}`,
                parse: false
            });
            const html = await response.text();

            const printWindow = window.open('', '_blank');
            printWindow.document.write(html);
            printWindow.document.close();

            setTimeout(() => {
                printWindow.print();
            }, 500);

            setMessage({ type: 'success', text: 'PDF raporu hazırlandı. Yazdırma diyaloğundan PDF olarak kaydedin.' });
        } catch (error) {
            setMessage({ type: 'error', text: 'PDF dışa aktarma hatası: ' + error.message });
        }
        setExporting(false);
        setExportType(null);
    };

    const loadData = async () => {
        setLoading(true);
        try {
            const [overviewRes, messagesRes, conversionsRes] = await Promise.all([
                apiFetch({ path: `/wwa/v1/analytics/overview?period=${period}` }),
                apiFetch({ path: `/wwa/v1/analytics/messages?period=${period}` }),
                apiFetch({ path: `/wwa/v1/analytics/conversions?period=${period}` })
            ]);
            setOverview(overviewRes || {});
            setMessageAnalytics(messagesRes || {});
            setConversionAnalytics(conversionsRes || {});
        } catch (error) {
            setMessage({ type: 'error', text: 'Veriler yüklenirken hata oluştu' });
        }
        setLoading(false);
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount || 0);
    };

    const formatNumber = (num) => {
        return new Intl.NumberFormat('tr-TR').format(num || 0);
    };

    const getPeriodLabel = () => {
        const labels = {
            '7days': 'Son 7 Gün',
            '30days': 'Son 30 Gün',
            '90days': 'Son 90 Gün',
            '1year': 'Son 1 Yıl'
        };
        return labels[period] || period;
    };

    if (loading) {
        return (
            <div className="wwa-loading">
                <Spinner />
                <p>Yükleniyor...</p>
            </div>
        );
    }

    const messages = overview.messages || {};
    const carts = overview.abandoned_carts || {};
    const flows = overview.flows || {};
    const chatbot = overview.chatbot || {};

    return (
        <div className="wwa-analytics">
            {message && (
                <Notice
                    status={message.type}
                    onRemove={() => setMessage(null)}
                    isDismissible
                >
                    {message.text}
                </Notice>
            )}

            <div className="wwa-analytics-header">
                <h2>Analitik & Raporlar</h2>
                <div className="wwa-analytics-actions">
                    <SelectControl
                        value={period}
                        options={[
                            { label: 'Son 7 Gün', value: '7days' },
                            { label: 'Son 30 Gün', value: '30days' },
                            { label: 'Son 90 Gün', value: '90days' },
                            { label: 'Son 1 Yıl', value: '1year' }
                        ]}
                        onChange={setPeriod}
                    />
                    <div className="wwa-export-buttons">
                        <div className="wwa-dropdown">
                            <Button
                                variant="secondary"
                                disabled={exporting}
                                onClick={() => document.getElementById('csv-dropdown').classList.toggle('show')}
                            >
                                {exporting && exportType === 'csv' ? (
                                    <>
                                        <Spinner /> Dışa Aktarılıyor...
                                    </>
                                ) : (
                                    <>
                                        <Dashicon icon="media-spreadsheet" /> CSV
                                    </>
                                )}
                            </Button>
                            <div id="csv-dropdown" className="wwa-dropdown-content">
                                <button onClick={() => { exportCSV('messages'); document.getElementById('csv-dropdown').classList.remove('show'); }}>
                                    📧 Mesajlar
                                </button>
                                <button onClick={() => { exportCSV('abandoned_carts'); document.getElementById('csv-dropdown').classList.remove('show'); }}>
                                    🛒 Terk Edilmiş Sepetler
                                </button>
                                <button onClick={() => { exportCSV('waitlist'); document.getElementById('csv-dropdown').classList.remove('show'); }}>
                                    📋 Bekleme Listesi
                                </button>
                            </div>
                        </div>
                        <Button
                            variant="primary"
                            disabled={exporting}
                            onClick={exportPDF}
                        >
                            {exporting && exportType === 'pdf' ? (
                                <>
                                    <Spinner /> Hazırlanıyor...
                                </>
                            ) : (
                                <>
                                    <Dashicon icon="pdf" /> PDF Rapor
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            </div>

            {/* Genel Özet */}
            <div className="wwa-analytics-section">
                <h3>Genel Bakış - {getPeriodLabel()}</h3>
                <div className="wwa-stats-grid large">
                    <Card className="wwa-stat-card highlight">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="email-alt" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(messages.total_messages)}</span>
                                    <span className="wwa-stat-label">Toplam Mesaj</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="wwa-stat-card success">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="yes-alt" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(messages.delivered)}</span>
                                    <span className="wwa-stat-label">Teslim Edildi</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="wwa-stat-card info">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="visibility" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(messages.read_count)}</span>
                                    <span className="wwa-stat-label">Okundu</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="wwa-stat-card warning">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="warning" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(messages.failed)}</span>
                                    <span className="wwa-stat-label">Başarısız</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>
                </div>
            </div>

            {/* Mesaj Analitikleri */}
            <div className="wwa-analytics-section">
                <h3>Mesaj Performansı</h3>
                <div className="wwa-analytics-grid">
                    <Card className="wwa-chart-card">
                        <CardHeader>
                            <h4>Mesaj Durumu Dağılımı</h4>
                        </CardHeader>
                        <CardBody>
                            {messageAnalytics.status_distribution && messageAnalytics.status_distribution.length > 0 ? (
                                <div className="wwa-status-distribution">
                                    {messageAnalytics.status_distribution.map((item, idx) => {
                                        const total = messageAnalytics.status_distribution.reduce((acc, i) => acc + parseInt(i.count), 0);
                                        const percent = total > 0 ? ((item.count / total) * 100).toFixed(1) : 0;
                                        return (
                                            <div key={idx} className="wwa-dist-item">
                                                <div className="wwa-dist-label">
                                                    <span className={`status-dot ${item.status}`}></span>
                                                    {getStatusLabel(item.status)}
                                                </div>
                                                <div className="wwa-dist-bar">
                                                    <div
                                                        className={`wwa-bar-fill ${item.status}`}
                                                        style={{ width: `${percent}%` }}
                                                    ></div>
                                                </div>
                                                <div className="wwa-dist-value">
                                                    {formatNumber(item.count)} ({percent}%)
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="wwa-no-data">Veri yok</p>
                            )}
                        </CardBody>
                    </Card>

                    <Card className="wwa-chart-card">
                        <CardHeader>
                            <h4>En Çok Mesaj Gönderilen Durumlar</h4>
                        </CardHeader>
                        <CardBody>
                            {messageAnalytics.top_order_statuses && messageAnalytics.top_order_statuses.length > 0 ? (
                                <div className="wwa-top-list">
                                    {messageAnalytics.top_order_statuses.map((item, idx) => (
                                        <div key={idx} className="wwa-top-item">
                                            <span className="wwa-rank">{idx + 1}</span>
                                            <span className="wwa-label">{item.status_type}</span>
                                            <span className="wwa-count">{formatNumber(item.count)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="wwa-no-data">Veri yok</p>
                            )}
                        </CardBody>
                    </Card>
                </div>

                {/* Zaman Çizelgesi */}
                <Card className="wwa-timeline-card">
                    <CardHeader>
                        <h4>Mesaj Zaman Çizelgesi</h4>
                    </CardHeader>
                    <CardBody>
                        {messageAnalytics.timeline && messageAnalytics.timeline.length > 0 ? (
                            <div className="wwa-timeline-chart">
                                <div className="wwa-timeline-bars">
                                    {messageAnalytics.timeline.map((item, idx) => {
                                        const maxValue = Math.max(...messageAnalytics.timeline.map(i => parseInt(i.total)));
                                        const height = maxValue > 0 ? ((item.total / maxValue) * 100) : 0;
                                        return (
                                            <div key={idx} className="wwa-timeline-bar-wrapper">
                                                <div
                                                    className="wwa-timeline-bar"
                                                    style={{ height: `${height}%` }}
                                                    title={`${item.period}: ${item.total} mesaj`}
                                                >
                                                    <span className="wwa-bar-value">{item.total}</span>
                                                </div>
                                                <span className="wwa-bar-label">{formatDateLabel(item.period)}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : (
                            <p className="wwa-no-data">Veri yok</p>
                        )}
                    </CardBody>
                </Card>
            </div>

            {/* Dönüşüm Analitikleri */}
            <div className="wwa-analytics-section">
                <h3>Dönüşüm Metrikleri</h3>
                <div className="wwa-stats-grid">
                    <Card className="wwa-stat-card">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="cart" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(carts.total_abandoned)}</span>
                                    <span className="wwa-stat-label">Terk Edilmiş Sepet</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="wwa-stat-card success">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="saved" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(carts.recovered)}</span>
                                    <span className="wwa-stat-label">Kurtarılan</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="wwa-stat-card highlight">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="chart-pie" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{conversionAnalytics.conversion_rate || 0}%</span>
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
                                    <span className="wwa-stat-value">{formatCurrency(carts.recovered_value)}</span>
                                    <span className="wwa-stat-label">Kurtarılan Değer</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>
                </div>

                <div className="wwa-analytics-grid">
                    {/* Hatırlatma Etkinliği */}
                    <Card className="wwa-chart-card">
                        <CardHeader>
                            <h4>Hatırlatma Etkinliği</h4>
                        </CardHeader>
                        <CardBody>
                            {conversionAnalytics.reminder_effectiveness && conversionAnalytics.reminder_effectiveness.length > 0 ? (
                                <div className="wwa-reminder-stats">
                                    {conversionAnalytics.reminder_effectiveness.map((item, idx) => {
                                        const rate = item.carts > 0 ? ((item.recovered / item.carts) * 100).toFixed(1) : 0;
                                        return (
                                            <div key={idx} className="wwa-reminder-item">
                                                <span className="wwa-reminder-label">
                                                    {item.reminder_count === '0' ? 'Hatırlatma Yok' : `${item.reminder_count}. Hatırlatma`}
                                                </span>
                                                <div className="wwa-reminder-bar">
                                                    <div
                                                        className="wwa-bar-fill"
                                                        style={{ width: `${rate}%` }}
                                                    ></div>
                                                </div>
                                                <span className="wwa-reminder-rate">{rate}%</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="wwa-no-data">Veri yok</p>
                            )}
                        </CardBody>
                    </Card>

                    {/* Waitlist Dönüşümü */}
                    <Card className="wwa-chart-card">
                        <CardHeader>
                            <h4>Stok Bildirimi Dönüşümü</h4>
                        </CardHeader>
                        <CardBody>
                            {conversionAnalytics.waitlist_conversion ? (
                                <div className="wwa-waitlist-stats">
                                    <div className="wwa-funnel">
                                        <div className="wwa-funnel-item">
                                            <Dashicon icon="groups" />
                                            <span className="value">{formatNumber(conversionAnalytics.waitlist_conversion.total_subscriptions)}</span>
                                            <span className="label">Toplam Abone</span>
                                        </div>
                                        <Dashicon icon="arrow-down-alt" className="wwa-funnel-arrow" />
                                        <div className="wwa-funnel-item">
                                            <Dashicon icon="megaphone" />
                                            <span className="value">{formatNumber(conversionAnalytics.waitlist_conversion.notified)}</span>
                                            <span className="label">Bildirildi</span>
                                        </div>
                                        <Dashicon icon="arrow-down-alt" className="wwa-funnel-arrow" />
                                        <div className="wwa-funnel-item success">
                                            <Dashicon icon="cart" />
                                            <span className="value">{formatNumber(conversionAnalytics.waitlist_conversion.converted)}</span>
                                            <span className="label">Satın Aldı</span>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <p className="wwa-no-data">Veri yok</p>
                            )}
                        </CardBody>
                    </Card>
                </div>
            </div>

            {/* Flow & Chatbot */}
            <div className="wwa-analytics-section">
                <h3>Otomasyon Performansı</h3>
                <div className="wwa-stats-grid">
                    <Card className="wwa-stat-card">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="networking" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(flows.total_executions)}</span>
                                    <span className="wwa-stat-label">Flow Çalışması</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="wwa-stat-card success">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="yes" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(flows.completed)}</span>
                                    <span className="wwa-stat-label">Başarılı Flow</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="wwa-stat-card">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="format-chat" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(chatbot.total_messages)}</span>
                                    <span className="wwa-stat-label">Chatbot Mesajı</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>

                    <Card className="wwa-stat-card">
                        <CardBody>
                            <div className="wwa-stat">
                                <Dashicon icon="admin-users" />
                                <div className="wwa-stat-content">
                                    <span className="wwa-stat-value">{formatNumber(chatbot.unique_users)}</span>
                                    <span className="wwa-stat-label">Benzersiz Kullanıcı</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>
                </div>
            </div>
        </div>
    );
};

const getStatusLabel = (status) => {
    const labels = {
        'pending': 'Beklemede',
        'sent': 'Gönderildi',
        'delivered': 'Teslim Edildi',
        'read': 'Okundu',
        'failed': 'Başarısız'
    };
    return labels[status] || status;
};

const formatDateLabel = (dateStr) => {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}`;
    }
    return dateStr;
};

export default Analytics;
