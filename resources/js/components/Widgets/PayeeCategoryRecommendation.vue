<template>
  <div
    id="widgetPayeeCategoryRecommendation"
    class="card mb-4"
    v-if="payeeSuggestion"
  >
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('widget.payeeCategoryRecommendation.cardTitle') }}
      </div>
      <div>
        <button
          type="button"
          class="btn-close"
          aria-label="Close"
          @click="hide"
          :disabled="busy"
        ></button>
      </div>
    </div>
    <div class="card-body">
      <p v-html="paragraph"></p>
      <div v-if="!success">
        <button
          type="button"
          class="btn btn-success me-2"
          :title="__('widget.payeeCategoryRecommendation.acceptTitle')"
          @click="accept"
          :disabled="busy"
        >
          {{ __('widget.payeeCategoryRecommendation.acceptButton') }}
        </button>
        <button
          type="button"
          class="btn btn-primary me-2"
          :title="__('widget.payeeCategoryRecommendation.maybeLaterTitle')"
          @click="hide"
          :disabled="busy"
        >
          {{ __('widget.payeeCategoryRecommendation.maybeLaterButton') }}
        </button>
        <button
          type="button"
          class="btn btn-outline-dark me-2"
          :title="__('widget.payeeCategoryRecommendation.dismissTitle')"
          @click="dismiss"
          :disabled="busy"
        >
          {{ __('widget.payeeCategoryRecommendation.dismissButton') }}
        </button>
      </div>
    </div>
    <div class="card-footer" v-if="error || success">
      <span class="text-danger" v-if="error">{{
        __('widget.payeeCategoryRecommendation.error')
      }}</span>
      <span class="text-success" v-if="success">{{
        __('widget.payeeCategoryRecommendation.success')
      }}</span>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/i18n';

  export default {
    data() {
      return {
        payeeSuggestion: null,
        error: false,
        busy: false,
        success: false,
      };
    },

    created() {
      axios
        .get('/api/v1/payees/category-suggestions/default')
        .then((response) => (this.payeeSuggestion = response.data));
    },

    methods: {
      accept() {
        this.busy = true;
        let vue = this;

        axios
          .post(
            '/api/v1/payees/' +
              this.payeeSuggestion.payee_id +
              '/category-suggestions/accept/' +
              this.payeeSuggestion.max_category_id,
          )
          .then(function () {
            vue.success = true;
            vue.error = false;
          })
          .catch(function () {
            vue.success = false;
            vue.error = true;
          })
          .finally(() => (vue.busy = false));
      },

      dismiss() {
        this.busy = true;
        let vue = this;

        axios
          .post(
            '/api/v1/payees/' +
              this.payeeSuggestion.payee_id +
              '/category-suggestions/dismiss',
          )
          .finally(() => this.hide())
          .catch(function () {
            vue.success = false;
            vue.error = true;
          });

        this.busy = false;
      },

      hide() {
        $('#widgetPayeeCategoryRecommendation').hide();
      },

      escapeHtml(value) {
        const textNode = document.createElement('div');
        textNode.textContent = value;

        return textNode.innerHTML;
      },
      __,
    },

    computed: {
      editlink() {
        return this.route('account-entity.edit', {
          type: 'payee',
          account_entity: this.payeeSuggestion.payee_id,
        });
      },

      paragraph() {
        if (!this.payeeSuggestion) {
          return '';
        }

        return __('widget.payeeCategoryRecommendation.paragraph', {
          payeeLink: this.payeeLink,
          categoryText: this.categoryText,
          maxCount: this.payeeSuggestion.max,
          totalCount: this.payeeSuggestion.sum,
        });
      },

      payeeLink() {
        return `<strong><a href="${this.editlink}">${this.escapeHtml(this.payeeSuggestion.payee)}</a></strong>`;
      },

      categoryText() {
        return `<strong>${this.escapeHtml(this.payeeSuggestion.category)}</strong>`;
      },
    },
  };
</script>
