import Form from 'vform';

/**
 * Shared by TransactionFormStandard and TransactionFormInvestment: lets the form edit a transaction
 * template (action "template") instead of a transaction. A template has no date, schedule or
 * reconciliation, and is saved to the template API as a draft of only the filled fields.
 */
export default {
  props: {
    // {id, name, is_featured, notices} of the edited template; id is null for a new one
    templateInfo: { type: Object, default: null },
    // The template a new transaction is created from, sent back for the usage statistics
    transactionTemplateId: { type: Number, default: null },
  },

  data() {
    return {
      templateName: this.templateInfo?.name ?? '',
      templateIsFeatured: this.templateInfo?.is_featured ?? false,
    };
  },

  computed: {
    isTemplate() {
      return this.action === 'template';
    },
  },

  methods: {
    draftId(value) {
      return value === null || value === undefined || value === ''
        ? null
        : Number(value);
    },

    // Amounts travel as decimal strings
    draftDecimal(value) {
      return value === null ||
        value === undefined ||
        value === '' ||
        Number.isNaN(value)
        ? null
        : String(value);
    },

    /**
     * Drop empty values, so that the draft holds only what the user filled in.
     */
    compactDraftPart(part) {
      return Object.fromEntries(
        Object.entries(part).filter(
          ([, value]) => value !== null && value !== undefined && value !== '',
        ),
      );
    },

    saveTemplate(draft) {
      const templateForm = new Form({
        name: this.templateName,
        is_featured: this.templateIsFeatured,
        draft,
      });
      const url = this.templateInfo?.id
        ? this.route('api.v1.transaction-templates.update', {
            template: this.templateInfo.id,
          })
        : this.route('api.v1.transaction-templates.store');

      this.form.errors.clear();
      this.form.busy = true;

      (this.templateInfo?.id ? templateForm.patch(url) : templateForm.post(url))
        .then((response) =>
          this.$emit('success', response.data.template, {
            callback: 'templates',
          }),
        )
        .catch(() => {
          // Show the server's errors on the form's own fields ("draft.config.x" -> "config.x")
          const errors = {};
          Object.entries(templateForm.errors.all()).forEach(
            ([key, messages]) => {
              errors[key.replace(/^draft\./, '')] = messages;
            },
          );
          this.form.errors.set(errors);
        })
        .finally(() => {
          this.form.busy = false;
        });
    },
  },
};
