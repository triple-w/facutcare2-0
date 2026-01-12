import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'

import './bootstrap'

// Alpine plugins (seguros)
import collapse from '@alpinejs/collapse'
import focus from '@alpinejs/focus'
import intersect from '@alpinejs/intersect'
import mask from '@alpinejs/mask'

// Exponer Alpine (opcional, útil para debug)
window.Alpine = Alpine

// ✅ Registrar plugins UNA sola vez (Vite HMR safe)
if (!window.__FACTUCARE_ALPINE_PLUGINS__) {
  Alpine.plugin(collapse)
  Alpine.plugin(focus)
  Alpine.plugin(intersect)
  Alpine.plugin(mask)

  window.__FACTUCARE_ALPINE_PLUGINS__ = true
}

// ✅ Start Livewire UNA sola vez (Vite HMR safe)
// No uses Alpine.start() cuando usas Livewire
if (!window.__FACTUCARE_LIVEWIRE_STARTED__) {
  Livewire.start()
  window.__FACTUCARE_LIVEWIRE_STARTED__ = true
}
