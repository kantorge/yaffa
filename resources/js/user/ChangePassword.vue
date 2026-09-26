<template>
    <div id="changePasswordForm" class="card">
        <form
            accept-charset="UTF-8"
            autocomplete="off"
            @submit.prevent="onSubmit"
            @keydown="form.onKeydown($event)"
        >
            <div class="card-header">
                <div class="card-title">
                    {{ __('Change Password') }}
                </div>
            </div>
            <div v-if="!sandbox_mode" class="card-body">
                <div class="row mb-3">
                    <label
                        for="current_password"
                        class="col-form-label col-sm-3"
                    >
                        {{ __('Current Password') }}
                    </label>
                    <div class="col-sm-9">
                        <input
                            id="current_password"
                            v-model="form.current_password"
                            type="password"
                            class="form-control"
                            name="current_password"
                            :placeholder="__('Current Password')"
                            required
                        />
                        <HasError :form="form" field="current_password" />
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="password" class="col-form-label col-sm-3">
                        {{ __('New Password') }}
                    </label>
                    <div class="col-sm-9">
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="form-control"
                            name="password"
                            :placeholder="__('New Password')"
                            required
                        />
                        <HasError :form="form" field="password" />
                    </div>
                </div>
                <div class="row mb-3">
                    <label
                        for="password_confirmation"
                        class="col-form-label col-sm-3"
                    >
                        {{ __('Confirm password') }}
                    </label>
                    <div class="col-sm-9">
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="form-control"
                            name="password_confirmation"
                            :placeholder="__('Confirm new password')"
                            required
                        />
                        <HasError :form="form" field="password_confirmation" />
                    </div>
                </div>
            </div>
            <div v-else class="card-body">
                <div class="alert alert-warning">
                    {{
                        __(
                            'You are in sandbox mode. You cannot change your password.',
                        )
                    }}
                </div>
            </div>
            <div v-if="!sandbox_mode" class="card-footer">
                <Button
                    class="btn btn-primary"
                    :form="form"
                    dusk="button-change-password"
                >
                    {{ __('Change Password') }}
                </Button>
            </div>
        </form>
    </div>
</template>

<script>
    import { __ } from '@/shared/lib/i18n';
    import Form from 'vform';
    import { Button, HasError } from 'vform/src/components/bootstrap5';
    import * as toastHelpers from '@/shared/lib/toast';

    export default {
        name: 'ChangePassword',
        components: {
            Button,
            HasError,
        },
        data: () => ({
            form: new Form({
                current_password: '',
                password: '',
                password_confirmation: '',
            }),
            sandbox_mode: window.YAFFA.config.sandbox_mode,
        }),
        methods: {
            onSubmit() {
                let _vue = this;
                this.form.busy = true;

                // Make the API call
                this.form
                    .patch(this.route('api.v1.users.me.password'), this.form)
                    .then(function () {
                        toastHelpers.showSuccessToast(
                            __('Password changed successfully.'),
                        );

                        // Clear the form
                        _vue.form.reset();
                        _vue.form.successful = true;
                    })
                    .catch(function (error) {
                        if (error.response.status === 422) {
                            toastHelpers.showErrorToast(
                                __(
                                    'Validation failed. Please check the form for errors.',
                                ),
                            );
                        } else {
                            console.error(error);
                            toastHelpers.showErrorToast(
                                __(
                                    'An error occurred. Please try again later.',
                                ),
                            );
                        }
                    })
                    .finally(function () {
                        _vue.form.busy = false;
                    });
            },
            __,
        },
    };
</script>
