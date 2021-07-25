import { createApp } from 'vue'

import PayeeCategoryRecommendationBox from './../components/PayeeCategoryRecommendationBox.vue'
const app = createApp({})
app.component('payee-category-recommendation-box', PayeeCategoryRecommendationBox)

app.mount('#PayeeCategoryRecommendationContainer')
