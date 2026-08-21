<template>
  <div class="modal" tabindex="-1" :id="id">
    <div class="modal-dialog" :class="dialogSizeClass">
      <div class="modal-content">
        <form
          accept-charset="UTF-8"
          @submit.prevent="$emit('submit')"
          autocomplete="off"
        >
          <div class="modal-header">
            <h5 class="modal-title" v-if="action === 'new'">
              {{ newTitle }}
            </h5>
            <h5 class="modal-title" v-else>
              {{ editTitle }}
            </h5>
            <button
              type="button"
              class="btn-close"
              data-coreui-dismiss="modal"
              :aria-label="__('Close')"
            ></button>
          </div>
          <div class="modal-body">
            <AlertErrors :form="form" :message="errorMessage" />
            <AlertSuccess :form="form" :message="successMessage" />

            <slot></slot>
          </div>
          <div class="modal-footer">
            <slot name="footer"></slot>
            <button
              type="button"
              class="btn btn-secondary"
              data-coreui-dismiss="modal"
            >
              {{ __('Cancel') }}
            </button>
            <Button class="btn btn-primary" :disabled="form.busy" :form="form">
              {{ __('Save') }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
  import {
    Button,
    AlertErrors,
    AlertSuccess,
  } from 'vform/src/components/bootstrap5';

  import { __ } from '@/shared/lib/i18n';
  import { confirmAction } from '@/shared/lib/confirm';

  /**
   * Shared modal-form skeleton: header/body/footer layout, the "new"/"edit"
   * title-toggle idiom, vform's AlertErrors/AlertSuccess, and (T-01) a
   * dirty-check confirm before the modal is dismissed with unsaved changes.
   *
   * Consumers fill in their own fields via the default slot, and may add
   * extra footer buttons (beyond the standard Cancel/Save) via the
   * `footer` slot. Submission is left to the consumer: this component only
   * emits `submit` (from the wrapped <form>'s @submit.prevent) and renders
   * the Save button bound to the given vform `form` instance.
   */
  export default {
    name: 'FormModal',

    components: {
      Button,
      AlertErrors,
      AlertSuccess,
    },

    props: {
      id: {
        type: String,
        required: true,
      },
      // Optional Bootstrap modal-dialog size suffix, e.g. "lg".
      size: {
        type: String,
        default: null,
      },
      action: {
        type: String,
        default: 'new',
      },
      newTitle: {
        type: String,
        required: true,
      },
      editTitle: {
        type: String,
        required: true,
      },
      // The vform Form instance driving this modal's fields, errors, and
      // busy state.
      form: {
        type: Object,
        required: true,
      },
      errorMessage: {
        type: String,
        default: () => __('There were some problems with your input.'),
      },
      successMessage: {
        type: String,
        default: () => __('Your changes have been saved!'),
      },
    },

    emits: ['submit'],

    data() {
      return {
        modal: null,
        modalEl: null,
        // Set right before a programmatic hide() so the hide.coreui.modal
        // listener lets it through once without re-running the dirty check.
        forceClose: false,
      };
    },

    computed: {
      dialogSizeClass() {
        return this.size ? `modal-${this.size}` : null;
      },
    },

    mounted() {
      this.modalEl = document.getElementById(this.id);
      this.modal = new coreui.Modal(this.modalEl);
      this.modalEl.addEventListener('hide.coreui.modal', this.onHide);
    },

    beforeUnmount() {
      if (this.modalEl) {
        this.modalEl.removeEventListener('hide.coreui.modal', this.onHide);
      }
    },

    methods: {
      show() {
        this.modal.show();
      },

      hide() {
        this.forceClose = true;
        this.modal.hide();
      },

      isDirty() {
        return (
          JSON.stringify(this.form.data()) !==
          JSON.stringify(this.form.originalData)
        );
      },

      onHide(event) {
        if (this.forceClose) {
          this.forceClose = false;
          return;
        }

        if (!this.isDirty()) {
          return;
        }

        event.preventDefault();

        confirmAction(__('Are you sure you want to discard any changes?'), {
          icon: 'warning',
          confirmButtonText: __('Discard changes'),
        }).then((result) => {
          if (result.isConfirmed) {
            this.form.reset();
            this.hide();
          }
        });
      },

      __,
    },
  };
</script>
