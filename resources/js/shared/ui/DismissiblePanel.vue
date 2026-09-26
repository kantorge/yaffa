<template>
  <div class="position-relative">
    <button
      type="button"
      class="btn-close position-absolute top-0 end-0 m-2"
      :aria-label="__('Dismiss this suggestion')"
      :title="__('Dismiss this suggestion')"
      @click.stop.prevent="$emit('dismiss')"
    ></button>

    <component :is="tag" v-bind="$attrs">
      <slot></slot>
    </component>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  /**
   * Shared corner-X dismiss wrapper for suggestion/candidate cards (T-08).
   * Renders the given `tag` (a plain card `<div>`, or an `<a>` when the
   * whole card is a link) alongside the standard corner-X dismiss button,
   * and emits a bare `dismiss` event — the caller (typically inside a
   * v-for) maps that to the specific candidate id before re-emitting
   * further up.
   *
   * The dismiss button is a sibling of the `tag` element, not nested
   * inside it: nesting the button inside an `<a>` (as `tag="a"` callers
   * do) would put an interactive element inside another interactive
   * element, which is invalid HTML. inheritAttrs is disabled and the
   * caller's attrs ($attrs) are bound explicitly onto the `tag` element
   * instead of the component root.
   */
  export default {
    name: 'DismissiblePanel',

    inheritAttrs: false,

    props: {
      tag: {
        type: String,
        default: 'div',
      },
    },

    emits: ['dismiss'],

    methods: {
      __,
    },
  };
</script>
