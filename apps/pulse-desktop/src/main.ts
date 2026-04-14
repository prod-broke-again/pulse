import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { useAuthStore } from './stores/authStore'

import './style.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
useAuthStore().hydrateFromCache()
app.mount('#app').$nextTick(() => {
  document.getElementById('app-splash')?.remove()
})
