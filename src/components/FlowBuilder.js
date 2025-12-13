import { useState, useEffect, useCallback } from '@wordpress/element';
import {
    Button,
    Card,
    CardBody,
    CardHeader,
    TextControl,
    TextareaControl,
    SelectControl,
    ToggleControl,
    Modal,
    Spinner,
    Notice,
    Icon,
    Dashicon,
    Tooltip
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const FlowBuilder = () => {
    const [flows, setFlows] = useState([]);
    const [triggers, setTriggers] = useState([]);
    const [actions, setActions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);
    const [editingFlow, setEditingFlow] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [flowLogs, setFlowLogs] = useState([]);
    const [showLogsModal, setShowLogsModal] = useState(false);
    const [selectedFlowId, setSelectedFlowId] = useState(null);

    // Flow düzenleme formu için state
    const [flowForm, setFlowForm] = useState({
        name: '',
        description: '',
        trigger_type: 'order_created',
        trigger_config: {},
        nodes: [],
        is_active: true
    });

    const [showTemplates, setShowTemplates] = useState(false);

    // Hazır şablonlar
    const flowTemplates = [
        {
            id: 'new_order_notification',
            name: '🛒 Yeni Sipariş Bildirimi',
            description: 'Yeni sipariş geldiğinde müşteriye ve admin\'e bildirim gönder',
            trigger_type: 'order_created',
            trigger_config: {},
            nodes: [
                {
                    id: 'node_1',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı} 👋\n\n✅ #{sipariş_no} numaralı siparişiniz alındı!\n\n📦 Ürünler:\n{ürün_listesi}\n\n💰 Toplam: {toplam_tutar}\n\nSiparişiniz en kısa sürede hazırlanacak. Teşekkürler! 🙏'
                    }
                },
                {
                    id: 'node_2',
                    type: 'send_whatsapp',
                    config: {
                        to: 'admin',
                        message: '🔔 YENİ SİPARİŞ!\n\nSipariş: #{sipariş_no}\nMüşteri: {müşteri_adı}\nTelefon: {müşteri_telefon}\nTutar: {toplam_tutar}\n\n{ürün_listesi}'
                    }
                }
            ]
        },
        {
            id: 'order_shipped',
            name: '🚚 Kargo Bildirimi',
            description: 'Sipariş kargoya verildiğinde müşteriye takip bilgisi gönder',
            trigger_type: 'order_status_changed',
            trigger_config: { from_status: 'any', to_status: 'shipped' },
            nodes: [
                {
                    id: 'node_1',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı} 🎉\n\n📦 #{sipariş_no} numaralı siparişiniz kargoya verildi!\n\n🚚 Kargo Firması: {kargo_firma}\n📋 Takip No: {kargo_takip}\n🔗 Takip: {kargo_link}\n\nİyi günler dileriz! 😊'
                    }
                }
            ]
        },
        {
            id: 'order_completed',
            name: '⭐ Sipariş Tamamlandı & Değerlendirme İste',
            description: 'Sipariş tamamlandığında teşekkür ve değerlendirme isteği gönder',
            trigger_type: 'order_status_changed',
            trigger_config: { from_status: 'any', to_status: 'completed' },
            nodes: [
                {
                    id: 'node_1',
                    type: 'wait',
                    config: { duration: 2, unit: 'days' }
                },
                {
                    id: 'node_2',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı} 👋\n\n#{sipariş_no} numaralı siparişiniz umarız elinize ulaşmıştır! 📦✨\n\nAlışverişinizden memnun kaldıysanız, bizi değerlendirmeniz bizim için çok önemli ⭐⭐⭐⭐⭐\n\nTeşekkür ederiz! 🙏'
                    }
                }
            ]
        },
        {
            id: 'abandoned_cart_recovery',
            name: '🛒 Terk Edilmiş Sepet Kurtarma',
            description: 'Sepet terk edildiğinde hatırlatma ve indirim kuponu gönder',
            trigger_type: 'abandoned_cart',
            trigger_config: { wait_minutes: 60 },
            nodes: [
                {
                    id: 'node_1',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı} 👋\n\nSepetinizde unuttuğunuz ürünler var! 🛒\n\n{sepet_ürünleri}\n\n💰 Toplam: {sepet_tutarı}\n\n🎁 Siparişinizi tamamlayın, kaçırmayın!\n\n🔗 Sepetinize dönün: {sepet_link}'
                    }
                },
                {
                    id: 'node_2',
                    type: 'wait',
                    config: { duration: 24, unit: 'hours' }
                },
                {
                    id: 'node_3',
                    type: 'apply_coupon',
                    config: { auto_generate: true, discount_type: 'percent', discount_amount: 10 }
                },
                {
                    id: 'node_4',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı} 🎉\n\nSepetinizdeki ürünler hala sizi bekliyor!\n\n🎁 Size özel %10 indirim kodunuz: {kupon_kodu}\n\n⏰ Bu kod 48 saat geçerlidir.\n\n🛒 Hemen alışverişi tamamlayın: {sepet_link}'
                    }
                }
            ]
        },
        {
            id: 'back_in_stock',
            name: '📦 Stok Bildirimi',
            description: 'Ürün stoğa girdiğinde bekleyen müşterilere haber ver',
            trigger_type: 'product_back_in_stock',
            trigger_config: {},
            nodes: [
                {
                    id: 'node_1',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı} 🎉\n\nBeklediğiniz ürün stoğa girdi!\n\n📦 {ürün_adı}\n💰 {ürün_fiyat}\n\n⚡ Stoklar sınırlı, hemen sipariş verin!\n\n🔗 {ürün_link}'
                    }
                }
            ]
        },
        {
            id: 'payment_reminder',
            name: '💳 Ödeme Hatırlatma',
            description: 'Ödeme bekleyen siparişler için hatırlatma gönder',
            trigger_type: 'order_status_changed',
            trigger_config: { from_status: 'any', to_status: 'on-hold' },
            nodes: [
                {
                    id: 'node_1',
                    type: 'wait',
                    config: { duration: 30, unit: 'minutes' }
                },
                {
                    id: 'node_2',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı} 👋\n\n#{sipariş_no} numaralı siparişiniz ödeme bekliyor 💳\n\n💰 Tutar: {toplam_tutar}\n🏦 Ödeme Yöntemi: {ödeme_yöntemi}\n\nÖdemenizi tamamladıktan sonra siparişiniz hemen işleme alınacaktır.\n\nSorularınız için bize ulaşabilirsiniz 📞'
                    }
                }
            ]
        },
        {
            id: 'order_cancelled',
            name: '❌ İptal Bildirimi',
            description: 'Sipariş iptal edildiğinde müşteriye bilgi ver',
            trigger_type: 'order_status_changed',
            trigger_config: { from_status: 'any', to_status: 'cancelled' },
            nodes: [
                {
                    id: 'node_1',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişiniz iptal edilmiştir ❌\n\nEğer bir ödeme yaptıysanız, iade işleminiz en kısa sürede gerçekleştirilecektir.\n\nSorularınız için bize ulaşabilirsiniz.\n\nAnlayışınız için teşekkürler 🙏'
                    }
                }
            ]
        },
        {
            id: 'refund_notification',
            name: '💸 İade Bildirimi',
            description: 'İade yapıldığında müşteriye bilgi ver',
            trigger_type: 'order_status_changed',
            trigger_config: { from_status: 'any', to_status: 'refunded' },
            nodes: [
                {
                    id: 'node_1',
                    type: 'send_whatsapp',
                    config: {
                        to: 'customer',
                        message: 'Merhaba {müşteri_adı},\n\n#{sipariş_no} numaralı siparişinizin iadesi tamamlandı ✅\n\n💰 İade Tutarı: {toplam_tutar}\n\nİade tutarı ödeme yönteminize göre 3-7 iş günü içinde hesabınıza yansıyacaktır.\n\nBizi tercih ettiğiniz için teşekkürler 🙏'
                    }
                }
            ]
        }
    ];

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        setLoading(true);
        try {
            const [flowsRes, triggersRes, actionsRes] = await Promise.all([
                apiFetch({ path: '/wwa/v1/flows' }),
                apiFetch({ path: '/wwa/v1/flows/triggers' }),
                apiFetch({ path: '/wwa/v1/flows/actions' })
            ]);
            console.log('Flows API response:', flowsRes);
            setFlows(flowsRes.flows || []);

            const triggersArray = triggersRes ? Object.entries(triggersRes).map(([id, data]) => ({
                id,
                ...data
            })) : [];
            const actionsArray = actionsRes ? Object.entries(actionsRes).map(([id, data]) => ({
                id,
                ...data
            })) : [];

            setTriggers(triggersArray);
            setActions(actionsArray);
        } catch (error) {
            console.error('Flow data load error:', error);
            setMessage({ type: 'error', text: 'Veriler yüklenirken hata oluştu: ' + error.message });
        }
        setLoading(false);
    };

    const openNewFlow = () => {
        setFlowForm({
            name: '',
            description: '',
            trigger_type: 'order_created',
            trigger_config: {},
            nodes: [],
            is_active: true
        });
        setEditingFlow(null);
        setShowTemplates(true); // Önce şablonları göster
    };

    const useTemplate = (template) => {
        // Node ID'lerini benzersiz yap
        const nodes = template.nodes.map((node, index) => ({
            ...node,
            id: `node_${Date.now()}_${index}`
        }));

        setFlowForm({
            name: template.name.replace(/^[^\s]+\s/, ''), // Emojiyi kaldır
            description: template.description,
            trigger_type: template.trigger_type,
            trigger_config: template.trigger_config,
            nodes: nodes,
            is_active: true
        });
        setShowTemplates(false);
        setShowModal(true);
    };

    const startFromScratch = () => {
        setFlowForm({
            name: '',
            description: '',
            trigger_type: 'order_created',
            trigger_config: {},
            nodes: [],
            is_active: true
        });
        setShowTemplates(false);
        setShowModal(true);
    };

    const openEditFlow = (flow) => {
        setFlowForm({
            name: flow.name || '',
            description: flow.description || '',
            trigger_type: flow.trigger_type || 'order_created',
            trigger_config: flow.trigger_config || {},
            nodes: flow.nodes || [],
            is_active: flow.status === 'active' || flow.is_active === '1' || flow.is_active === true
        });
        setEditingFlow(flow);
        setShowModal(true);
    };

    const saveFlow = async () => {
        if (!flowForm.name) {
            setMessage({ type: 'error', text: 'Flow adı gerekli' });
            return;
        }

        setSaving(true);
        try {
            if (editingFlow) {
                await apiFetch({
                    path: `/wwa/v1/flows/${editingFlow.id}`,
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(flowForm)
                });
                setMessage({ type: 'success', text: 'Flow güncellendi' });
            } else {
                const createRes = await apiFetch({
                    path: '/wwa/v1/flows',
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(flowForm)
                });
                console.log('Create flow response:', createRes);
                setMessage({ type: 'success', text: 'Flow oluşturuldu' });

                // Yeni flow'u doğrudan listeye ekle
                if (createRes?.flow) {
                    setFlows(prevFlows => [createRes.flow, ...prevFlows]);
                }
            }
            setShowModal(false);
        } catch (error) {
            setMessage({ type: 'error', text: error.message || 'Hata oluştu' });
        }
        setSaving(false);
    };

    const deleteFlow = async (flowId) => {
        if (!confirm('Bu flow\'u silmek istediğinize emin misiniz?')) return;

        try {
            const res = await apiFetch({
                path: `/wwa/v1/flows/${flowId}`,
                method: 'DELETE'
            });
            console.log('Delete response:', res);

            if (res?.success) {
                setMessage({ type: 'success', text: 'Flow silindi' });
                // Doğrudan state'den kaldır
                setFlows(prevFlows => prevFlows.filter(f => f.id != flowId));
            } else {
                setMessage({ type: 'error', text: res?.message || 'Flow silinemedi' });
            }
        } catch (error) {
            console.error('Delete error:', error);
            setMessage({ type: 'error', text: 'Flow silinemedi: ' + error.message });
        }
    };

    const toggleFlow = async (flowId) => {
        try {
            await apiFetch({
                path: `/wwa/v1/flows/${flowId}/toggle`,
                method: 'POST'
            });
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: 'Durum güncellenemedi' });
        }
    };

    const duplicateFlow = async (flowId) => {
        try {
            await apiFetch({
                path: `/wwa/v1/flows/${flowId}/duplicate`,
                method: 'POST'
            });
            setMessage({ type: 'success', text: 'Flow kopyalandı' });
            loadData();
        } catch (error) {
            setMessage({ type: 'error', text: 'Flow kopyalanamadı' });
        }
    };

    const viewLogs = async (flowId) => {
        setSelectedFlowId(flowId);
        try {
            const response = await apiFetch({
                path: `/wwa/v1/flows/logs?flow_id=${flowId}`
            });
            setFlowLogs(response || []);
            setShowLogsModal(true);
        } catch (error) {
            setMessage({ type: 'error', text: 'Loglar yüklenemedi' });
        }
    };

    // Node ekleme
    const addNode = (type) => {
        const newNode = {
            id: `node_${Date.now()}`,
            type: type,
            config: getDefaultNodeConfig(type),
            next: null
        };

        setFlowForm(prev => ({
            ...prev,
            nodes: [...prev.nodes, newNode]
        }));
    };

    const getDefaultNodeConfig = (type) => {
        switch (type) {
            case 'send_whatsapp':
                return { message: '', to: 'customer' };
            case 'wait':
                return { duration: 60, unit: 'minutes' };
            case 'condition':
                return { field: '', operator: 'equals', value: '' };
            case 'update_order_meta':
                return { key: '', value: '' };
            case 'add_order_note':
                return { note: '', is_customer_note: false };
            case 'apply_coupon':
                return { coupon_code: '', auto_generate: false };
            case 'send_email':
                return { to: '', subject: '', message: '' };
            case 'webhook_send':
                return { url: '', method: 'POST' };
            default:
                return {};
        }
    };

    // Node güncelleme
    const updateNode = (nodeId, config) => {
        setFlowForm(prev => ({
            ...prev,
            nodes: prev.nodes.map(node =>
                node.id === nodeId ? { ...node, config: { ...node.config, ...config } } : node
            )
        }));
    };

    // Node silme
    const removeNode = (nodeId) => {
        setFlowForm(prev => ({
            ...prev,
            nodes: prev.nodes.filter(node => node.id !== nodeId)
        }));
    };

    // Node sıralama (yukarı)
    const moveNodeUp = (index) => {
        if (index === 0) return;
        setFlowForm(prev => {
            const newNodes = [...prev.nodes];
            [newNodes[index - 1], newNodes[index]] = [newNodes[index], newNodes[index - 1]];
            return { ...prev, nodes: newNodes };
        });
    };

    // Node sıralama (aşağı)
    const moveNodeDown = (index) => {
        setFlowForm(prev => {
            if (index === prev.nodes.length - 1) return prev;
            const newNodes = [...prev.nodes];
            [newNodes[index], newNodes[index + 1]] = [newNodes[index + 1], newNodes[index]];
            return { ...prev, nodes: newNodes };
        });
    };

    const getTriggerLabel = (type) => {
        const trigger = triggers.find(t => t.id === type);
        return trigger ? trigger.name : type;
    };

    const getActionLabel = (type) => {
        const action = actions.find(a => a.id === type);
        return action ? action.name : type;
    };

    const renderNodeEditor = (node, index) => {
        const action = actions.find(a => a.id === node.type);

        return (
            <Card key={node.id} className="wwa-flow-node">
                <CardHeader>
                    <div className="wwa-node-header">
                        <span className="wwa-node-number">{index + 1}</span>
                        <span className="wwa-node-type">{action?.name || node.type}</span>
                        <div className="wwa-node-actions">
                            <Button
                                isSmall
                                icon="arrow-up-alt"
                                onClick={() => moveNodeUp(index)}
                                disabled={index === 0}
                            />
                            <Button
                                isSmall
                                icon="arrow-down-alt"
                                onClick={() => moveNodeDown(index)}
                                disabled={index === flowForm.nodes.length - 1}
                            />
                            <Button
                                isSmall
                                icon="trash"
                                isDestructive
                                onClick={() => removeNode(node.id)}
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardBody>
                    {renderNodeConfig(node)}
                </CardBody>
            </Card>
        );
    };

    const renderNodeConfig = (node) => {
        switch (node.type) {
            case 'send_whatsapp':
                return (
                    <>
                        <SelectControl
                            label="Alıcı"
                            value={node.config.to || 'customer'}
                            options={[
                                { label: 'Müşteri', value: 'customer' },
                                { label: 'Admin', value: 'admin' },
                                { label: 'Özel Numara', value: 'custom' }
                            ]}
                            onChange={(value) => updateNode(node.id, { to: value })}
                        />
                        {node.config.to === 'custom' && (
                            <TextControl
                                label="Telefon Numarası"
                                value={node.config.custom_phone || ''}
                                onChange={(value) => updateNode(node.id, { custom_phone: value })}
                                placeholder="+905551234567"
                            />
                        )}
                        <TextareaControl
                            label="Mesaj"
                            value={node.config.message || ''}
                            onChange={(value) => updateNode(node.id, { message: value })}
                            placeholder="Mesaj şablonunuzu yazın... {müşteri_adı}, {sipariş_no} gibi placeholder'lar kullanabilirsiniz."
                            rows={4}
                        />
                    </>
                );

            case 'wait':
                return (
                    <div className="wwa-wait-config">
                        <TextControl
                            label="Bekleme Süresi"
                            type="number"
                            value={node.config.duration || 60}
                            onChange={(value) => updateNode(node.id, { duration: parseInt(value) })}
                        />
                        <SelectControl
                            label="Birim"
                            value={node.config.unit || 'minutes'}
                            options={[
                                { label: 'Dakika', value: 'minutes' },
                                { label: 'Saat', value: 'hours' },
                                { label: 'Gün', value: 'days' }
                            ]}
                            onChange={(value) => updateNode(node.id, { unit: value })}
                        />
                    </div>
                );

            case 'condition':
                return (
                    <>
                        <SelectControl
                            label="Alan"
                            value={node.config.field || ''}
                            options={[
                                { label: 'Alan seçin...', value: '' },
                                { label: 'Sipariş Durumu', value: 'order_status' },
                                { label: 'Sipariş Toplamı', value: 'order_total' },
                                { label: 'Ödeme Yöntemi', value: 'payment_method' },
                                { label: 'Kargo Yöntemi', value: 'shipping_method' },
                                { label: 'Müşteri E-posta', value: 'customer_email' },
                                { label: 'Ürün Kategorisi', value: 'product_category' }
                            ]}
                            onChange={(value) => updateNode(node.id, { field: value })}
                        />
                        <SelectControl
                            label="Operatör"
                            value={node.config.operator || 'equals'}
                            options={[
                                { label: 'Eşit', value: 'equals' },
                                { label: 'Eşit Değil', value: 'not_equals' },
                                { label: 'İçerir', value: 'contains' },
                                { label: 'Büyük', value: 'greater' },
                                { label: 'Küçük', value: 'less' }
                            ]}
                            onChange={(value) => updateNode(node.id, { operator: value })}
                        />
                        <TextControl
                            label="Değer"
                            value={node.config.value || ''}
                            onChange={(value) => updateNode(node.id, { value: value })}
                        />
                    </>
                );

            case 'add_order_note':
                return (
                    <>
                        <TextareaControl
                            label="Not"
                            value={node.config.note || ''}
                            onChange={(value) => updateNode(node.id, { note: value })}
                            rows={3}
                        />
                        <ToggleControl
                            label="Müşteriye göster"
                            checked={node.config.is_customer_note || false}
                            onChange={(value) => updateNode(node.id, { is_customer_note: value })}
                        />
                    </>
                );

            case 'apply_coupon':
                return (
                    <>
                        <ToggleControl
                            label="Otomatik kupon oluştur"
                            checked={node.config.auto_generate || false}
                            onChange={(value) => updateNode(node.id, { auto_generate: value })}
                        />
                        {!node.config.auto_generate && (
                            <TextControl
                                label="Kupon Kodu"
                                value={node.config.coupon_code || ''}
                                onChange={(value) => updateNode(node.id, { coupon_code: value })}
                            />
                        )}
                        {node.config.auto_generate && (
                            <>
                                <SelectControl
                                    label="İndirim Tipi"
                                    value={node.config.discount_type || 'percent'}
                                    options={[
                                        { label: 'Yüzde', value: 'percent' },
                                        { label: 'Sabit Tutar', value: 'fixed_cart' }
                                    ]}
                                    onChange={(value) => updateNode(node.id, { discount_type: value })}
                                />
                                <TextControl
                                    label="İndirim Miktarı"
                                    type="number"
                                    value={node.config.discount_amount || ''}
                                    onChange={(value) => updateNode(node.id, { discount_amount: value })}
                                />
                            </>
                        )}
                    </>
                );

            case 'send_email':
                return (
                    <>
                        <SelectControl
                            label="Alıcı"
                            value={node.config.to || 'customer'}
                            options={[
                                { label: 'Müşteri', value: 'customer' },
                                { label: 'Admin', value: 'admin' },
                                { label: 'Özel E-posta', value: 'custom' }
                            ]}
                            onChange={(value) => updateNode(node.id, { to: value })}
                        />
                        {node.config.to === 'custom' && (
                            <TextControl
                                label="E-posta Adresi"
                                value={node.config.custom_email || ''}
                                onChange={(value) => updateNode(node.id, { custom_email: value })}
                            />
                        )}
                        <TextControl
                            label="Konu"
                            value={node.config.subject || ''}
                            onChange={(value) => updateNode(node.id, { subject: value })}
                        />
                        <TextareaControl
                            label="Mesaj"
                            value={node.config.message || ''}
                            onChange={(value) => updateNode(node.id, { message: value })}
                            rows={4}
                        />
                    </>
                );

            case 'webhook_send':
                return (
                    <>
                        <TextControl
                            label="Webhook URL"
                            value={node.config.url || ''}
                            onChange={(value) => updateNode(node.id, { url: value })}
                            placeholder="https://..."
                        />
                        <SelectControl
                            label="Method"
                            value={node.config.method || 'POST'}
                            options={[
                                { label: 'POST', value: 'POST' },
                                { label: 'GET', value: 'GET' },
                                { label: 'PUT', value: 'PUT' }
                            ]}
                            onChange={(value) => updateNode(node.id, { method: value })}
                        />
                    </>
                );

            case 'update_order_meta':
                return (
                    <>
                        <TextControl
                            label="Meta Key"
                            value={node.config.key || ''}
                            onChange={(value) => updateNode(node.id, { key: value })}
                        />
                        <TextControl
                            label="Meta Value"
                            value={node.config.value || ''}
                            onChange={(value) => updateNode(node.id, { value: value })}
                        />
                    </>
                );

            default:
                return <p>Bu action tipi için yapılandırma mevcut değil.</p>;
        }
    };

    const renderTriggerConfig = () => {
        switch (flowForm.trigger_type) {
            case 'order_status_changed':
                return (
                    <>
                        <SelectControl
                            label="Önceki Durum"
                            value={flowForm.trigger_config.from_status || 'any'}
                            options={[
                                { label: 'Herhangi', value: 'any' },
                                { label: 'Beklemede', value: 'pending' },
                                { label: 'İşleniyor', value: 'processing' },
                                { label: 'Beklemede (Ödeme)', value: 'on-hold' },
                                { label: 'Tamamlandı', value: 'completed' }
                            ]}
                            onChange={(value) => setFlowForm(prev => ({
                                ...prev,
                                trigger_config: { ...prev.trigger_config, from_status: value }
                            }))}
                        />
                        <SelectControl
                            label="Yeni Durum"
                            value={flowForm.trigger_config.to_status || 'any'}
                            options={[
                                { label: 'Herhangi', value: 'any' },
                                { label: 'İşleniyor', value: 'processing' },
                                { label: 'Tamamlandı', value: 'completed' },
                                { label: 'Kargoya Verildi', value: 'shipped' },
                                { label: 'İptal', value: 'cancelled' },
                                { label: 'İade', value: 'refunded' }
                            ]}
                            onChange={(value) => setFlowForm(prev => ({
                                ...prev,
                                trigger_config: { ...prev.trigger_config, to_status: value }
                            }))}
                        />
                    </>
                );

            case 'abandoned_cart':
                return (
                    <TextControl
                        label="Bekleme Süresi (dakika)"
                        type="number"
                        value={flowForm.trigger_config.wait_minutes || 30}
                        onChange={(value) => setFlowForm(prev => ({
                            ...prev,
                            trigger_config: { ...prev.trigger_config, wait_minutes: parseInt(value) }
                        }))}
                        help="Sepet terk edildikten sonra kaç dakika bekleneceği"
                    />
                );

            case 'scheduled':
                return (
                    <>
                        <SelectControl
                            label="Zamanlama Tipi"
                            value={flowForm.trigger_config.schedule_type || 'daily'}
                            options={[
                                { label: 'Günlük', value: 'daily' },
                                { label: 'Haftalık', value: 'weekly' },
                                { label: 'Aylık', value: 'monthly' }
                            ]}
                            onChange={(value) => setFlowForm(prev => ({
                                ...prev,
                                trigger_config: { ...prev.trigger_config, schedule_type: value }
                            }))}
                        />
                        <TextControl
                            label="Saat"
                            type="time"
                            value={flowForm.trigger_config.time || '09:00'}
                            onChange={(value) => setFlowForm(prev => ({
                                ...prev,
                                trigger_config: { ...prev.trigger_config, time: value }
                            }))}
                        />
                    </>
                );

            case 'webhook_received':
                return (
                    <TextControl
                        label="Webhook Secret"
                        value={flowForm.trigger_config.secret || ''}
                        onChange={(value) => setFlowForm(prev => ({
                            ...prev,
                            trigger_config: { ...prev.trigger_config, secret: value }
                        }))}
                        help="Webhook doğrulaması için güvenlik anahtarı"
                    />
                );

            default:
                return null;
        }
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
        <div className="wwa-flow-builder">
            {message && (
                <Notice
                    status={message.type}
                    onRemove={() => setMessage(null)}
                    isDismissible
                >
                    {message.text}
                </Notice>
            )}

            <div className="wwa-flow-header">
                <h2>Otomasyon Akışları (Flow Builder)</h2>
                <Button isPrimary onClick={openNewFlow}>
                    <Dashicon icon="plus-alt" /> Yeni Flow Oluştur
                </Button>
            </div>

            <div className="wwa-flow-list">
                {flows.length === 0 ? (
                    <Card>
                        <CardBody>
                            <div className="wwa-empty-state">
                                <Dashicon icon="networking" size={48} />
                                <h3>Henüz otomasyon akışı yok</h3>
                                <p>Sipariş durumu değişikliği, terk edilmiş sepet ve daha fazlası için otomatik WhatsApp mesajları gönderin.</p>
                                <Button isPrimary onClick={openNewFlow}>
                                    İlk Flow'unuzu Oluşturun
                                </Button>
                            </div>
                        </CardBody>
                    </Card>
                ) : (
                    flows.map(flow => (
                        <Card key={flow.id} className={`wwa-flow-card ${flow.status === 'active' ? 'active' : 'inactive'}`}>
                            <CardBody>
                                <div className="wwa-flow-card-content">
                                    <div className="wwa-flow-info">
                                        <div className="wwa-flow-status">
                                            <span className={`status-badge ${flow.status === 'active' ? 'active' : 'inactive'}`}>
                                                {flow.status === 'active' ? 'Aktif' : 'Pasif'}
                                            </span>
                                        </div>
                                        <h3>{flow.name}</h3>
                                        {flow.description && <p className="description">{flow.description}</p>}
                                        <div className="wwa-flow-meta">
                                            <span className="trigger-badge">
                                                <Dashicon icon="flag" />
                                                {getTriggerLabel(flow.trigger_type)}
                                            </span>
                                            <span className="node-count">
                                                <Dashicon icon="editor-ol" />
                                                {(flow.nodes?.length || 0)} adım
                                            </span>
                                            <span className="execution-count">
                                                <Dashicon icon="chart-line" />
                                                {flow.execution_count || 0} çalıştırma
                                            </span>
                                        </div>
                                    </div>
                                    <div className="wwa-flow-actions">
                                        <Tooltip text={flow.status === 'active' ? 'Devre dışı bırak' : 'Etkinleştir'}>
                                            <Button
                                                isSecondary
                                                onClick={() => toggleFlow(flow.id)}
                                            >
                                                <Dashicon icon={flow.status === 'active' ? 'controls-pause' : 'controls-play'} />
                                            </Button>
                                        </Tooltip>
                                        <Tooltip text="Düzenle">
                                            <Button
                                                isSecondary
                                                onClick={() => openEditFlow(flow)}
                                            >
                                                <Dashicon icon="edit" />
                                            </Button>
                                        </Tooltip>
                                        <Tooltip text="Kopyala">
                                            <Button
                                                isSecondary
                                                onClick={() => duplicateFlow(flow.id)}
                                            >
                                                <Dashicon icon="admin-page" />
                                            </Button>
                                        </Tooltip>
                                        <Tooltip text="Logları Gör">
                                            <Button
                                                isSecondary
                                                onClick={() => viewLogs(flow.id)}
                                            >
                                                <Dashicon icon="list-view" />
                                            </Button>
                                        </Tooltip>
                                        <Button
                                            isDestructive
                                            onClick={() => deleteFlow(flow.id)}
                                            title="Sil"
                                            style={{ marginLeft: '4px' }}
                                        >
                                            <Dashicon icon="trash" />
                                        </Button>
                                    </div>
                                </div>
                            </CardBody>
                        </Card>
                    ))
                )}
            </div>

            {/* Şablon Seçme Modal */}
            {showTemplates && (
                <Modal
                    title="Nasıl başlamak istersiniz?"
                    onRequestClose={() => setShowTemplates(false)}
                    className="wwa-templates-modal"
                >
                    <div className="wwa-templates-intro">
                        <p>Hazır bir şablon kullanarak hızlıca başlayın veya sıfırdan oluşturun.</p>
                    </div>

                    <div className="wwa-templates-grid">
                        <Card className="wwa-template-card wwa-template-scratch" onClick={startFromScratch}>
                            <CardBody>
                                <div className="wwa-template-icon">➕</div>
                                <h3>Sıfırdan Oluştur</h3>
                                <p>Boş bir flow ile başlayın ve kendi akışınızı oluşturun</p>
                            </CardBody>
                        </Card>

                        {flowTemplates.map(template => (
                            <Card key={template.id} className="wwa-template-card" onClick={() => useTemplate(template)}>
                                <CardBody>
                                    <div className="wwa-template-icon">{template.name.split(' ')[0]}</div>
                                    <h3>{template.name.replace(/^[^\s]+\s/, '')}</h3>
                                    <p>{template.description}</p>
                                    <div className="wwa-template-meta">
                                        <span>{template.nodes.length} adım</span>
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
                        .wwa-templates-grid {
                            display: grid !important;
                            grid-template-columns: repeat(3, 1fr) !important;
                            gap: 16px !important;
                            max-height: 65vh;
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
                        .wwa-templates-modal .wwa-template-scratch {
                            border: 2px dashed #ddd !important;
                            background: #f9f9f9;
                        }
                        .wwa-templates-modal .wwa-template-scratch:hover {
                            border-color: #25D366 !important;
                            background: #fff;
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

            {/* Flow Düzenleme Modal */}
            {showModal && (
                <Modal
                    title={editingFlow ? 'Flow Düzenle' : 'Yeni Flow Oluştur'}
                    onRequestClose={() => setShowModal(false)}
                    className="wwa-flow-modal"
                    isFullScreen
                >
                    <div className="wwa-flow-editor">
                        <div className="wwa-flow-sidebar">
                            <h3>Flow Bilgileri</h3>
                            <TextControl
                                label="Flow Adı"
                                value={flowForm.name}
                                onChange={(value) => setFlowForm(prev => ({ ...prev, name: value }))}
                                placeholder="Örn: Sipariş Onayı Bildirimi"
                            />
                            <TextareaControl
                                label="Açıklama"
                                value={flowForm.description}
                                onChange={(value) => setFlowForm(prev => ({ ...prev, description: value }))}
                                placeholder="Bu flow ne işe yarar?"
                                rows={2}
                            />
                            <ToggleControl
                                label="Aktif"
                                checked={flowForm.is_active}
                                onChange={(value) => setFlowForm(prev => ({ ...prev, is_active: value }))}
                            />

                            <hr />

                            <h3>Tetikleyici (Trigger)</h3>
                            <SelectControl
                                label="Trigger Tipi"
                                value={flowForm.trigger_type}
                                options={triggers.map(t => ({ label: t.name, value: t.id }))}
                                onChange={(value) => setFlowForm(prev => ({
                                    ...prev,
                                    trigger_type: value,
                                    trigger_config: {}
                                }))}
                            />
                            {renderTriggerConfig()}

                            <hr />

                            <h3>Aksiyonlar</h3>
                            <p className="wwa-help-text">Flow'a eklemek için bir aksiyon seçin:</p>
                            <div className="wwa-action-buttons">
                                {actions.map(action => (
                                    <Button
                                        key={action.id}
                                        isSecondary
                                        onClick={() => addNode(action.id)}
                                        className="wwa-action-btn"
                                    >
                                        <Dashicon icon={getActionIcon(action.id)} />
                                        {action.name}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <div className="wwa-flow-canvas">
                            <h3>Flow Adımları</h3>
                            {flowForm.nodes.length === 0 ? (
                                <div className="wwa-empty-canvas">
                                    <Dashicon icon="arrow-left-alt" />
                                    <p>Soldaki menüden aksiyon ekleyerek başlayın</p>
                                </div>
                            ) : (
                                <div className="wwa-nodes-list">
                                    {flowForm.nodes.map((node, index) => renderNodeEditor(node, index))}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="wwa-modal-footer">
                        <Button isSecondary onClick={() => setShowModal(false)}>
                            İptal
                        </Button>
                        <Button isPrimary onClick={saveFlow} isBusy={saving}>
                            {saving ? 'Kaydediliyor...' : (editingFlow ? 'Güncelle' : 'Oluştur')}
                        </Button>
                    </div>
                </Modal>
            )}

            {/* Flow Logs Modal */}
            {showLogsModal && (
                <Modal
                    title="Flow Çalıştırma Logları"
                    onRequestClose={() => setShowLogsModal(false)}
                    className="wwa-logs-modal"
                >
                    {flowLogs.length === 0 ? (
                        <p>Bu flow henüz çalıştırılmamış.</p>
                    ) : (
                        <table className="wwa-logs-table">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                    <th>Detay</th>
                                </tr>
                            </thead>
                            <tbody>
                                {flowLogs.map(log => (
                                    <tr key={log.id}>
                                        <td>{log.created_at}</td>
                                        <td>
                                            <span className={`status-badge ${log.status}`}>
                                                {log.status === 'completed' ? 'Başarılı' :
                                                 log.status === 'failed' ? 'Başarısız' :
                                                 log.status === 'running' ? 'Çalışıyor' : log.status}
                                            </span>
                                        </td>
                                        <td>{log.details || '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Modal>
            )}
        </div>
    );
};

const getActionIcon = (actionId) => {
    const icons = {
        'send_whatsapp': 'whatsapp',
        'wait': 'clock',
        'condition': 'randomize',
        'update_order_meta': 'database',
        'add_order_note': 'edit',
        'apply_coupon': 'tag',
        'send_email': 'email',
        'webhook_send': 'admin-links',
        'stop_flow': 'dismiss'
    };
    return icons[actionId] || 'admin-generic';
};

export default FlowBuilder;
