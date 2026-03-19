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
              <label :for="nameInputId" class="form-label col-sm-3">
                {{ __('Name') }}
              </label>
              <div class="col-sm-9">
                <input
                  class="form-control"
                  :id="nameInputId"
                  maxlength="255"
                  type="text"
                  v-model="form.name"
                  @keyup="onNameChange"
                />
              </div>
            </div>

            <div class="row mb-3">
              <label :for="activeInputId" class="form-label col-sm-3">
                {{ __('Active') }}
              </label>
              <div class="col-sm-9">
                <input
                  :id="activeInputId"
                  class="checkbox-inline"
                  type="checkbox"
                  value="1"
                  v-model="form.active"
                />
              </div>
            </div>

            <div class="row mb-3">
              <label :for="categorySelectId" class="form-label col-sm-3">
                {{ __('Default category') }}
              </label>
              <div class="col-sm-9">
                <select
                  :id="categorySelectId"
                  class="form-select category"
                  style="width: 100%"
                ></select>
              </div>
            </div>

            <template v-if="!simplified">
              <div class="row mb-3">
                <label :for="aliasInputId" class="form-label col-sm-3">
                  {{ __('Import alias') }}
                </label>
                <div class="col-sm-9">
                  <textarea
                    :id="aliasInputId"
                    class="form-control"
                    rows="3"
                    v-model="form.alias"
                  ></textarea>
                </div>
              </div>

              <div class="row mb-3">
                <label
                  :for="preferredCategoriesSelectId"
                  class="form-label col-sm-3"
                >
                  {{ __('Preferred categories') }}
                </label>
                <div class="col-sm-9">
                  <select
                    :id="preferredCategoriesSelectId"
                    class="form-select preferred"
                    style="width: 100%"
                    multiple="multiple"
                    :data-other-select="`#${notPreferredCategoriesSelectId}`"
                  ></select>
                </div>
              </div>

              <div class="row mb-3">
                <label
                  :for="notPreferredCategoriesSelectId"
                  class="form-label col-sm-3"
                >
                  {{ __('Excluded categories') }}
                </label>
                <div class="col-sm-9">
                  <select
                    :id="notPreferredCategoriesSelectId"
                    class="form-select not-preferred"
                    style="width: 100%"
                    multiple="multiple"
                    :data-other-select="`#${preferredCategoriesSelectId}`"
                  ></select>
                </div>
              </div>
            </template>

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
  import { initializeSelect2 } from '@/select2';
  initializeSelect2(window.YAFFA.userSettings.language);

  import Form from 'vform';
  import {
    Button,
    AlertErrors,
    AlertSuccess,
  } from 'vform/src/components/bootstrap5';

  import { __ } from '@/i18n';

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
      instanceId: {
        type: String,
        default: null,
      },
      simplified: {
        type: Boolean,
        default: false,
      },
    },

    data() {
      let data = {};

      // Main form data
      data.form = new Form({
        config_type: 'payee',
        name: '',
        active: true,
        alias: '',
        simplified: this.simplified,
        config: {
          category_id: null,
          preferred: [],
          not_preferred: [],
        },
      });

      data.similarPayees = [];
      data.payeeId = null;
      data.categorySelect = null;
      data.preferredSelect = null;
      data.notPreferredSelect = null;

      return data;
    },

    computed: {
      formInstanceId() {
        return this.instanceId || this.id;
      },
      nameInputId() {
        return `${this.formInstanceId}-name`;
      },
      activeInputId() {
        return `${this.formInstanceId}-active`;
      },
      categorySelectId() {
        return `${this.formInstanceId}-category_id`;
      },
      aliasInputId() {
        return `${this.formInstanceId}-alias`;
      },
      preferredCategoriesSelectId() {
        return `${this.formInstanceId}-preferred_categories`;
      },
      notPreferredCategoriesSelectId() {
        return `${this.formInstanceId}-not_preferred_categories`;
      },
      formUrl() {
        if (this.payeeId === null) {
          return null;
        }

        return route('api.v1.payees.update', {
          accountEntity: this.payeeId,
        });
      },
    },

    mounted() {
      this.initializeCategorySelect();

      if (!this.simplified) {
        this.initializeCategoryPreferenceSelects();
      }

      // Initialize modal
      this.modal = new coreui.Modal(document.getElementById(this.id));
    },

    methods: {
      show(payeeId = null) {
        this.resetForm();

        if (payeeId !== null) {
          // Load payee data for editing
          this.loadPayeeData(payeeId);
        }

        this.modal.show();
      },

      initializeCategorySelect() {
        this.categorySelect = $(this.$el).find(`#${this.categorySelectId}`);

        this.categorySelect
          .select2({
            theme: 'bootstrap-5',
            ajax: {
              url: '/api/v1/categories',
              dataType: 'json',
              delay: 150,
              data: function (params) {
                return {
                  _token: csrfToken,
                  q: params.term || '*',
                  withInactive: true,
                };
              },
              processResults: function (data) {
                const results = Array.isArray(data) ? data : data.data || [];

                return {
                  results: results.map(function (item) {
                    return {
                      id: item.id,
                      text: item.full_name,
                    };
                  }),
                };
              },
              cache: true,
            },
            selectOnClose: true,
            placeholder: __('Select category'),
            allowClear: true,
            dropdownParent: $('#' + this.id),
          })
          .on('change', () => {
            const selectedValue = this.categorySelect.val();

            this.form.config.category_id =
              selectedValue === null || selectedValue === ''
                ? null
                : Number(selectedValue);
          });
      },

      initializeCategoryPreferenceSelects() {
        this.preferredSelect = $(this.$el).find(
          `#${this.preferredCategoriesSelectId}`,
        );
        this.notPreferredSelect = $(this.$el).find(
          `#${this.notPreferredCategoriesSelectId}`,
        );

        const baseConfig = {
          theme: 'bootstrap-5',
          multiple: true,
          ajax: {
            url: '/api/v1/categories',
            dataType: 'json',
            delay: 150,
            data: function (params) {
              return {
                _token: csrfToken,
                q: params.term || '*',
                withInactive: true,
              };
            },
            processResults: function (data) {
              const thisSelect = $(this.$element[0]);
              const otherSelect = thisSelect
                .closest('.modal')
                .find(thisSelect.data('other-select'));
              const otherItems = otherSelect.select2('val') || [];
              const results = Array.isArray(data) ? data : data.data || [];

              return {
                results: results
                  .filter(function (item) {
                    return !otherItems.includes(item.id.toString());
                  })
                  .map(function (item) {
                    return {
                      id: item.id,
                      text: item.full_name,
                    };
                  }),
              };
            },
            cache: true,
          },
          selectOnClose: true,
          placeholder: __('Select category'),
          allowClear: true,
          width: '100%',
          dropdownParent: $('#' + this.id),
        };

        this.preferredSelect.select2(baseConfig).on('change', () => {
          this.form.config.preferred = (this.preferredSelect.val() || []).map(
            (item) => Number(item),
          );
        });

        this.notPreferredSelect.select2(baseConfig).on('change', () => {
          this.form.config.not_preferred = (
            this.notPreferredSelect.val() || []
          ).map((item) => Number(item));
        });
      },

      setSelectValue(selectElement, category) {
        if (!selectElement || !category) {
          return;
        }

        const option = new Option(category.full_name, category.id, true, true);
        selectElement.append(option).trigger('change');
      },

      setMultiSelectValues(selectElement, categories) {
        if (!selectElement) {
          return;
        }

        selectElement.empty();

        categories.forEach((category) => {
          const option = new Option(
            category.full_name,
            category.id,
            true,
            true,
          );
          selectElement.append(option);
        });

        selectElement.trigger('change');
      },

      loadPayeeData(payeeId) {
        this.payeeId = payeeId;

        // Fetch payee data from API
        fetch(route('api.v1.payees.show', { accountEntity: payeeId }))
          .then((response) => {
            if (!response.ok) {
              throw new Error('Failed to load payee data');
            }
            return response.json();
          })
          .then((data) => {
            this.form.name = data.name;
            this.form.active = Boolean(data.active);
            this.form.alias = data.alias || '';
            this.form.config.category_id = data.config?.category_id || null;
            this.form.config.preferred = (data.preferred_categories || []).map(
              (category) => Number(category.id),
            );
            this.form.config.not_preferred = (
              data.deferred_categories || []
            ).map((category) => Number(category.id));

            // Update Select2 with the current category
            this.categorySelect.empty();

            if (data.config?.category) {
              this.setSelectValue(this.categorySelect, data.config.category);
            } else {
              this.categorySelect.val(null).trigger('change');
            }

            if (!this.simplified) {
              this.setMultiSelectValues(
                this.preferredSelect,
                data.preferred_categories || [],
              );
              this.setMultiSelectValues(
                this.notPreferredSelect,
                data.deferred_categories || [],
              );
            }
          })
          .catch((error) => {
            console.error('Error loading payee:', error);
            this.form.errors.set({
              general: __('Failed to load payee data'),
            });
          });
      },

      resetForm() {
        this.form.reset();
        this.form.errors.clear();

        this.form.active = true;
        this.form.alias = '';
        this.form.config.category_id = null;
        this.form.config.preferred = [];
        this.form.config.not_preferred = [];

        if (this.categorySelect) {
          this.categorySelect.empty().val(null).trigger('change');
        }

        if (!this.simplified) {
          if (this.preferredSelect) {
            this.preferredSelect.empty().trigger('change');
          }
          if (this.notPreferredSelect) {
            this.notPreferredSelect.empty().trigger('change');
          }
        }

        // Reset payee ID
        this.payeeId = null;

        // Reset list of similar payees
        this.similarPayees = [];
      },

      onNameChange(event) {
        const query = event.target.value?.trim();

        if (!query) {
          this.similarPayees = [];
          return;
        }

        // Get similar payees from API
        fetch('/api/v1/payees/similar?query=' + encodeURIComponent(query))
          .then((response) => {
            if (!response.ok) {
              throw new Error('Failed to fetch similar payees');
            }
            return response.json();
          })
          .then((data) => {
            this.similarPayees = data;
          })
          .catch((error) => {
            console.error('Error fetching similar payees:', error);
            this.similarPayees = [];
          });
      },

      onSelectPayee(payee) {
        // If payee is inactive, activate it before adding it to form
        if (!payee.active) {
          window.axios
            .patch(
              route('api.v1.account-entities.patch-active', {
                accountEntity: payee.id,
              }),
              { active: true },
            )
            .then((response) => this.processAfterSubmit(response));
        } else {
          this.hideAndReset();

          // Let parent know about the new item
          this.$emit('payeeSelected', payee);
        }
      },

      processAfterSubmit(response) {
        setTimeout(() => this.hideAndReset(), 1000);

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
            .post(route('api.v1.payees.store'), this.form)
            .then((response) => this.processAfterSubmit(response));
        } else {
          if (this.formUrl === null) {
            this.form.errors.set({
              general: __('Failed to determine payee update endpoint'),
            });

            return;
          }

          this.form
            .patch(this.formUrl, this.form)
            .then((response) => this.processAfterSubmit(response));
        }
      },
      __,
    },
  };
</script>
