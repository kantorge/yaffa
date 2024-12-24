<template>
    <input
        :value="modelValue"
        type="text"
        @blur.stop="updateAmount($event.target)"
        @paste.stop="updateAmount($event.target)"
    >
</template>

<script>
    import { evaluate } from 'mathjs';

    export default {
        name: 'MathInput',
        props: ['modelValue'],
        emits: ['update:modelValue'],
        methods: {
            updateAmount: function (target) {
                let amount;
                try {
                    // Make some preparations to the input value
                    let input = target.value;

                    // Replace commas with dots if locale has comma as decimal separator
                    if (this.getDecimalSeparator(window.YAFFA.locale) === ',') {
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
                    if (typeof amount === "undefined") {
                        amount = null;
                    }
                } catch (e) {
                    // On error, leave the input value and the amount as is
                    amount = this.modelValue;

                    // Display a toast message
                    let notificationEvent = new CustomEvent('toast', {
                        detail: {
                            header: __('Error'),
                            body: __('Error while evaluating the input.'),
                            toastClass: "bg-danger",
                        }
                    });
                    window.dispatchEvent(notificationEvent);
                }

                this.$emit('update:modelValue', amount);
            },

            getDecimalSeparator(locale) {
                const numberWithDecimalSeparator = 1.1;
                return Intl.NumberFormat(locale)
                    .formatToParts(numberWithDecimalSeparator)
                    .find(part => part.type === 'decimal')
                    .value;
            }
        }
    }
</script>
