<template>
  <div class="row mb-3">
    <div class="col-12">
      <monthly-timeline
        :transactions="transactions"
        :busy="busy"
      ></monthly-timeline>
    </div>
  </div>
</template>

<script>
  // Import all required reporting widgets
  import MonthlyTimeline from './MonthlyTimeline.vue';
  export default {
    name: 'ReportingCanvasFindTransactionsTimeline',
    components: {
      'monthly-timeline': MonthlyTimeline,
    },
    props: {
      transactions: {
        type: Array,
        required: false,
        default: () => [],
      },
      busy: {
        type: Boolean,
        required: true,
      },
    },
    mounted() {},
  };
</script>
