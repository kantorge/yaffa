<template>
  <div class="modal" tabindex="-1" :id="id">
    <div class="modal-dialog">
      <div class="modal-content">
        <form
          accept-charset="UTF-8"
          @submit.prevent="onSubmit"
          autocomplete="off"
        >
          <div class="modal-header">
            <h5 class="modal-title" v-if="action == 'new'">
              {{ __('Add new payee') }}
            </h5>
            <h5 class="modal-title" v-if="action == 'edit'">
              {{ __('Edit payee') }}
            </h5>
            <button
              type="button"
              class="btn-close"
              data-coreui-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <AlertErrors
              :form="form"
              :message="__('There were some problems with your input.')"
            />
            <AlertSuccess
              :form="form"
              :message="__('Your changes have been saved!')"
            />

            <div class="row mb-3">
              <label for="name" class="form-label col-sm-3">
                {{ __('Name') }}
              </label>
              <div class="col-sm-9">
                <input
                  class="form-control"
                  id="name"
                  maxlength="255"
                  type="text"
                  v-model="form.name"
                  @keyup="onNameChange"
                />
              </div>
            </div>

            <div class="row mb-3">
              <label for="active" class="form-label col-sm-3">
                {{ __('Active') }}
              </label>
              <div class="col-sm-9">
                <input
                  id="active"
                  class="checkbox-inline"
                  type="checkbox"
                  value="1"
                  v-model="form.active"
                />
              </div>
            </div>

            <div class="row mb-3">
              <label for="category_id" class="form-label col-sm-3">
                {{ __('Default category') }}
              </label>
              <div class="col-sm-9">
                <select
                  id="category_id"
                  class="form-select category"
                  style="width: 100%"
                  v-model.number="form.config.category_id"
                ></select>
              </div>
            </div>
            <div class="row mb-3" v-show="similarPayees.length > 0">
              <hr />
              <span class="form-label col-sm-3">
                {{ __('Are you looking for any of these payees?') }}
              </span>
              <div class="col-sm-9">
                <ul class="list-unstyled" id="similar-payee-list">
                  <li
                    class="mt-2"
                    v-for="payee in similarPayees"
                    :key="payee.id"
                    :data-id="payee.id"
                  >
                    <a href="#" @click.prevent="onSelectPayee(payee)">
                      {{ payee.name }}
                      <span v-if="!payee.active">({{ __('inactive') }})</span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-default"
              data-coreui-dismiss="modal"
            >
              {{ __('Close') }}
            </button>
            <Button
              class="btn btn-primary"
              :disabled="form.busy"
              :form="form"
              >{{ __('Save') }}</Button
            >
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
  import { loadSelect2Language } from '../helpers';
  import select2 from 'select2';
  select2();
  loadSelect2Language(window.YAFFA.language);

  import Form from 'vform';
  import {
    Button,
    AlertErrors,
    AlertSuccess,
  } from 'vform/src/components/bootstrap5';

  export default {
    components: {
      Button,
      AlertErrors,
      AlertSuccess,
    },

    props: {
      action: String,
      payee: Object,
      id: {
        type: String,
        default: 'newPayeeModal',
      },
    },

    data() {
      let data = {};

      // Main form data
      data.form = new Form({
        config_type: 'payee',
        name: '',
        active: true,
        config: {
          category_id: '',
        },
      });

      data.similarPayees = [];

      return data;
    },

    mounted() {
      // Add select2 functionality to category
      let elementCategory = $(this.$el).find('select.category');

      elementCategory
        .select2({
          ajax: {
            url: '/api/assets/category',
            dataType: 'json',
            delay: 150,
            data: function (params) {
              return {
                q: params.term,
              };
            },
            processResults: function (data) {
              return {
                results: data,
              };
            },
            cache: true,
          },
          selectOnClose: true,
          placeholder: __('Select category'),
          allowClear: true,
          dropdownParent: $('#' + this.id),
        })
        .on('select2:select', function (e) {
          const event = new Event('change', {
            bubbles: true,
            cancelable: true,
          });
          e.target.dispatchEvent(event);
        });

      // Initialize modal
      this.modal = new coreui.Modal(document.getElementById(this.id));
    },

    methods: {
      show() {
        this.modal.show();
      },

      resetForm() {
        // Clear form data
        this.form.name = '';
        this.form.active = true;
        this.form.config.category_id = '';
        $(this.$el).find('select.category').val('').trigger('change');

        // Reset list of similar payees
        this.similarPayees = [];

        // Reset Form status
        this.form.reset();
        this.form.successful = false;
      },

      onNameChange(event) {
        // Get similar payees from API
        fetch('/api/assets/payee/similar?query=' + event.target.value)
          .then((response) => response.json())
          .then((data) => {
            this.similarPayees = data;
          });
      },

      onSelectPayee(payee) {
        // If payee is inactive, activate it before adding it to form
        if (!payee.active) {
          this.form
            .put(
              route('api.accountentity.updateActive', {
                accountEntity: payee.id,
                active: 1,
              }),
            )
            .then((response) => this.processAfterSubmit(response));
        } else {
          this.hideAndReset(this);

          // Let parent know about the new item
          this.$emit('payeeSelected', payee);
        }
      },

      processAfterSubmit(response) {
        setTimeout(this.hideAndReset, 1000);

        // Let parent know about the new item
        this.$emit('payeeSelected', response.data);
      },

      hideAndReset() {
        this.resetForm();
        this.modal.hide();
      },

      onSubmit() {
        if (this.action === 'new') {
          this.form
            .post(route('api.payee.store'), this.form)
            .then((response) => this.processAfterSubmit(response));
        } else {
          this.form
            .patch(this.formUrl, this.form)
            .then((response) => this.processAfterSubmit(response));
        }
      },
    },
  };
</script>
