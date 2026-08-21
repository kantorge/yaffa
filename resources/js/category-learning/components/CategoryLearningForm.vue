<template>
  <FormModal
    ref="formModal"
    :id="id"
    :action="action"
    :new-title="__('Add new category learning entry')"
    :edit-title="__('Edit category learning entry')"
    :form="form"
    @submit="onSubmit"
  >
            <div class="row mb-3">
              <label :for="descriptionInputId" class="form-label col-sm-3">
                {{ __('Description') }}
              </label>
              <div class="col-sm-9">
                <input
                  class="form-control"
                  :id="descriptionInputId"
                  maxlength="255"
                  type="text"
                  v-model="form.item_description"
                  @keyup="onDescriptionChange"
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
                {{ __('Category') }}
              </label>
              <div class="col-sm-9">
                <select
                  :id="categorySelectId"
                  class="form-select category"
                  style="width: 100%"
                ></select>
              </div>
            </div>

            <div class="row mb-3" v-show="similarLearnings.length > 0">
              <hr />
              <span class="form-label col-sm-3">{{
                __('Similar category learning entries')
              }}</span>
              <div class="col-sm-9">
                <ul class="list-unstyled" id="similar-learning-list">
                  <li
                    class="mt-2"
                    v-for="learning in similarLearnings"
                    :key="learning.id"
                  >
                    <a href="#" @click.prevent="onSelectLearning(learning)">
                      {{ learning.item_description }}
                      <span class="text-muted"
                        >({{
                          learning.category?.full_name ||
                          learning.category?.name
                        }})</span
                      >
                      <span v-if="!learning.active" class="text-warning">
                        - {{ __('inactive, click to activate') }}</span
                      >
                    </a>
                  </li>
                </ul>
              </div>
            </div>
  </FormModal>
</template>

<script>
  import { initializeSelect2 } from '@/shared/lib/select2';
  initializeSelect2(window.YAFFA.userSettings.language);

  import Form from 'vform';

  import FormModal from '@/shared/ui/FormModal.vue';

  export default {
    components: {
      FormModal,
    },

    props: {
      action: String,
      id: {
        type: String,
        default: 'newCategoryLearningModal',
      },
      instanceId: {
        type: String,
        default: null,
      },
    },

    data() {
      return {
        form: new Form({
          item_description: '',
          category_id: null,
          active: true,
        }),
        similarLearnings: [],
        learningId: null,
        categorySelect: null,
        similarLearningsDebounceTimeout: null,
        similarLearningsRequestId: 0,
      };
    },

    computed: {
      formInstanceId() {
        return this.instanceId || this.id;
      },
      descriptionInputId() {
        return `${this.formInstanceId}-item_description`;
      },
      activeInputId() {
        return `${this.formInstanceId}-active`;
      },
      categorySelectId() {
        return `${this.formInstanceId}-category_id`;
      },
      formUrl() {
        if (this.learningId === null) {
          return null;
        }

        return route('api.v1.category-learning.update', {
          categoryLearning: this.learningId,
        });
      },
    },

    mounted() {
      this.initializeCategorySelect();
    },

    beforeUnmount() {
      if (this.similarLearningsDebounceTimeout) {
        clearTimeout(this.similarLearningsDebounceTimeout);
      }
    },

    methods: {
      show(learning = null) {
        this.resetForm();

        if (learning !== null) {
          this.learningId = learning.id;
          this.form.item_description = learning.item_description;
          this.form.category_id = learning.category?.id || null;
          this.form.active = Boolean(learning.active);

          if (learning.category) {
            this.setSelectValue(this.categorySelect, learning.category);
          }

          // The freshly-loaded values are the "clean" baseline for the
          // dirty check in FormModal, not the blank values the Form was
          // constructed with.
          this.form.originalData = JSON.parse(JSON.stringify(this.form.data()));
        }

        this.$refs.formModal.show();
      },

      initializeCategorySelect() {
        this.categorySelect = $(this.$el).find(`#${this.categorySelectId}`);

        this.categorySelect
          .select2({
            language: window.YAFFA.userSettings.language,
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
            selectOnClose: false,
            placeholder: __('Select category'),
            allowClear: true,
            dropdownParent: $('#' + this.id),
          })
          .on('select2:select select2:unselect', () => {
            const selectedValue = this.categorySelect.val();
            this.form.category_id =
              selectedValue === null || selectedValue === ''
                ? null
                : Number(selectedValue);
          });
      },

      setSelectValue(selectElement, category) {
        if (!selectElement || !category) {
          return;
        }

        const option = new Option(
          category.full_name || category.name,
          category.id,
          true,
          true,
        );
        selectElement.append(option).trigger('change');
      },

      resetForm() {
        this.form.reset();
        this.form.errors.clear();

        this.form.active = true;
        this.form.item_description = '';
        this.form.category_id = null;

        this.learningId = null;
        this.similarLearnings = [];

        if (this.similarLearningsDebounceTimeout) {
          clearTimeout(this.similarLearningsDebounceTimeout);
        }
        this.similarLearningsRequestId++;

        if (this.categorySelect) {
          this.categorySelect.empty().val(null).trigger('change');
        }
      },

      onDescriptionChange(event) {
        const query = event.target.value?.trim();

        if (this.similarLearningsDebounceTimeout) {
          clearTimeout(this.similarLearningsDebounceTimeout);
        }

        if (!query) {
          this.similarLearningsRequestId++;
          this.similarLearnings = [];
          return;
        }

        const requestId = ++this.similarLearningsRequestId;

        this.similarLearningsDebounceTimeout = setTimeout(() => {
          window.axios
            .get(route('api.v1.category-learning.index'), {
              params: {
                search: query,
                status: 'all',
              },
            })
            .then((response) => {
              if (requestId !== this.similarLearningsRequestId) {
                return;
              }

              const rows = Array.isArray(response.data) ? response.data : [];

              this.similarLearnings = rows.filter(
                (item) => Number(item.id) !== Number(this.learningId),
              );
            })
            .catch(() => {
              if (requestId !== this.similarLearningsRequestId) {
                return;
              }

              this.similarLearnings = [];
            });
        }, 300);
      },

      onSelectLearning(learning) {
        if (!learning.active) {
          window.axios
            .post(
              route('api.v1.category-learning.activate', {
                categoryLearning: learning.id,
              }),
            )
            .then((response) => this.processAfterSubmit(response));

          return;
        }

        this.hideAndReset();
        this.$emit('learning-selected', learning);
      },

      processAfterSubmit(response) {
        setTimeout(() => this.hideAndReset(), 1000);
        this.$emit('learning-selected', response.data);
      },

      hideAndReset() {
        this.resetForm();

        // The blank reset state is the "clean" baseline for the next time this modal
        // opens as "new" - without this, FormModal's dirty check would compare
        // against whatever entry was last edited.
        this.form.originalData = JSON.parse(JSON.stringify(this.form.data()));

        this.$refs.formModal.hide();
      },

      async onSubmit() {
        let response = null;

        if (this.action === 'new') {
          response = await this.form.post(
            route('api.v1.category-learning.store'),
            this.form,
          );
        } else {
          if (this.formUrl === null) {
            this.form.errors.set({
              general: __('Failed to determine API endpoint'),
            });

            return;
          }

          response = await this.form.patch(this.formUrl, this.form);
        }

        this.processAfterSubmit(response);
      },
      __,
    },
  };
</script>
