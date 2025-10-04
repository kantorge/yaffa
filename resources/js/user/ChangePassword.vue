<template>
  <div class="card" id="changePasswordForm">
    <form
      accept-charset="UTF-8"
      @submit.prevent="onSubmit"
      @keydown="form.onKeydown($event)"
      autocomplete="off"
    >
      <div class="card-header">
        <div class="card-title">
          {{ __('Change Password') }}
        </div>
      </div>
      <div class="card-body" v-if="!sandbox_mode">
        <div class="row mb-3">
          <label for="current_password" class="col-form-label col-sm-3">
            {{ __('Current Password') }}
          </label>
          <div class="col-sm-9">
            <input
              type="password"
              class="form-control"
              id="current_password"
              name="current_password"
              :placeholder="__('Current Password')"
              v-model="form.current_password"
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
              type="password"
              class="form-control"
              id="password"
              name="password"
              :placeholder="__('New Password')"
              v-model="form.password"
              required
            />
            <HasError :form="form" field="password" />
          </div>
        </div>
        <div class="row mb-3">
          <label for="password_confirmation" class="col-form-label col-sm-3">
            {{ __('Confirm password') }}
          </label>
          <div class="col-sm-9">
            <input
              type="password"
              class="form-control"
              id="password_confirmation"
              name="password_confirmation"
              :placeholder="__('Confirm new password')"
              v-model="form.password_confirmation"
              required
            />
            <HasError :form="form" field="password_confirmation" />
          </div>
        </div>
      </div>
      <div class="card-body" v-else>
        <div class="alert alert-warning">
          {{ __('You are in sandbox mode. You cannot change your password.') }}
        </div>
      </div>
      <div class="card-footer" v-if="!sandbox_mode">
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
  import * as helpers from '../helpers';
  import Form from 'vform';
  import { Button, HasError } from 'vform/src/components/bootstrap5';

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
      sandbox_mode: window.sandbox_mode,
    }),
    methods: {
      onSubmit() {
        let _vue = this;
        this.form.busy = true;

        // Make the API call
        this.form
          .patch(window.route('user.change_password'), this.form)
          .then(function () {
            _vue.showToast(
              __('Success'),
              __('Password changed successfully.'),
              'bg-success',
            );

            // Clear the form
            _vue.form.reset();
            _vue.form.successful = true;
          })
          .catch(function (error) {
            if (error.response.status === 422) {
              _vue.showToast(
                __('Error'),
                __('Validation failed. Please check the form for errors.'),
                'bg-danger',
              );
            } else {
              console.error(error);
              _vue.showToast(
                __('Error'),
                __('An error occurred. Please try again later.'),
                'bg-danger',
              );
            }
          })
          .finally(function () {
            _vue.form.busy = false;
          });
      },

      /**
       * Import the translation helper function.
       */
      __: function (string, replace) {
        return helpers.__(string, replace);
      },

      /**
       * Import the toast display helper function.
       */
      showToast: function (header, body, toastClass, otherProperties) {
        return helpers.showToast(header, body, toastClass, otherProperties);
      },
    },
  };
</script>
