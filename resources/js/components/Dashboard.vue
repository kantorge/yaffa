<template>
    <div class="row">
        <div class="col-md-5">
            <onboarding-card
                    :cardTitle="__('Welcome to YAFFA!')"
                    :cardBody="__('We recommend completing the following steps to have a great experience with YAFFA.')"
                    :completedMessage="__('Congratulations! Keep using YAFFA. You can dismiss this widget.')"
                    topic="dashboard"
            ></onboarding-card>
            <account-balance></account-balance>
            <payee-category-recommendation></payee-category-recommendation>
        </div>
        <!-- /.col -->

        <div class="col-md-7">
            <category-waterfall></category-waterfall>
            <schedule-calendar></schedule-calendar>
        </div>
        <!-- /.col -->
    </div>
    <!-- /.row -->
</template>

<script>
import AccountBalance from './Widgets/AccountBalance.vue';
import CategoryWaterfall from './Widgets/CategoryWaterfall.vue';
import ScheduleCalendar from './Widgets/ScheduleCalendar.vue';
import PayeeCategoryRecommendation from './Widgets/PayeeCategoryRecommendation.vue';
import OnboardingCard from "./Widgets/OnboardingCard.vue";

export default {
    components: {
        'account-balance': AccountBalance,
        'category-waterfall': CategoryWaterfall,
        'schedule-calendar': ScheduleCalendar,
        'payee-category-recommendation': PayeeCategoryRecommendation,
        'onboarding-card': OnboardingCard,
    },
}
</script>
