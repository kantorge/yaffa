<template>
    <div class="row">
        <div class="col-md-5">
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

    export default {
        components: {
            'account-balance': AccountBalance,
            'category-waterfall': CategoryWaterfall,
            'schedule-calendar': ScheduleCalendar,
            'payee-category-recommendation': PayeeCategoryRecommendation,
        },
    }
</script>
