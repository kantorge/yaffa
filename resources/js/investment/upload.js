import { createApp } from 'vue'
import InvestmentUploadTool from '../components/InvestmentUploadTool.vue'

console.log('Investment upload.js loaded');
console.log('InvestmentUploadTool component:', InvestmentUploadTool);

const app = createApp({})

// Make the translation function available to Vue components
app.config.globalProperties.__ = window.__;

app.component('investment-upload-tool', InvestmentUploadTool)

console.log('About to mount Vue app');
app.mount('#app')
console.log('Vue app mounted');
