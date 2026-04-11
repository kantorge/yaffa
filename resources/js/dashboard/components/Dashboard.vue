<template>
  <div class="row">
    <div class="col-md-5">
      <onboarding-card
        :cardTitle="__('Welcome to YAFFA!')"
        :cardBody="
          __(
            'We recommend completing the following steps to have a great experience with YAFFA.',
          )
        "
        :completedMessage="
          __('Congratulations! Keep using YAFFA. You can dismiss this widget.')
        "
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
  import AccountBalance from './widgets/AccountBalance.vue';
  import CategoryWaterfall from './widgets/CategoryWaterfall.vue';
  import ScheduleCalendar from './widgets/ScheduleCalendar.vue';
  import PayeeCategoryRecommendation from './widgets/PayeeCategoryRecommendation.vue';
  import OnboardingCard from './widgets/OnboardingCard.vue';

  export default {
    components: {
      'account-balance': AccountBalance,
      'category-waterfall': CategoryWaterfall,
      'schedule-calendar': ScheduleCalendar,
      'payee-category-recommendation': PayeeCategoryRecommendation,
      'onboarding-card': OnboardingCard,
    },
  };
</script>
