<template>
  <FormModal
    :id="id"
    ref="formModal"
    :action="action"
    :new-title="__('Add new payee')"
    :edit-title="__('Edit payee')"
    :form="form"
    @submit="onSubmit"
  >
    <div class="row mb-3">
      <label :for="nameInputId" class="form-label col-sm-3">
        {{ __('Name') }}
      </label>
      <div class="col-sm-9">
        <input
          :id="nameInputId"
          v-model="form.name"
          class="form-control"
          maxlength="255"
          type="text"
          @keyup="onNameChange"
        />
        <HasError field="name" :form="form" />
      </div>
    </div>

    <div class="row mb-3">
      <label :for="activeInputId" class="form-label col-sm-3">
        {{ __('Active') }}
      </label>
      <div class="col-sm-9">
        <input
          :id="activeInputId"
          v-model="form.active"
          class="checkbox-inline"
          type="checkbox"
          value="1"
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
            v-model="form.alias"
            class="form-control"
            rows="3"
          ></textarea>
          <HasError field="alias" :form="form" />
          <small class="form-text text-muted">
            {{
              __(
                'One name per line. A payee text that starts with an alias, or equals it, is matched to this payee.',
              )
            }}
          </small>
        </div>
      </div>

      <div class="row mb-3">
        <label :for="autoRecordPolicyId" class="form-label col-sm-3">
          {{ __('Auto-recording') }}
        </label>
        <div class="col-sm-9">
          <select
            :id="autoRecordPolicyId"
            v-model="form.config.auto_record_policy"
            class="form-select"
          >
            <option value="follow_global">
              {{ __('Follow the global setting') }}
            </option>
            <option value="always">{{ __('Always allow') }}</option>
            <option value="never">{{ __('Never') }}</option>
          </select>
          <small class="form-text text-muted">
            {{
              __(
                'Whether documents of this payee may be recorded without your review. Nothing is recorded automatically yet.',
              )
            }}
          </small>
        </div>
      </div>

      <div class="row mb-3">
        <label :for="itemizationExpectedId" class="form-label col-sm-3">
          {{ __('Itemization') }}
        </label>
        <div class="col-sm-9">
          <select
            :id="itemizationExpectedId"
            v-model="form.config.itemization_expected"
            class="form-select"
          >
            <option :value="false">{{ __('Summary is sufficient') }}</option>
            <option :value="true">{{ __('Itemization expected') }}</option>
          </select>
          <small class="form-text text-muted">
            {{
              __(
                'Choose itemization expected for payees where you record the individual items of a receipt, such as a grocery store.',
              )
            }}
          </small>
        </div>
      </div>

      <div class="row mb-3">
        <label :for="preferredCategoriesSelectId" class="form-label col-sm-3">
          {{ __('Preferred categories') }}
        </label>
        <div class="col-sm-9">
          <select
            :id="preferredCategoriesSelectId"
            class="form-select preferred"
            style="width: 100%"
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
          ></select>
        </div>
      </div>
    </template>

    <div v-show="similarPayees.length > 0" class="row mb-3">
      <hr />
      <span class="form-label col-sm-3">
        {{ __('Are you looking for any of these payees?') }}
        <small class="d-block text-muted">
          {{ __('Click the payee to view and activate.') }}
        </small>
      </span>
      <div class="col-sm-9">
        <ul id="similar-payee-list" class="list-unstyled">
          <li
            v-for="similarPayee in similarPayees"
            :key="similarPayee.id"
            class="mt-2"
            :data-id="similarPayee.id"
          >
            <a href="#" @click.prevent="onSelectPayee(similarPayee)">{{
              similarPayee.name
            }}</a>
            <span v-if="!similarPayee.active" class="text-muted">
              ({{ __('inactive') }})
            </span>
          </li>
        </ul>
      </div>
    </div>
  </FormModal>
</template>

<script>
  import { markRaw } from 'vue';
  import Form from 'vform';
  import { HasError } from 'vform/src/components/bootstrap5';

  import FormModal from '@/shared/ui/FormModal.vue';
  import { __ } from '@/shared/lib/i18n';
  import { showErrorToast } from '@/shared/lib/toast';
  import {
    createRemoteSelect,
    setSelected,
    clearSelect,
  } from '@/shared/lib/tom-select';

  export default {
    components: {
      FormModal,
      HasError,
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

    emits: ['payeeSelected'],

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
          auto_record_policy: 'follow_global',
          itemization_expected: false,
        },
      });

      data.similarPayees = [];
      data.payeeId = null;
      data.categorySelect = null;
      data.preferredSelect = null;
      data.notPreferredSelect = null;
      data.similarPayeesDebounceTimeout = null;
      data.similarPayeesRequestId = 0;
      data.payeeRequestId = 0;

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
      autoRecordPolicyId() {
        return `${this.formInstanceId}-auto_record_policy`;
      },
      itemizationExpectedId() {
        return `${this.formInstanceId}-itemization_expected`;
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
    },

    beforeUnmount() {
      this.categorySelect?.destroy();
      this.preferredSelect?.destroy();
      this.notPreferredSelect?.destroy();

      if (this.similarPayeesDebounceTimeout) {
        clearTimeout(this.similarPayeesDebounceTimeout);
      }
    },

    methods: {
      show(payeeId = null) {
        this.resetForm();

        if (payeeId === null) {
          this.$refs.formModal.show();
          return;
        }

        // Open the edit modal only once the payee has loaded, so an unknown or
        // inaccessible ID never leaves a half-filled modal behind.
        // resetForm() above invalidated any earlier load, so only this request may act.
        const requestId = this.payeeRequestId;

        this.loadPayeeData(payeeId, requestId)
          .then(() => {
            if (requestId === this.payeeRequestId) {
              this.$refs.formModal.show();
            }
          })
          .catch((error) => {
            if (requestId !== this.payeeRequestId) {
              return;
            }

            console.error('Error loading payee:', error);
            this.resetForm();
            showErrorToast(__('Failed to load payee data'));
          });
      },

      initializeCategorySelect() {
        this.categorySelect = markRaw(
          createRemoteSelect(
            this.$el.querySelector(`#${this.categorySelectId}`),
            {
              url: '/api/v1/categories',
              params: (term) => ({ q: term || '*', withInactive: true }),
              mapResult: (item) => ({ id: item.id, text: item.full_name }),
              placeholder: __('Select category'),
              onChange: (value) => {
                this.form.config.category_id = value ? Number(value) : null;
              },
            },
          ),
        );
      },

      initializeCategoryPreferenceSelects() {
        // A category can't be both preferred and excluded: each select hides the other's values
        const createPreferenceSelect = (selectId, otherSelect, field) =>
          markRaw(
            createRemoteSelect(this.$el.querySelector(`#${selectId}`), {
              url: '/api/v1/categories',
              params: (term) => ({ q: term || '*', withInactive: true }),
              mapResult: (item) => ({ id: item.id, text: item.full_name }),
              filterResults: (results) => {
                const otherValues = this[otherSelect].getValue();

                return results.filter(
                  (item) => !otherValues.includes(String(item.id)),
                );
              },
              multiple: true,
              placeholder: __('Select category'),
              onChange: (values) => {
                this.form.config[field] = values.map(Number);
              },
            }),
          );

        this.preferredSelect = createPreferenceSelect(
          this.preferredCategoriesSelectId,
          'notPreferredSelect',
          'preferred',
        );
        this.notPreferredSelect = createPreferenceSelect(
          this.notPreferredCategoriesSelectId,
          'preferredSelect',
          'not_preferred',
        );
      },

      toSelectItems(categories) {
        return categories.map((category) => ({
          id: category.id,
          text: category.full_name,
        }));
      },

      loadPayeeData(payeeId, requestId) {
        this.payeeId = payeeId;

        // Fetch payee data from API
        return fetch(route('api.v1.payees.show', { accountEntity: payeeId }))
          .then((response) => {
            if (!response.ok) {
              throw new Error('Failed to load payee data');
            }
            return response.json();
          })
          .then((data) => {
            // A newer show() or a reset superseded this request
            if (requestId !== this.payeeRequestId) {
              return;
            }

            this.form.name = data.name;
            this.form.active = Boolean(data.active);
            this.form.alias = data.alias || '';
            this.form.config.category_id = data.config?.category_id || null;
            this.form.config.auto_record_policy =
              data.config?.auto_record_policy || 'follow_global';
            this.form.config.itemization_expected = Boolean(
              data.config?.itemization_expected,
            );
            this.form.config.preferred = (data.preferred_categories || []).map(
              (category) => Number(category.id),
            );
            this.form.config.not_preferred = (
              data.deferred_categories || []
            ).map((category) => Number(category.id));

            // The form fields are set above, so the selects are updated silently
            clearSelect(this.categorySelect, { silent: true });
            if (data.config?.category) {
              setSelected(
                this.categorySelect,
                this.toSelectItems([data.config.category]),
                { silent: true },
              );
            }

            if (!this.simplified) {
              clearSelect(this.preferredSelect, { silent: true });
              setSelected(
                this.preferredSelect,
                this.toSelectItems(data.preferred_categories || []),
                { silent: true },
              );
              clearSelect(this.notPreferredSelect, { silent: true });
              setSelected(
                this.notPreferredSelect,
                this.toSelectItems(data.deferred_categories || []),
                { silent: true },
              );
            }

            // The freshly-loaded values are the "clean" baseline for the
            // dirty check in FormModal, not the blank values the Form was
            // constructed with.
            this.form.originalData = JSON.parse(
              JSON.stringify(this.form.data()),
            );
          });
      },

      resetForm() {
        this.form.reset();
        this.form.errors.clear();

        this.form.name = '';
        this.form.active = true;
        this.form.alias = '';
        this.form.config.category_id = null;
        this.form.config.auto_record_policy = 'follow_global';
        this.form.config.itemization_expected = false;
        this.form.config.preferred = [];
        this.form.config.not_preferred = [];

        if (this.categorySelect) {
          clearSelect(this.categorySelect, { silent: true });
        }

        if (!this.simplified) {
          if (this.preferredSelect) {
            clearSelect(this.preferredSelect, { silent: true });
          }
          if (this.notPreferredSelect) {
            clearSelect(this.notPreferredSelect, { silent: true });
          }
        }

        // These blank values are the "clean" baseline for a new payee - without this,
        // FormModal's dirty check would compare against whatever payee was last edited.
        this.form.originalData = JSON.parse(JSON.stringify(this.form.data()));

        // Reset payee ID, and drop any payee load still in flight
        this.payeeId = null;
        this.payeeRequestId++;

        // Reset list of similar payees
        this.similarPayees = [];

        if (this.similarPayeesDebounceTimeout) {
          clearTimeout(this.similarPayeesDebounceTimeout);
        }
        this.similarPayeesRequestId++;
      },

      onNameChange(event) {
        const query = event.target.value?.trim();

        if (this.similarPayeesDebounceTimeout) {
          clearTimeout(this.similarPayeesDebounceTimeout);
        }

        if (!query) {
          this.similarPayeesRequestId++;
          this.similarPayees = [];
          return;
        }

        const requestId = ++this.similarPayeesRequestId;

        this.similarPayeesDebounceTimeout = setTimeout(() => {
          // Get similar payees from API
          fetch('/api/v1/payees/similar?query=' + encodeURIComponent(query))
            .then((response) => {
              if (!response.ok) {
                throw new Error('Failed to fetch similar payees');
              }
              return response.json();
            })
            .then((data) => {
              if (requestId !== this.similarPayeesRequestId) {
                return;
              }

              this.similarPayees = data;
            })
            .catch((error) => {
              if (requestId !== this.similarPayeesRequestId) {
                return;
              }

              console.error('Error fetching similar payees:', error);
              this.similarPayees = [];
            });
        }, 300);
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
        this.$refs.formModal.hide();
      },

      onSubmit() {
        if (this.action === 'new') {
          this.form
            .post(route('api.v1.payees.store'), this.form)
            .then((response) => this.processAfterSubmit(response));
        } else {
          if (this.formUrl === null) {
            this.form.errors.set({
              general: __('Failed to determine API endpoint'),
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
