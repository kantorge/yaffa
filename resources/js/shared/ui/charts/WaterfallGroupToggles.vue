<template>
  <div
    class="btn-group"
    role="group"
    :aria-label="__('waterfall.groups.label')"
  >
    <template v-for="group in groups" :key="group.value">
      <input
        :id="`${uid}_${group.value}`"
        type="checkbox"
        class="btn-check"
        autocomplete="off"
        :checked="modelValue.includes(group.value)"
        :disabled="disabled"
        @change="toggle(group.value)"
      />
      <label
        class="btn btn-sm btn-outline-primary"
        :for="`${uid}_${group.value}`"
      >
        {{ group.label }}
      </label>
    </template>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  let nextId = 0;

  export default {
    name: 'WaterfallGroupToggles',
    props: {
      // Selected group keys: any of 'standard', 'income', 'trades'
      modelValue: {
        type: Array,
        required: true,
      },
      disabled: {
        type: Boolean,
        default: false,
      },
    },
    emits: ['update:modelValue'],
    data() {
      return { uid: `waterfallGroup${nextId++}` };
    },
    computed: {
      groups() {
        return [
          { value: 'standard', label: __('waterfall.groups.standard') },
          { value: 'income', label: __('waterfall.groups.income') },
          { value: 'trades', label: __('waterfall.groups.trades') },
        ];
      },
    },
    methods: {
      toggle(value) {
        const selected = this.modelValue.includes(value)
          ? this.modelValue.filter((v) => v !== value)
          : [...this.modelValue, value];

        // At least one group must stay selected; re-render to restore the checkbox
        if (selected.length === 0) {
          this.$forceUpdate();
          return;
        }

        this.$emit('update:modelValue', selected);
      },
      __,
    },
  };
</script>
