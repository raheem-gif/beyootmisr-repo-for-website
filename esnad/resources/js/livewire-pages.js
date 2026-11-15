// Pages that use Livewire get Alpine from Livewire itself. Register the
// shared components on that instance instead of starting a second Alpine.
import { register } from './components';

document.addEventListener('alpine:init', () => register(window.Alpine));
