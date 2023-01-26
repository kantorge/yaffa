<template>
  <div id="widgetPayeeCategoryRecommendation" class="card mb-4" v-if="payeeSuggestion">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Tip on your payee!') }}
      </div>
      <div>
        <button type="button" class="btn-close" aria-label="Close" @click="hide" :disabled="busy"></button>
      </div>
    </div>
    <div class="card-body">
      <p>
        Your payee <strong><a :href="editlink">{{ payeeSuggestion.payee }}</a></strong>
        used category <strong>{{ payeeSuggestion.category }}</strong>
        {{ payeeSuggestion.max }} times out of {{ payeeSuggestion.sum }} transactions.
        It might be a good idea to set it as default category.
      </p>
      <div v-if="!success">
        <button type="button" class="btn btn-success me-2"
                :title="__('Accept recommendation and set it as default category for payee')" @click="accept"
                :disabled="busy">
          {{ __('OK, let\'s do it!') }}
        </button>
        <button type="button" class="btn btn-primary me-2"
                :title="__('Hide this recommendation, but it might be displayed later')" @click="hide" :disabled="busy">
          {{ __('Maybe later') }}
        </button>
        <button type="button" class="btn btn-outline-dark me-2"
                :title="__('Don\'t show category recommendations for this payee any more')" @click="dismiss"
                :disabled="busy">
          {{ __('No, thanks') }}
        </button>
      </div>
    </div>
    <div class="card-footer" v-if="error || success">
      <span class="text-danger" v-if="error">{{ __('Something failed') }}</span>
      <span class="text-success" v-if="success">{{ __('Saved successfully') }}</span>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      payeeSuggestion: null,
      error: false,
      busy: false,
      success: false,
    }
  },

  created() {
    axios.get('/api/assets/get_default_category_suggestion')
        .then(response => this.payeeSuggestion = response.data)
  },

  methods: {
    accept() {
      this.busy = true;
      let vue = this;

      axios.get('/api/assets/accept_default_category_suggestion/' + this.payeeSuggestion.payee_id + '/' + this.payeeSuggestion.max_category_id)
          .then(function () {
            vue.success = true;
            vue.error = false;
          })
          .catch(function () {
            vue.success = false;
            vue.error = true;
          })
          .finally(() => vue.busy = false)
    },

    dismiss() {
      this.busy = true;
      let vue = this;

      axios.get('/api/assets/dismiss_default_category_suggestion/' + this.payeeSuggestion.payee_id)
          .finally(() => this.hide())
          .catch(function () {
            vue.success = false;
            vue.error = true;
          })

      this.busy = false;
    },

    hide() {
      $('#widgetPayeeCategoryRecommendation').hide();
    }
  },

  computed: {
    editlink() {
      return window.route('account-entity.edit', {type: 'payee', account_entity: this.payeeSuggestion.payee_id});
    }
  }
}
</script>
