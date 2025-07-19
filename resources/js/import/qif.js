import { createApp } from 'vue';
import QifImport from './QifImport.vue';

const app = createApp({});
app.component('qif-import', QifImport);
app.mount('#qif-import-app');
