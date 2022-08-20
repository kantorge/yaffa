<template>
    <div
        class="alert"
        :class="[
            dismissable ? 'alert-dismissable' : '',
            'alert-' + type,
        ]"
    >
        <button v-if="dismissable" type="button" class="close" data-dismiss="alert" aria-hidden="true">X</button>
        <h4
            v-if="title || icon"
            class="alert-heading"
        >
            <span v-if="icon" :class="'icon fa fa-' + icon"></span>
            <span v-html="title"></span>
        </h4>
        <span v-html="message"></span>
    </div>
</template>

<script>
    export default {
        name: 'BootstrapNotification',
        components: {},
        props: {
            dismissable: {
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
        },
    }
</script>
