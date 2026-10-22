<template>
  <div class="d-flex justify-content-end w-auto" dusk="action-bar">
    <button
      v-if="
        controls.skip &&
        transaction.schedule &&
        transaction.transaction_schedule.next_date
      "
      class="btn btn-warning ms-2"
      :disabled="skipInstanceButtonBusy"
      dusk="button-action-bar-skip"
      :title="__('Skip schedule instance')"
      @click="skipInstance"
    >
      <i class="fa me-1 fa-fast-forward"></i>{{ __('Skip instance') }}
    </button>

    <a
      v-if="
        controls.enter &&
        transaction.schedule &&
        transaction.transaction_schedule.next_date
      "
      class="btn btn-success enter ms-2"
      dusk="button-action-bar-enter-instance"
      :href="getRoute('enter')"
      :title="__('Enter schedule instance')"
    >
      <i class="fa me-1 fa-pencil"></i>{{ __('Enter instance') }}
    </a>

    <a
      v-if="isModal && controls.show"
      class="btn btn-success ms-2"
      dusk="button-action-bar-open"
      :href="getRoute('show')"
      :title="__('View details')"
    >
      <i class="fa me-1 fa-search"></i>{{ __('Open') }}
    </a>

    <a
      v-if="controls.edit"
      :href="getRoute('edit', { callback: 'show' })"
      class="btn btn-primary ms-2"
      :title="__('Edit')"
    >
      <i class="fa me-1 fa-edit"></i>{{ __('Edit') }}
    </a>
    <a
      v-if="controls.clone"
      :href="getRoute('clone')"
      class="btn btn-primary ms-2"
      :title="__('Clone')"
    >
      <i class="fa me-1 fa-clone"></i>{{ __('Clone') }}
    </a>

    <a
      v-if="controls.clone"
      :href="getRoute('template')"
      class="btn btn-primary ms-2"
      :title="__('Save as template')"
    >
      <i class="fa me-1 fa-bookmark"></i>{{ __('Save as template') }}
    </a>

    <button
      v-if="controls.delete && !isModal"
      class="btn btn-danger ms-2"
      :disabled="deleteButtonBusy"
      dusk="button-action-bar-delete"
      type="button"
      :title="__('Delete')"
      @click="deleteTransaction"
    >
      <i class="fa me-1 fa-trash"></i>{{ __('Delete') }}
    </button>

    <button
      v-if="isModal"
      class="btn btn-secondary ms-2"
      data-coreui-dismiss="modal"
      data-coreui-target="#modal-quickview"
      dusk="button-action-bar-close"
      type="button"
    >
      {{ __('Close') }}
    </button>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { processTransaction } from '@/shared/lib/helpers';
  import { confirmDelete } from '@/shared/lib/confirm';
  import { showErrorToast } from '@/shared/lib/toast';
  import { storeNotification } from '@/shared/lib/notifications/handleNotifications';

  export default {
    name: 'ActionButtonBar',
    props: {
      controls: {
        type: Object,
        default: () => ({
          show: true,
          edit: true,
          clone: true,
          skip: true,
          enter: true,
          delete: true,
        }),
      },
      isModal: {
        type: Boolean,
        default: false,
      },
      transaction: {
        type: Object,
        default: () => ({}),
      },
    },
    emits: ['transactionUpdated'],
    data() {
      return {
        skipInstanceButtonBusy: false,
        deleteButtonBusy: false,
      };
    },
    methods: {
      __,
      getRoute(action, additionalParams = {}) {
        const routeParams = Object.assign(
          { transaction: this.transaction.id, action: action },
          additionalParams,
        );
        return this.route('transaction.open', routeParams);
      },
      // The page we came from, unless it no longer makes sense (other origin, or a page of the
      // deleted transaction itself, e.g. the edit form). Falls back to the transaction's account.
      getReturnUrl() {
        const config = this.transaction.config || {};
        const accountId =
          config.account_from_id || config.account_to_id || config.account_id;
        const ownPath = '/transactions/' + this.transaction.id + '/';

        if (
          document.referrer.startsWith(window.location.origin) &&
          !document.referrer.includes(ownPath)
        ) {
          return document.referrer;
        }

        return accountId
          ? this.route('account-entity.show', { account_entity: accountId })
          : this.route('home');
      },
      deleteTransaction() {
        if (this.deleteButtonBusy) {
          return;
        }

        confirmDelete(__('Are you sure you want to delete this transaction?'), {
          confirmButtonText: __('Delete'),
        }).then((result) => {
          if (!result.isConfirmed) {
            return;
          }
          this.deleteButtonBusy = true;
          // Computed before the request: nothing changes it, and it keeps the redirect trivial
          const returnUrl = this.getReturnUrl();

          axios
            .delete(
              this.route('api.v1.transactions.destroy', {
                transaction: this.transaction.id,
              }),
            )
            .then(() => {
              storeNotification(
                'success',
                __('Transaction deleted (#:transactionId)', {
                  transactionId: this.transaction.id,
                }),
                { dismissible: true },
              );
              location.href = returnUrl;
            })
            .catch((error) => {
              this.deleteButtonBusy = false;
              showErrorToast(
                __('Error deleting transaction (#:transactionId): :error', {
                  transactionId: this.transaction.id,
                  error: error,
                }),
              );
            });
        });
      },
      skipInstance() {
        // Prevent double clicks
        if (this.skipInstanceButtonBusy) {
          return;
        }
        this.skipInstanceButtonBusy = true;

        let url = this.route('api.v1.transactions.skip', {
          transaction: this.transaction.id,
        });
        axios
          .patch(url)
          .then((response) => {
            // Notify the parent component that the transaction has been updated. Normalized
            // here, at the single emit site, so every consumer (page container, quick-view
            // modal, any future one) gets decimal-string wire-format fields already
            // converted back to JS numbers.
            this.$emit(
              'transactionUpdated',
              processTransaction(response.data.transaction),
            );
          })
          .catch((error) => {
            console.error(error);
          })
          .finally(() => {
            this.skipInstanceButtonBusy = false;
          });
      },
    },
  };
</script>
