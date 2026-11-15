import Alpine from '@alpinejs/csp';
import { register } from './components';

register(Alpine);
window.Alpine = Alpine;
Alpine.start();

// Progressive web app: the service worker caches the shell and shows the
// offline page when there is no connection.
if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
