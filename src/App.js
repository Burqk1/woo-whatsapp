import { useState, useEffect, useCallback } from '@wordpress/element';
import Dashboard from './components/Dashboard';
import Settings from './components/Settings';
import Templates from './components/Templates';
import Logs from './components/Logs';
import FlowBuilder from './components/FlowBuilder';
import AbandonedCart from './components/AbandonedCart';
import Chatbot from './components/Chatbot';
import Waitlist from './components/Waitlist';
import Analytics from './components/Analytics';
import SetupWizard from './components/SetupWizard';
import { apiFetch } from './utils/api';

// Pro Upgrade Prompt Component
const ProUpgradePrompt = ({ feature, onGoToLicense }) => (
    <div className="wwa-pro-upgrade-prompt">
        <div className="wwa-upgrade-icon">🔒</div>
        <h2>{feature} - Pro Özelliği</h2>
        <p>Bu özelliği kullanmak için Pro lisansınızı aktifleştirmeniz gerekmektedir.</p>
        <div className="wwa-upgrade-features">
            <div className="wwa-upgrade-feature">
                <span>✓</span> Flow Builder ile gelişmiş otomasyonlar
            </div>
            <div className="wwa-upgrade-feature">
                <span>✓</span> Terk edilmiş sepet kurtarma
            </div>
            <div className="wwa-upgrade-feature">
                <span>✓</span> Akıllı chatbot yanıtları
            </div>
            <div className="wwa-upgrade-feature">
                <span>✓</span> Stok bildirimleri ve bekleme listesi
            </div>
            <div className="wwa-upgrade-feature">
                <span>✓</span> Detaylı analitik ve raporlama
            </div>
        </div>
        <button className="wwa-btn wwa-btn-primary wwa-btn-large" onClick={onGoToLicense}>
            🔑 Lisansı Aktifleştir
        </button>
        <p className="wwa-upgrade-hint">
            Demo lisans anahtarları ile test edebilirsiniz:<br />
            <code>DEMO-1234-5678-DEMO</code>
        </p>
    </div>
);

// Sidebar Menu Items
const menuItems = [
    { id: 'dashboard', label: 'Dashboard', icon: 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z', isPro: false },
    { id: 'flows', label: 'Flow Builder', icon: 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 14h-3v3h-2v-3H8v-2h3v-3h2v3h3v2zm-3-7V3.5L18.5 9H13z', isPro: true },
    { id: 'abandoned-cart', label: 'Sepet Kurtarma', icon: 'M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z', isPro: true },
    { id: 'chatbot', label: 'Chatbot', icon: 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z', isPro: true },
    { id: 'waitlist', label: 'Stok Bildirimi', icon: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z', isPro: true },
    { id: 'templates', label: 'Şablonlar', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z', isPro: false },
    { id: 'analytics', label: 'Analitik', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z', isPro: true },
    { id: 'logs', label: 'Mesaj Logları', icon: 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z', isPro: false },
    { id: 'settings', label: 'Ayarlar', icon: 'M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z', isPro: false }
];

const App = () => {
    const [activeTab, setActiveTab] = useState('dashboard');
    const [notification, setNotification] = useState(null);
    const [showSetupWizard, setShowSetupWizard] = useState(false);
    const [setupChecked, setSetupChecked] = useState(false);
    const [license, setLicense] = useState({ is_valid: false, type: 'free' });
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

    const tabs = [
        'dashboard', 'flows', 'abandoned-cart', 'chatbot', 'waitlist',
        'templates', 'analytics', 'logs', 'settings'
    ];

    // Pro özelliklerin listesi
    const proFeatures = ['flows', 'abandoned-cart', 'chatbot', 'waitlist', 'analytics'];

    // Check if setup is needed and fetch license
    useEffect(() => {
        const checkSetupStatus = async () => {
            try {
                const [setupResponse, licenseResponse] = await Promise.all([
                    apiFetch('/setup/status'),
                    apiFetch('/license')
                ]);

                if (!setupResponse.setup_completed) {
                    setShowSetupWizard(true);
                }

                if (licenseResponse) {
                    setLicense(licenseResponse);
                }
            } catch (error) {
                console.error('Setup/License check failed:', error);
            }
            setSetupChecked(true);
        };
        checkSetupStatus();
    }, []);

    // Pro özellik kontrolü
    const isProFeature = (tab) => proFeatures.includes(tab);
    const canAccessFeature = (tab) => !isProFeature(tab) || license.is_valid;

    // URL hash'ten tab belirle
    useEffect(() => {
        const hash = window.location.hash.replace('#/', '').replace('#', '');
        if (tabs.includes(hash)) {
            setActiveTab(hash);
        }

        // Hash değişikliklerini dinle
        const handleHashChange = () => {
            const newHash = window.location.hash.replace('#/', '').replace('#', '');
            if (tabs.includes(newHash)) {
                setActiveTab(newHash);
            }
        };

        window.addEventListener('hashchange', handleHashChange);
        return () => window.removeEventListener('hashchange', handleHashChange);
    }, []);

    // Tab değiştir
    const handleTabChange = (tab) => {
        setActiveTab(tab);
        window.location.hash = `/${tab}`;
    };

    // Bildirim göster
    const showNotification = useCallback((type, message) => {
        setNotification({ type, message });
        setTimeout(() => setNotification(null), 5000);
    }, []);

    // Handle setup wizard completion
    const handleSetupComplete = () => {
        setShowSetupWizard(false);
        showNotification('success', 'Kurulum tamamlandı! Woo WhatsApp Pro kullanıma hazır.');
    };

    // Show setup wizard if needed
    if (showSetupWizard && setupChecked) {
        return (
            <SetupWizard
                onComplete={handleSetupComplete}
                showNotification={showNotification}
            />
        );
    }

    // Show loading while checking setup status
    if (!setupChecked) {
        return (
            <div className="wwa-fullpage-app">
                <div className="wwa-loading-screen">
                    <div className="wwa-spinner"></div>
                    <p>Yükleniyor...</p>
                </div>
            </div>
        );
    }

    // Get page title
    const getPageTitle = () => {
        const item = menuItems.find(m => m.id === activeTab);
        return item ? item.label : 'Dashboard';
    };

    return (
        <div className={`wwa-fullpage-app ${sidebarCollapsed ? 'sidebar-collapsed' : ''}`}>
            {/* Sidebar */}
            <aside className={`wwa-sidebar ${sidebarCollapsed ? 'collapsed' : ''}`}>
                {/* Sidebar Header */}
                <div className="wwa-sidebar-header">
                    <div className="wwa-logo">
                        <svg className="wwa-logo-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        {!sidebarCollapsed && (
                            <div className="wwa-logo-text">
                                <span className="wwa-logo-title">Woo WhatsApp</span>
                                <span className="wwa-logo-version">v{window.wwaSettings?.version || '3.0.0'}</span>
                            </div>
                        )}
                    </div>
                    <button
                        className="wwa-sidebar-toggle"
                        onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                        title={sidebarCollapsed ? 'Genişlet' : 'Daralt'}
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            {sidebarCollapsed ? (
                                <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
                            ) : (
                                <path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/>
                            )}
                        </svg>
                    </button>
                </div>

                {/* License Badge */}
                {!sidebarCollapsed && (
                    <div className="wwa-sidebar-license">
                        {license.is_valid ? (
                            <div className="wwa-license-badge pro">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                Pro Aktif
                            </div>
                        ) : (
                            <button
                                className="wwa-upgrade-btn"
                                onClick={() => handleTabChange('settings')}
                            >
                                Pro'ya Yükselt
                            </button>
                        )}
                    </div>
                )}

                {/* Navigation */}
                <nav className="wwa-sidebar-nav">
                    {menuItems.map(item => (
                        <button
                            key={item.id}
                            className={`wwa-nav-item ${activeTab === item.id ? 'active' : ''} ${item.isPro && !license.is_valid ? 'locked' : ''}`}
                            onClick={() => {
                                if (item.isPro && !license.is_valid) {
                                    showNotification('warning', '🔒 Bu özellik Pro lisans gerektirir.');
                                    return;
                                }
                                handleTabChange(item.id);
                            }}
                            title={sidebarCollapsed ? item.label : ''}
                        >
                            <svg className="wwa-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d={item.icon}/>
                            </svg>
                            {!sidebarCollapsed && (
                                <>
                                    <span className="wwa-nav-label">{item.label}</span>
                                    {item.isPro && (
                                        <span className={`wwa-nav-badge ${license.is_valid ? 'pro' : 'locked'}`}>
                                            {license.is_valid ? 'PRO' : '🔒'}
                                        </span>
                                    )}
                                </>
                            )}
                        </button>
                    ))}
                </nav>

                {/* Sidebar Footer */}
                {!sidebarCollapsed && (
                    <div className="wwa-sidebar-footer">
                        <a href="https://developer.dev" target="_blank" rel="noopener noreferrer" className="wwa-sidebar-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                            </svg>
                            Yardım & Destek
                        </a>
                    </div>
                )}
            </aside>

            {/* Main Content */}
            <main className="wwa-main">
                {/* Top Bar */}
                <header className="wwa-topbar">
                    <h1 className="wwa-page-title">{getPageTitle()}</h1>
                    <div className="wwa-topbar-actions">
                        {license.is_valid && (
                            <span className="wwa-pro-indicator">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                </svg>
                                Pro
                            </span>
                        )}
                    </div>
                </header>

                {/* Notification */}
                {notification && (
                    <div className={`wwa-notification ${notification.type}`}>
                        {notification.type === 'success' && '✓ '}
                        {notification.type === 'error' && '✗ '}
                        {notification.type === 'warning' && '⚠ '}
                        {notification.message}
                        <button className="wwa-notification-close" onClick={() => setNotification(null)}>×</button>
                    </div>
                )}

                {/* Content Area */}
                <div className="wwa-content-area">
                    {activeTab === 'dashboard' && <Dashboard showNotification={showNotification} />}
                    {activeTab === 'flows' && (canAccessFeature('flows') ? <FlowBuilder showNotification={showNotification} /> : <ProUpgradePrompt feature="Flow Builder" onGoToLicense={() => handleTabChange('settings')} />)}
                    {activeTab === 'abandoned-cart' && (canAccessFeature('abandoned-cart') ? <AbandonedCart showNotification={showNotification} /> : <ProUpgradePrompt feature="Sepet Kurtarma" onGoToLicense={() => handleTabChange('settings')} />)}
                    {activeTab === 'chatbot' && (canAccessFeature('chatbot') ? <Chatbot showNotification={showNotification} /> : <ProUpgradePrompt feature="Chatbot" onGoToLicense={() => handleTabChange('settings')} />)}
                    {activeTab === 'waitlist' && (canAccessFeature('waitlist') ? <Waitlist showNotification={showNotification} /> : <ProUpgradePrompt feature="Stok Bildirimi" onGoToLicense={() => handleTabChange('settings')} />)}
                    {activeTab === 'templates' && <Templates showNotification={showNotification} />}
                    {activeTab === 'analytics' && (canAccessFeature('analytics') ? <Analytics showNotification={showNotification} /> : <ProUpgradePrompt feature="Analitik" onGoToLicense={() => handleTabChange('settings')} />)}
                    {activeTab === 'logs' && <Logs showNotification={showNotification} />}
                    {activeTab === 'settings' && <Settings showNotification={showNotification} onLicenseChange={setLicense} />}
                </div>
            </main>
        </div>
    );
};

export default App;
