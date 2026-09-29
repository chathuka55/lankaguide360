import 'flowbite';
// Livewire ships its own Alpine (with the morph, focus, collapse… plugins). We bundle both here and
// start Livewire ourselves (config/livewire.php inject_assets=false, layouts use @livewireScriptConfig).
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import './site.js';

window.Alpine = Alpine;

// Start after every module script has run, so page bundles (e.g. admin.js, map.js, builder.js)
// can register Alpine.data() components first. Module scripts run before DOMContentLoaded fires.
if (document.readyState === 'complete') {
    Livewire.start();
} else {
    document.addEventListener('DOMContentLoaded', () => Livewire.start());
}
