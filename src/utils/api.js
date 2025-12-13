/**
 * API Utility Functions
 */

const { apiUrl, nonce } = window.wwaSettings || {};

/**
 * API fetch wrapper
 *
 * @param {string} endpoint - API endpoint (örn: '/settings')
 * @param {object} options - Fetch options
 * @returns {Promise<any>}
 */
export const apiFetch = async (endpoint, options = {}) => {
    // GET istekleri için cache-busting timestamp ekle
    const timestamp = Date.now();
    const separator = endpoint.includes('?') ? '&' : '?';
    const urlWithTimestamp = options.method && options.method !== 'GET'
        ? `${apiUrl}${endpoint}`
        : `${apiUrl}${endpoint}${separator}_t=${timestamp}`;

    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        },
        cache: 'no-store'
    };

    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    };

    try {
        const response = await fetch(urlWithTimestamp, mergedOptions);

        // JSON parse
        const data = await response.json();

        // HTTP error kontrolü
        if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
};

/**
 * Debounce utility
 *
 * @param {Function} func - Çağrılacak fonksiyon
 * @param {number} wait - Bekleme süresi (ms)
 * @returns {Function}
 */
export const debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

/**
 * Format phone number for display
 *
 * @param {string} phone - Telefon numarası
 * @returns {string}
 */
export const formatPhone = (phone) => {
    if (!phone) return '';

    // Sadece rakamları al
    const cleaned = phone.replace(/\D/g, '');

    // Türkiye formatı
    if (cleaned.startsWith('90') && cleaned.length === 12) {
        return `+90 ${cleaned.slice(2, 5)} ${cleaned.slice(5, 8)} ${cleaned.slice(8)}`;
    }

    // Genel format
    if (cleaned.length >= 10) {
        return `+${cleaned}`;
    }

    return phone;
};

/**
 * Format date for display
 *
 * @param {string} dateString - ISO date string
 * @returns {string}
 */
export const formatDate = (dateString) => {
    if (!dateString) return '-';

    return new Date(dateString).toLocaleString('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

/**
 * Truncate text with ellipsis
 *
 * @param {string} text - Metin
 * @param {number} length - Maksimum uzunluk
 * @returns {string}
 */
export const truncate = (text, length = 50) => {
    if (!text) return '';
    if (text.length <= length) return text;
    return text.substring(0, length) + '...';
};

/**
 * Copy text to clipboard
 *
 * @param {string} text - Kopyalanacak metin
 * @returns {Promise<boolean>}
 */
export const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (error) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        return true;
    }
};

/**
 * Validate phone number
 *
 * @param {string} phone - Telefon numarası
 * @returns {object} { valid: boolean, formatted: string, error?: string }
 */
export const validatePhone = (phone) => {
    if (!phone) {
        return { valid: false, error: 'Telefon numarası gerekli' };
    }

    // Sadece rakamları al
    const cleaned = phone.replace(/\D/g, '');

    if (cleaned.length < 10) {
        return { valid: false, error: 'Telefon numarası çok kısa' };
    }

    if (cleaned.length > 15) {
        return { valid: false, error: 'Telefon numarası çok uzun' };
    }

    return { valid: true, formatted: cleaned };
};

export default {
    apiFetch,
    debounce,
    formatPhone,
    formatDate,
    truncate,
    copyToClipboard,
    validatePhone
};
