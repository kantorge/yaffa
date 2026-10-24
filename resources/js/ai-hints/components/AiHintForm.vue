<template>
  <FormModal
    :id="id"
    ref="formModal"
    :action="action"
    :new-title="__('Add new AI hint')"
    :edit-title="__('Edit AI hint')"
    size="lg"
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
      <label :for="triggerInputId" class="form-label col-sm-3">
        {{ __('Trigger text') }}
      </label>
      <div class="col-sm-9">
        <input
          :id="triggerInputId"
          v-model="form.trigger_text"
          class="form-control"
          maxlength="255"
          type="text"
        />
        <HasError field="trigger_text" :form="form" />
        <small class="form-text text-muted">
          {{
            __(
              'Case-insensitive text to look for in the document. Leave empty to always use this hint.',
            )
          }}
        </small>
      </div>
    </div>

    <div class="row mb-3">
      <label :for="hintInputId" class="form-label col-sm-3">
        {{ __('Hint') }}
      </label>
      <div class="col-sm-9">
        <textarea
          :id="hintInputId"
          v-model="form.hint"
          class="form-control"
          maxlength="5000"
          rows="6"
        ></textarea>
        <HasError field="hint" :form="form" />
      </div>
    </div>
  </FormModal>
</template>

<script>
  import Form from 'vform';
  import { HasError } from 'vform/src/components/bootstrap5';

  import FormModal from '@/shared/ui/FormModal.vue';
  import { __ } from '@/shared/lib/i18n';

  export default {
    components: {
      FormModal,
      HasError,
    },

    props: {
      action: {
        type: String,
        default: 'new',
      },
      id: {
        type: String,
        default: 'newAiHintModal',
      },
    },

    emits: ['hint-saved'],

    data() {
      return {
        form: new Form({
          name: '',
          trigger_text: '',
          hint: '',
          active: true,
        }),
        hintId: null,
      };
    },

    computed: {
      nameInputId() {
        return `${this.id}-name`;
      },
      activeInputId() {
        return `${this.id}-active`;
      },
      triggerInputId() {
        return `${this.id}-trigger_text`;
      },
      hintInputId() {
        return `${this.id}-hint`;
      },
    },

    methods: {
      show(hint = null) {
        this.resetForm();

        if (hint !== null) {
          this.hintId = hint.id;
          this.form.name = hint.name;
          this.form.trigger_text = hint.trigger_text ?? '';
          this.form.hint = hint.hint;
          this.form.active = Boolean(hint.active);
        }

        // The freshly-loaded values are the "clean" baseline for the dirty check in FormModal
        this.form.originalData = JSON.parse(JSON.stringify(this.form.data()));

        this.$refs.formModal.show();
      },

      resetForm() {
        this.form.reset();
        this.form.errors.clear();

        this.form.name = '';
        this.form.trigger_text = '';
        this.form.hint = '';
        this.form.active = true;

        this.hintId = null;
      },

      async onSubmit() {
        this.form.trigger_text = this.form.trigger_text || null;

        const response =
          this.action === 'new'
            ? await this.form.post(route('api.v1.ai-hints.store'))
            : await this.form.patch(
                route('api.v1.ai-hints.update', { aiHint: this.hintId }),
              );

        this.$emit('hint-saved', response.data);

        setTimeout(() => this.$refs.formModal.hide(), 300);
      },

      __,
    },
  };
</script>
