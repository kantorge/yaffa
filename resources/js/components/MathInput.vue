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
                    amount = evaluate(target.value.replace(/\s/g,""));
                    // If the input is empty, set the amount to null
                    if (typeof amount === "undefined") {
                        amount = null;
                    }
                } catch (e) {
                    // On error, set the amount to null and clear the input
                    target.value = '';
                    amount = null;
                }

                this.$emit('update:modelValue', amount);
            },
        }
    }
</script>
