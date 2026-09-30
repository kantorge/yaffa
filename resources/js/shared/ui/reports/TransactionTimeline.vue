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
  import { __ } from '@/shared/lib/i18n';

  // Import all required reporting widgets
  import MonthlyTimelineChart from './MonthlyTimelineChart.vue';
  export default {
    name: 'TransactionTimeline',
    components: {
      'monthly-timeline': MonthlyTimelineChart,
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
    methods: {
      __,
    },
  };
</script>
