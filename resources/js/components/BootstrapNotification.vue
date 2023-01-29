<template>
    <div
        class="alert fade show"
        :class="[
            { 'alert-dismissible' : dismissible },
            'alert-' + type,
        ]"
        role="alert"
    >
        <h4
            v-if="title || icon"
            class="alert-heading"
        >
            <span v-if="icon" :class="'me-1 icon fa fa-' + icon"></span>
            <span v-html="title" class="align-text-bottom"></span>
        </h4>
        <span v-html="message"></span>
        <button v-if="dismissible" type="button" class="btn-close" data-coreui-dismiss="alert" aria-label="Close"></button>
    </div>
</template>

<script>
    export default {
        name: 'BootstrapNotification',
        components: {},
        props: {
            dismissible: {
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
