<template>
  <div class="card" id="twoFactorSettings">
    <div class="card-header">
      <div class="card-title mb-0">{{ __('Two-Factor Authentication') }}</div>
    </div>
    <div class="card-body" v-if="sandbox_mode">
      <div class="alert alert-warning mb-0">
        {{ __('You are in sandbox mode. You cannot enable two-factor authentication.') }}
      </div>
    </div>
    <div class="card-body" v-else>
      <div v-if="loading" class="text-muted">
        {{ __('Loading two-factor authentication status...') }}
      </div>

      <div
        v-else-if="loadError"
        class="alert alert-danger mb-0"
        role="alert"
      >
        {{ __('Unable to load two-factor authentication status.') }}
      </div>

      <template v-else>
        <div v-if="enabled" class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <span class="badge text-bg-success me-2">
              <i class="fa fa-check-circle"></i>
              {{ __('Enabled') }}
            </span>
            <span class="text-muted">
              {{ __('Your account is protected with an authenticator app.') }}
            </span>
          </div>
          <div class="d-flex gap-2">
            <button
              type="button"
              class="btn btn-outline-secondary btn-sm"
              :disabled="busy"
              dusk="button-regenerate-recovery-codes"
              @click="regenerateRecoveryCodes"
            >
              {{ __('Regenerate recovery codes') }}
            </button>
            <button
              type="button"
              class="btn btn-outline-danger btn-sm"
              :disabled="busy"
              dusk="button-disable-2fa"
              @click="disableTwoFactor"
            >
              {{ __('Disable') }}
            </button>
          </div>
        </div>

        <div v-else>
          <p class="text-muted">
            {{
              __(
                'Add an extra layer of security to your account by requiring a code from an authenticator app when you log in.',
              )
            }}
          </p>
          <button
            type="button"
            class="btn btn-primary"
            dusk="button-enable-2fa"
            @click="openEnrollModal"
          >
            <i class="fa fa-shield-alt"></i>
            {{ __('Enable two-factor authentication') }}
          </button>
        </div>

        <!-- Recovery codes, shown once after (re)generation -->
        <div v-if="revealedRecoveryCodes" class="mt-3">
          <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle me-1"></i>
            {{
              __(
                'Save these recovery codes somewhere safe. Each one can be used once to log in if you lose access to your authenticator app. They will not be shown again.',
              )
            }}
          </div>
          <div class="input-group mb-2">
            <textarea
              class="form-control font-monospace"
              readonly
              rows="4"
              dusk="recovery-codes-value"
              :value="revealedRecoveryCodes.join('\n')"
            ></textarea>
            <button type="button" class="btn btn-outline-secondary" @click="copyRecoveryCodes">
              <i class="fa fa-copy"></i>
              {{ __('Copy') }}
            </button>
          </div>
          <div class="form-check">
            <input
              id="recoveryCodesAcknowledge"
              v-model="recoveryCodesAcknowledged"
              class="form-check-input"
              type="checkbox"
            />
            <label class="form-check-label" for="recoveryCodesAcknowledge">
              {{ __('I have saved these recovery codes.') }}
            </label>
          </div>
          <button
            type="button"
            class="btn btn-primary btn-sm mt-2"
            :disabled="!recoveryCodesAcknowledged"
            @click="dismissRecoveryCodes"
          >
            {{ __('Done') }}
          </button>
        </div>
      </template>
    </div>

    <!-- Enrollment Modal -->
    <div
      id="twoFactorEnrollModal"
      ref="enrollModalEl"
      class="modal fade"
      tabindex="-1"
      aria-labelledby="twoFactorEnrollModalLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="twoFactorEnrollModalLabel" class="modal-title">
              {{ __('Enable two-factor authentication') }}
            </h5>
            <button
              type="button"
              class="btn-close"
              data-coreui-dismiss="modal"
              aria-label="Close"
              @click="resetEnrollment"
            ></button>
          </div>
          <div class="modal-body">
            <div v-if="enrolling" class="text-muted">
              {{ __('Generating your secret key...') }}
            </div>

            <div v-else-if="enrollment">
              <p>
                {{ __('Scan this QR code with your authenticator app (e.g. Google Authenticator, Authy).') }}
              </p>
              <div class="text-center mb-3" v-html="enrollment.qr_svg"></div>
              <p class="text-muted small">
                {{ __('Or enter this code manually:') }}
                <code>{{ enrollment.secret }}</code>
              </p>

              <form @submit.prevent="confirmEnrollment">
                <label for="twoFactorConfirmCode" class="form-label">
                  {{ __('Enter the 6-digit code from your app to confirm') }}
                </label>
                <input
                  id="twoFactorConfirmCode"
                  v-model="confirmCode"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': confirmError }"
                  autocomplete="one-time-code"
                  required
                />
                <div v-if="confirmError" class="invalid-feedback">
                  {{ confirmError }}
                </div>
              </form>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-coreui-dismiss="modal"
              @click="resetEnrollment"
            >
              {{ __('Cancel') }}
            </button>
            <button
              v-if="enrollment"
              type="button"
              class="btn btn-primary"
              :disabled="confirming || !confirmCode"
              @click="confirmEnrollment"
            >
              <i :class="['fa', confirming ? 'fa-spinner fa-spin' : 'fa-check']"></i>
              {{ __('Confirm') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import Swal from 'sweetalert2';

  export default {
    name: 'TwoFactorSettings',
    data: () => ({
      loading: false,
      loadError: false,
      enabled: false,
      busy: false,
      enrollModal: null,
      enrolling: false,
      enrollment: null,
      confirmCode: '',
      confirming: false,
      confirmError: null,
      revealedRecoveryCodes: null,
      recoveryCodesAcknowledged: false,
      sandbox_mode: window.YAFFA.config.sandbox_mode,
    }),
    mounted() {
      if (this.sandbox_mode) {
        return;
      }

      this.loadStatus();

      this.$nextTick(() => {
        const el = this.$refs.enrollModalEl;
        if (el) {
          this.enrollModal = new window.coreui.Modal(el);
        }
      });
    },
    methods: {
      async loadStatus() {
        this.loading = true;
        this.loadError = false;

        try {
          const response = await axios.get(this.route('api.v1.users.me.two-factor.show'));
          this.enabled = Boolean(response.data?.enabled);
        } catch (error) {
          console.error(error);
          this.loadError = true;
          toastHelpers.showErrorToast(__('Unable to load two-factor authentication status.'));
        } finally {
          this.loading = false;
        }
      },
      async openEnrollModal() {
        if (this.enrollModal) {
          this.enrollModal.show();
        }

        this.enrolling = true;
        this.enrollment = null;

        try {
          const response = await axios.post(this.route('api.v1.users.me.two-factor.enroll'));
          this.enrollment = response.data;
        } catch (error) {
          console.error(error);
          toastHelpers.showErrorToast(__('Unable to start two-factor enrollment.'));
          if (this.enrollModal) {
            this.enrollModal.hide();
          }
        } finally {
          this.enrolling = false;
        }
      },
      resetEnrollment() {
        this.enrollment = null;
        this.confirmCode = '';
        this.confirmError = null;
        this.confirming = false;
      },
      async confirmEnrollment() {
        if (this.confirming || !this.confirmCode) {
          return;
        }

        this.confirming = true;
        this.confirmError = null;

        try {
          const response = await axios.post(this.route('api.v1.users.me.two-factor.confirm'), {
            code: this.confirmCode,
          });

          this.enabled = true;
          this.revealedRecoveryCodes = response.data.recovery_codes || [];
          this.recoveryCodesAcknowledged = false;

          if (this.enrollModal) {
            this.enrollModal.hide();
          }
          this.resetEnrollment();
          toastHelpers.showSuccessToast(__('Two-factor authentication enabled.'));
        } catch (error) {
          if (error.response?.status === 422) {
            this.confirmError =
              error.response.data.errors?.code?.[0] ||
              error.response.data.message ||
              __('The provided code is invalid.');
          } else {
            console.error(error);
            toastHelpers.showErrorToast(__('Unable to confirm two-factor authentication.'));
          }
        } finally {
          this.confirming = false;
        }
      },
      async copyRecoveryCodes() {
        if (!this.revealedRecoveryCodes) {
          return;
        }

        try {
          await navigator.clipboard.writeText(this.revealedRecoveryCodes.join('\n'));
          toastHelpers.showSuccessToast(__('Recovery codes copied to clipboard.'));
        } catch (error) {
          console.error(error);
          toastHelpers.showErrorToast(__('Unable to copy the recovery codes automatically. Please copy them manually.'));
        }
      },
      dismissRecoveryCodes() {
        this.revealedRecoveryCodes = null;
        this.recoveryCodesAcknowledged = false;
      },
      async promptForPassword(title, confirmButtonText) {
        const { value: password, isConfirmed } = await Swal.fire({
          title,
          input: 'password',
          inputLabel: __('Enter your password to confirm'),
          inputAttributes: { autocomplete: 'current-password' },
          showCancelButton: true,
          confirmButtonText,
          cancelButtonText: __('Cancel'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
          },
          inputValidator: (value) => (!value ? __('Password is required.') : undefined),
        });

        return isConfirmed ? password : null;
      },
      async disableTwoFactor() {
        const password = await this.promptForPassword(
          __('Disable two-factor authentication?'),
          __('Disable'),
        );

        if (!password) {
          return;
        }

        this.busy = true;

        try {
          await axios.post(this.route('api.v1.users.me.two-factor.disable'), { password });
          this.enabled = false;
          toastHelpers.showSuccessToast(__('Two-factor authentication disabled.'));
        } catch (error) {
          if (error.response?.status === 422) {
            toastHelpers.showErrorToast(
              error.response.data.errors?.password?.[0] ||
                __('Incorrect password.'),
            );
          } else {
            console.error(error);
            toastHelpers.showErrorToast(__('Unable to disable two-factor authentication.'));
          }
        } finally {
          this.busy = false;
        }
      },
      async regenerateRecoveryCodes() {
        const password = await this.promptForPassword(
          __('Regenerate recovery codes?'),
          __('Regenerate'),
        );

        if (!password) {
          return;
        }

        this.busy = true;

        try {
          const response = await axios.post(
            this.route('api.v1.users.me.two-factor.recovery-codes'),
            { password },
          );
          this.revealedRecoveryCodes = response.data.recovery_codes || [];
          this.recoveryCodesAcknowledged = false;
        } catch (error) {
          if (error.response?.status === 422) {
            toastHelpers.showErrorToast(
              error.response.data.errors?.password?.[0] ||
                __('Incorrect password.'),
            );
          } else {
            console.error(error);
            toastHelpers.showErrorToast(__('Unable to regenerate recovery codes.'));
          }
        } finally {
          this.busy = false;
        }
      },
      __,
    },
  };
</script>
