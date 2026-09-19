import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// 📱 NATIVEPHP MOBILE ADAPTER 📱
// iOS uses 'php://' protocol which standard axios/fetch don't understand.
// Skip during build if not in mobile context or on Vercel
if (window.location.protocol === 'php:' && !import.meta.env.VERCEL) {
    try {
        // Use a dynamic path to prevent Vite from trying to resolve it at build time
        const adapterPath = '../../../vendor/nativephp/mobile/resources/js/phpProtocolAdapter.js';
        
        import(/* @vite-ignore */ adapterPath).then((module) => {
            const phpAdapter = module.default;
            window.axios.defaults.adapter = phpAdapter;
            
            // Also override global fetch for Livewire compatibility
            const originalFetch = window.fetch;
            window.fetch = function(url, options) {
                if (typeof url === 'string' && url.startsWith('/')) {
                    url = window.location.origin + url;
                }
                return originalFetch(url, options);
            };
            
            console.log('NativePHP: iOS Protocol Adapter loaded.');
        }).catch(err => {
            console.warn('NativePHP: Protocol adapter module not found (Web mode).');
        });
    } catch (e) {
        console.error('NativePHP: Failed to load protocol adapter', e);
    }
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import '../echo/echo';
