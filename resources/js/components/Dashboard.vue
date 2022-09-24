<template>
    <div class="row">
        <div class="col-md-5">
            <account-balance></account-balance>
        </div>
        <!-- /.col -->

		<div class="col-md-7">
            <payee-category-recommendation></payee-category-recommendation>
            <schedule-calendar></schedule-calendar>
		</div>
		<!-- /.col -->
    </div>
    <!-- /.row -->
</template>

<script>
    import AccountBalance from './Widgets/AccountBalance.vue';
    import ScheduleCalendar from './Widgets/ScheduleCalendar.vue';
    import PayeeCategoryRecommendation from './Widgets/PayeeCategoryRecommendation.vue';

    export default {
        components: {
            'account-balance': AccountBalance,
            'schedule-calendar': ScheduleCalendar,
            'payee-category-recommendation': PayeeCategoryRecommendation,
        },
    }
</script>
