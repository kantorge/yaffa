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

  // Generous ceiling used only to normalize floating-point representation
  // noise from evaluate()'s native-float math (e.g. 0.1 + 0.2 evaluates to
  // 0.30000000000000004), not to enforce any field's real storage scale -
  // that's validated server-side (TransactionRequest/InvestmentPriceRequest's
  // decimal:0,<scale> rule). Set well above any decimal count a human would
  // plausibly type by hand, so a deliberately precise value (e.g.
  // 1.1111111111) is never truncated here.
  const FLOAT_NOISE_CLEANUP_SCALE = 10;

  export default {
    name: 'MathInput',
    props: {
      modelValue: [Number, String],
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

          // Normalize float-representation noise from the evaluation above,
          // using exact decimal arithmetic - see FLOAT_NOISE_CLEANUP_SCALE.
          if (amount !== null) {
            const exactAmount = new Decimal(amount);
            const roundedAmount = exactAmount.toDecimalPlaces(
              FLOAT_NOISE_CLEANUP_SCALE,
            );

            // Only genuinely over-precise input (more decimals than the
            // cleanup ceiling) reaches here - warn, since this is the one
            // case where the emitted value differs from what was typed.
            if (!roundedAmount.equals(exactAmount)) {
              toastHelpers.showWarningToast(
                __(
                  'The entered value had more decimal places than supported and was rounded.',
                ),
              );
            }

            amount = roundedAmount.toNumber();
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
