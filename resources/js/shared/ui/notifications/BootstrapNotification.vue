<template>
  <div
      class="alert fade show"
      :class="[
            { 'alert-dismissible' : dismissible },
            'alert-' + type,
      ]"
      role="alert"
      @mouseover="pauseTimer"
      @mouseleave="resumeTimer"
  >
    <div>
      <h4
          class="alert-heading"
          v-if="title || icon"
      >
      <span
          :class="[
              'me-1 icon fa',
              'fa-' + icon,
          ]"
          v-if="icon"
      ></span>
        <span v-html="title" class="align-text-bottom"></span>
      </h4>
      <span v-html="message"></span>
      <button v-if="dismissible" type="button" class="btn-close" data-coreui-dismiss="alert"
              aria-label="Close"></button>
    </div>
    <span class="notificationCountdownContainer" v-if="timer">
      <span class="notificationCountdownBar" :style="{ width: barWidth + '%' }"></span>
    </span>
  </div>
</template>

<script>
export default {
  name: 'BootstrapNotification',
  props: {
    dismissible: {
      type: Boolean,
      default: false,
    },
    type: {
      type: String,
      required: true,
    },
    title: {
      type: String,
      default: null,
    },
    message: {
      type: String,
      required: true,
    },
    icon: {
      type: String,
      default: null,
    },
    timeout: {
      type: Number,
      default: 0,
    }
  },
  data() {
    return {
      barWidth: 100,
      timer: null,
      interval: 10,
      timeElapsed: 0,
    }
  },
  mounted() {
    if (!this.hasTimeout  || this.timeout <= this.interval) {
      return;
    }

    this.timer = setInterval(this.updateCountdown, this.interval);
  },
  beforeDestroy() {
    clearInterval(this.timer);
  },
  methods: {
    updateCountdown() {
      this.timeElapsed += this.interval;
      this.barWidth = 100 - (this.timeElapsed / this.timeout) * 100;

      if (this.barWidth <= 0) {
        clearInterval(this.timer);
        const alert = coreui.Alert.getOrCreateInstance(this.$el);
        alert.close()
      }
    },
    pauseTimer() {
      clearInterval(this.timer);
    },
    resumeTimer() {
      if (!this.hasTimeout  || this.timeout <= this.interval) {
        return;
      }

      this.timer = setInterval(this.updateCountdown, this.interval);
    },
  },
  computed: {
    hasTimeout() {
      return this.dismissible && this.timeout > 0;
    }
  }
}
</script>

<style scoped>
.notificationCountdownContainer {
  width: 100%;
}

.notificationCountdownBar {
  display: inline-block;
  background-color: var(--cui-alert-color);
  width: 100%;
  height: 5px;
}
</style>
