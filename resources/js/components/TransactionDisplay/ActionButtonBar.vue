<template>
    <div class="d-flex justify-content-end w-auto" dusk="action-bar">
        <button
                class="btn btn-warning ms-2"
                :disabled="skipInstanceButtonBusy"
                dusk="button-action-bar-skip"
                :title="__('Skip schedule instance')"
                v-if="controls.skip && transaction.schedule && transaction.transaction_schedule.next_date"
                @click="skipInstance"
        >
            <i class="fa fa-fw fa-fast-forward"></i>
            {{ __('Skip instance') }}
        </button>

        <a
                class="btn btn-success enter ms-2"
                dusk="button-action-bar-enter-instance"
                :href="getRoute('enter')"
                :title="__('Enter schedule instance')"
                v-if="controls.enter && transaction.schedule && transaction.transaction_schedule.next_date"
        >
            <i class="fa fa-fw fa-pencil"></i>
            {{ __('Enter instance') }}
        </a>

        <a
                class="btn btn-success ms-2"
                dusk="button-action-bar-open"
                :href="getRoute('show')"
                :title="__('View details')"
                v-if="isModal && controls.show"
        >
            <i class="fa fa-fw fa-search"></i>
            {{ __('Open') }}
        </a>

        <a v-if="controls.edit"
           :href="getRoute('edit', {callback: 'show'})"
           class="btn btn-primary ms-2"
           :title="__('Edit')"
        >
            <i class="fa fa-fw fa-edit"></i> {{ __('Edit') }}
        </a>
        <a v-if="controls.clone"
           :href="getRoute('clone')"
           class="btn btn-primary ms-2"
           :title="__('Clone')"
        >
            <i class="fa fa-fw fa-clone"></i> {{ __('Clone') }}
        </a>

        <button
                class="btn btn-secondary ms-2"
                data-coreui-dismiss="modal"
                data-coreui-target="#modal-quickview"
                dusk="button-action-bar-close"
                type="button"
                v-if="isModal"
        >
            {{ __('Close') }}
        </button>
    </div>
</template>

<script>

import * as helpers from '../../helpers';

export default {
    name: "ActionButtonBar",
    props: {
        controls: {
            type: Object,
            default: {
                show: true,
                edit: true,
                clone: true,
                skip: true,
                enter: true,
                delete: true,
            },
        },
        isModal: {
            type: Boolean,
            default: false,
        },
        transaction: {
            type: Object,
            default: {},
        },
    },
    data() {
        return {
            skipInstanceButtonBusy: false,
        };
    },
    emits: [
        'transactionUpdated'
    ],
    methods: {
        getRoute(action, additionalParams = {}) {
            const routeParams = Object.assign({transaction: this.transaction.id, action: action}, additionalParams);
            return window.route('transaction.open', routeParams);
        },
        skipInstance() {
            // Prevent double clicks
            if (this.skipInstanceButtonBusy) {
                return;
            }
            this.skipInstanceButtonBusy = true;

            let url = window.route('api.transactions.skipScheduleInstance', {transaction: this.transaction.id});
            axios.patch(url)
                .then((response) => {
                    // Notify the parent component that the transaction has been updated
                    this.$emit(
                        'transactionUpdated',
                        response.data.transaction
                    );
                })
                .catch((error) => {
                    console.error(error);
                })
                .finally(() => {
                    this.skipInstanceButtonBusy = false;
                });
        },

        /**
         * Import the translation helper function.
         */
        __: function (string, replace) {
            return helpers.__(string, replace);
        },
    }
}
</script>
