import '../bootstrap/bootstrap';
import '../echo/echo';
import '../advanced-file-upload/advanced-file-upload';
import '../sidebar-auto-expand/sidebar-auto-expand';
import 'emoji-picker-element';
import '../emoji-picker/emoji-picker';
import '../pdf-preview-plugin/pdf-preview-plugin';

// No phpProtocolAdapter import: it was only needed by NativePHP's iOS build,
// which served the app over a `php://` scheme. The file is not in the repo and
// nativephp is not a composer dependency, so importing it broke
// `npm run build:main` -- which is the build that feeds Laravel's @vite()
// manifest (public/build/manifest.json). The desktop and mobile entries
// already dropped this import for the same reason; see app-desktop.js and
// app-mobile.js.

// Dark mode synchronization (Follow System by Default)
function syncTheme() {
    const theme = localStorage.getItem('theme');
    const isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
    
    if (isDark) {
        document.documentElement.classList.add('dark');
        document.documentElement.style.colorScheme = 'dark';
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';
    }
}

syncTheme();

// Listen for system theme changes in real-time
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', syncTheme);
