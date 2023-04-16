<template>
  <div class="card mb-3" v-show="isVisible">
    <div class="card-header">
      <div class="card-title">
        {{ __('Schedule') }}
      </div>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-12 col-md-6">
          <dl class="row">
            <dt class="col-6 mb-2">
              {{ __('Frequency') }}
            </dt>
            <dd class="col-6 mb-2">
              {{ schedule.frequency }}
            </dd>

            <dt class="col-6 mb-2">
              {{ __('Interval') }}
            </dt>
            <dd class="col-6 mb-2">
              {{ schedule.interval }}
            </dd>

            <dt class="col-6 mb-2">
              {{ __('Count') }}
            </dt>
            <dd class="col-6 mb-2">
              {{ schedule.count }}
            </dd>

            <dt
                class="col-6 mb-2"
                v-if="isBudget"
            >
              {{ __('Budget inflation') }}
            </dt>
            <dd
                class="col-6 mb-2"
                v-if="isBudget"
            >
          <span v-if="typeof schedule.inflation !== 'undefined'">
            {{ schedule.inflation }}%</span>
              <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
            </dd>
          </dl>
        </div>
        <div class="col-12 col-md-6">
          <dl class="row">
            <dt class="col-6 mb-2">
              {{ __('Start date') }}
            </dt>
            <dd class="col-6 mb-2">
              {{ formattedDate(schedule.start_date) }}
            </dd>

            <dt
                class="col-6 mb-2"
                v-if="isSchedule"
            >
              {{ __('Next date') }}
            </dt>
            <dd
                class="col-6 mb-2"
                v-if="isSchedule"
            >
              <span v-if="schedule.next_date">{{ formattedDate(schedule.next_date) }}</span>
              <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
            </dd>

            <dt class="col-6 mb-2">
              {{ __('End date') }}
            </dt>
            <dd class="col-6 mb-2">
              <span v-if="schedule.end_date">{{ formattedDate(schedule.end_date) }}</span>
              <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
            </dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  components: {},

  props: {
    isVisible: Boolean,
    isSchedule: Boolean,
    isBudget: Boolean,
    schedule: Object,
    locale: {
      type: String,
      default: window.YAFFA.locale,
    }
  },

  data() {
    return {}
  },

  methods: {
    formattedDate(date) {
      if (typeof date === 'undefined') {
        return;
      }

      const newDate = new Date(date);

      return newDate.toLocaleDateString(this.locale);
    },
  }
}
</script>
