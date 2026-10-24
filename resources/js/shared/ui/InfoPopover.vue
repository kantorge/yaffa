<template>
  <i
    ref="icon"
    class="fa fa-info-circle text-info ms-1"
    tabindex="0"
    role="img"
    :aria-label="content"
  ></i>
</template>

<script>
  export default {
    name: 'InfoPopover',
    props: {
      content: { type: String, required: true },
    },
    mounted() {
      this.popover = new window.bootstrap.Popover(this.$refs.icon, {
        content: this.content,
        trigger: 'hover focus',
        placement: 'top',
        container: 'body',
        customClass: 'info-popover',
      });
    },
    beforeUnmount() {
      this.popover?.dispose();
    },
  };
</script>

<!-- Not scoped: the popover is appended to the body. Inverted colors, same as tooltips. -->
<style>
  .info-popover {
    --cui-popover-bg: var(--cui-emphasis-color);
    --cui-popover-body-color: var(--cui-body-bg);
    --cui-popover-border-color: var(--cui-emphasis-color);
    --cui-popover-arrow-border: var(--cui-emphasis-color);
  }
</style>
