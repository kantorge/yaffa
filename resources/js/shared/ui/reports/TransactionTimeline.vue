<template>
  <div class="row mb-3">
    <div class="col-12">
      <monthly-timeline
        :transactions="transactions"
        :busy="busy"
        :matching-items-only="matchingItemsOnly"
        :category-ids="categoryIds"
        :tag-ids="tagIds"
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
      matchingItemsOnly: {
        type: Boolean,
        default: false,
      },
      categoryIds: {
        type: Array,
        default: () => [],
      },
      tagIds: {
        type: Array,
        default: () => [],
      },
    },
    mounted() {},
    methods: {
      __,
    },
  };
</script>
