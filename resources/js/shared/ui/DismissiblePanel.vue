<template>
  <component :is="tag" class="position-relative">
    <button
      type="button"
      class="btn-close position-absolute top-0 end-0 m-2"
      :aria-label="__('Dismiss this suggestion')"
      :title="__('Dismiss this suggestion')"
      @click.stop.prevent="$emit('dismiss')"
    ></button>

    <slot></slot>
  </component>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  /**
   * Shared corner-X dismiss wrapper for suggestion/candidate cards (T-08).
   * Renders the given `tag` (a plain card `<div>`, or an `<a>` when the
   * whole card is a link) with a position-relative wrapper and the
   * standard corner-X dismiss button, and emits a bare `dismiss` event —
   * the caller (typically inside a v-for) maps that to the specific
   * candidate id before re-emitting further up.
   *
   * Attribute fallthrough is left at its Vue default (inheritAttrs: true),
   * so a caller's class/style/href/target/etc. land on the single root
   * <component> element as normal — no manual $attrs handling needed here.
   */
  export default {
    name: 'DismissiblePanel',

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
