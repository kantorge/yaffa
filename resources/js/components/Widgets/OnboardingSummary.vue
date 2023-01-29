<template>
  <div
      class="card mb-4"
      id="widgetOnboardingSummary"
      v-show="ready"
  >
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Welcome to YAFFA!') }}
      </div>
      <div>
        <button
            class="btn-close"
            :disabled="busy"
            :title="__('Temporarly hide this widget')"
            type="button"
            @click="hide"
        ></button>
      </div>
    </div>
    <div class="card-body">
      {{ __('We recommend completing the following steps to have a great experience with YAFFA.') }}
    </div>
    <ul class="list-group list-group-flush">
      <li
          class="list-group-item"
          v-for="(step, index) in onboardingSteps"
          v-bind:key="index"
      >
        <div class="d-flex justify-content-between">
          <span>
            <i v-if="!step.complete" class="text-primary fa-regular fa-square me-2"></i>
            <i v-if="step.complete" class="text-success fa-regular fa-square-check me-2"></i>
            {{ step.title }}
          </span>
          <span v-show="!step.complete" class="text-end">
            <a :href="step.link">{{ step.cta }}</a>
          </span>
        </div>
      </li>
    </ul>
    <div class="card-footer d-flex justify-content-between">
      <div>
        <span v-show="onboardingComplete">
          {{ __('Congratulations! Keep using YAFFA. You can dismiss this widget.') }}
        </span>
      </div>
      <button
          class="btn btn-sm btn-outline-primary"
          :disabled="busy"
          :title="__('Never show this widget')"
          type="button"
          @click="dismiss"
      >
        {{ __('Dismiss') }}
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: "OnboardingSummaryWidget",

  data() {
    return {
      dismissed: false,
      onboardingSteps: [],
      busy: false,
      ready: false,
    }
  },

  created() {
    this.busy = true;
    let vue = this;

    axios.get('/api/onboarding')
        .then(response => {
          vue.dismissed = response.data.dismissed;
          vue.onboardingSteps = response.data.steps;
        })
        .finally(() => {
          if (vue.dismissed) {
            vue.hide();
          } else {
            vue.ready = true;
            vue.busy = false;
          }
        });
  },

  methods: {
    hide() {
      document.getElementById('widgetOnboardingSummary').classList.add('hidden');
    },

    dismiss() {
      this.busy = true;
      axios.put('/api/onboadding/dismiss')
          .then(() => this.hide());
    }
  },

  computed: {
    onboardingComplete() {
      return this.onboardingSteps
          .filter(step => !step.complete)
          .filter(step => !step.excluded)
          .length === 0;
    }
  }
}
</script>
