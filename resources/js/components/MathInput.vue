<template>
    <input
        :value="modelValue"
        type="text"
        @blur.stop="updateAmount($event.target)"
    >
</template>

<script>
    let math = require("mathjs");
    export default {
        name: 'MathInput',
        props: ['modelValue'],

        methods: {
            updateAmount: function (target) {
                let amount = math.evaluate(target.value.replace(/\s/g,""));

                //TODO: make this optional or customizable
                if(amount <= 0) throw Error("Positive number expected");

                this.$emit('update:modelValue', amount);
            },
        }
    }
</script>
