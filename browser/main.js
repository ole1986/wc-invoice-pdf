import { createApp } from 'vue'

// Vuetify
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import 'vuetify/styles'
import App from './App.vue'

const app = createApp(App)

// Register Vuetify as plugin
const vuetify = createVuetify()
app.use(vuetify).mount("#app")