// resources/js/import/moneyhub.js
import { createApp } from 'vue';
import MoneyHubUploadTool from '../components/MoneyHubUploadTool.vue';

const el = document.getElementById('moneyhub-upload-app');
if (el) {
    const app = createApp({
        components: { MoneyHubUploadTool }
    });
    
    // Add global translator function
    app.config.globalProperties.__ = window.__;
    
    app.mount(el);
}
