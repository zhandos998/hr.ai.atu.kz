import './bootstrap';

import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { createPinia } from 'pinia';
import { installAlertOverride } from './plugins/installAlertOverride';

const app = createApp(App);
const pinia = createPinia();

installAlertOverride(pinia);

app
    .use(pinia)
    .use(router)
    .mount('#app');
