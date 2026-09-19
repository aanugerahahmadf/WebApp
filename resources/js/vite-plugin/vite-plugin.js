import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import { networkInterfaces } from 'os';
import { existsSync, unlinkSync } from 'fs';
import path from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

/**
 * Get the platform-specific hot file path for Laravel Vite plugin.
 * Use this in your vite.config.js:
 *
 * laravel({
 *     input: ['resources/css/User/User.css', 'resources/css/Admin/Admin.css', 'resources/js/app/app.js'],
 *     hotFile: nativephpHotFile(),
 * })
 */
export function nativephpHotFile() {
    const isIos = process.argv.includes('--mode=ios');
    const isAndroid = process.argv.includes('--mode=android');

    if (isIos) {
        return 'public/ios-hot';
    }

    if (isAndroid) {
        return 'public/android-hot';
    }

    return 'public/hot';
}

// Get the local IP address for HMR
function getLocalIP() {
    const nets = networkInterfaces();
    for (const name of Object.keys(nets)) {
        for (const net of nets[name]) {
            // Skip over non-IPv4 and internal addresses
            if (net.family === 'IPv4' && !net.internal) {
                return net.address;
            }
        }
    }
    return 'localhost';
}

export function nativephpMobile() {
    const localIP = getLocalIP();
    const isIos = process.argv.includes('--mode=ios');
    const isAndroid = process.argv.includes('--mode=android');
    const isBuild = process.argv.includes('build');

    const config = {};

    if (isIos) {
        // Force the correct URL for iOS
        process.env.APP_URL = 'php://127.0.0.1';

        // Set the base path for iOS builds (only in production)
        if (isBuild) {
            config.base = '/_assets/build/';
        }

        // Always prevent Vite from pre-bundling axios so our plugin can intercept it
        config.optimizeDeps = {
            exclude: ['axios']
        };

        config.server = {
            host: localIP,
            cors: {
                origin: ['php://127.0.0.1'],
            },
        };

        // Add plugins for iOS
        config.plugins = [
            // Intercept ALL axios imports and wrap with PHP adapter
            {
                name: 'axios-php-wrapper',
                enforce: 'pre',
                resolveId(id) {
                    if (id === 'axios') {
                        // Return a virtual module ID
                        return '\0axios-with-php-adapter';
                    }
                    return null;
                },
                load(id) {
                    if (id === '\0axios-with-php-adapter') {
                        // Return code that imports real axios and applies the adapter
                        const adapterPath = resolve(__dirname, 'phpProtocolAdapter.js');
                        const axiosPath = path.resolve(process.cwd(), 'node_modules/axios/lib/axios.js');

                        return `
import axios from '${axiosPath}';
import phpAdapter from '${adapterPath}';

axios.defaults.adapter = phpAdapter;

export default axios;
export const isAxiosError = axios.isAxiosError;
export const isCancel = axios.isCancel;
export const mergeConfig = axios.mergeConfig;
`;
                    }
                    return null;
                }
            }
        ];
    }

    if (isAndroid) {
        process.env.APP_URL = 'http://127.0.0.1';

        config.server = {
            host: localIP,
            cors: {
                origin: ['http://127.0.0.1'],
            },
        };
    }

    // Extract axios plugin for iOS
    const axiosPlugin = config.plugins?.[0];
    delete config.plugins;

    const mainPlugin = {
        name: 'nativephp',
        enforce: 'pre',
        config() {
            return config;
        },
        closeBundle() {
            if (!isBuild) {
                return;
            }

            const hotFiles = ['public/ios-hot', 'public/android-hot', 'public/hot'];
            for (const hotFile of hotFiles) {
                const hotFilePath = path.resolve(process.cwd(), hotFile);
                if (existsSync(hotFilePath)) {
                    unlinkSync(hotFilePath);
                }
            }
        }
    };

    // Return array with axios wrapper only for iOS
    if (isIos) {
        return [mainPlugin, axiosPlugin];
    }

    return mainPlugin;
}
