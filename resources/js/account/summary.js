import { createApp } from 'vue'

import PayeeCategoryRecommendationBox from './../components/PayeeCategoryRecommendationBox.vue'
const appPayee = createApp({})
appPayee.component('payee-category-recommendation-box', PayeeCategoryRecommendationBox)
appPayee.mount('#PayeeCategoryRecommendationContainer')

import ScheduleCalendar from './../components/ScheduleCalendar.vue'
import VCalendar from 'v-calendar';
const appCalendar = createApp({})
appCalendar.component('schedule-calendar', ScheduleCalendar)
appCalendar.use(VCalendar, {})
appCalendar.mount('#ScheduleCalendar')
