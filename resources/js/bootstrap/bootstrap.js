import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// NativePHP used to swap in a custom axios/fetch adapter here, because its iOS
// build served the app over a `php://` scheme that neither axios nor fetch
// understands. The Capacitor shells load this server over ordinary http(s), so
// there is nothing left to polyfill: a same-origin relative URL is all axios
// needs, and `fetch` is left untouched so Livewire works unchanged.

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import '../echo/echo';
