<template>
  <input
    :value="modelValue"
    type="text"
    @blur.stop="updateAmount($event.target)"
    @paste.stop="updateAmount($event.target)"
  />
</template>

<script>
  import { evaluate } from 'mathjs';
  import Decimal from 'decimal.js';
  import { __ } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';

  export default {
    name: 'MathInput',
    props: {
      modelValue: [Number, String],
      // Number of decimal places to clamp the evaluated result to (e.g. a
      // currency's generic_decimal_precision). Left unclamped when null.
      precision: {
        type: Number,
        default: null,
      },
    },
    emits: ['update:modelValue'],
    methods: {
      updateAmount: function (target) {
        let amount;
        try {
          // Make some preparations to the input value
          let input = target.value;

          // Replace commas with dots if locale has comma as decimal separator
          if (
            this.getDecimalSeparator(window.YAFFA.userSettings.locale) === ','
          ) {
            input = input.replace(/,/g, '.');
          } else {
            // Otherwise, remove commas, assuming they are thousands separators
            input = input.replace(/,/g, '');
          }

          // Remove any spaces
          input = input.replace(/\s/g, '');

          // Evaluate the input
          amount = evaluate(input);

          // If the evaluated input is empty, set the amount to null
          if (typeof amount === 'undefined') {
            amount = null;
          }

          // Clamp to the field's expected precision, using exact decimal
          // arithmetic to avoid reintroducing float drift while rounding.
          if (amount !== null && this.precision !== null) {
            amount = new Decimal(amount)
              .toDecimalPlaces(this.precision)
              .toNumber();
          }
        } catch (e) {
          // On error, leave the input value and the amount as is
          amount = this.modelValue;

          // Display a toast message
          toastHelpers.showErrorToast(
            __('Error while evaluating the input as a mathematical expression'),
          );
        }

        this.$emit('update:modelValue', amount);
      },

      getDecimalSeparator(locale) {
        const numberWithDecimalSeparator = 1.1;
        return Intl.NumberFormat(locale)
          .formatToParts(numberWithDecimalSeparator)
          .find((part) => part.type === 'decimal').value;
      },

      __,
    },
  };
</script>
