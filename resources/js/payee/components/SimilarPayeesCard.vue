<template>
  <div v-if="similarPayees.length > 0" id="similarPayeesCard" class="card mb-3">
    <div class="card-header">
      <div class="card-title">{{ __('Similar payees') }}</div>
    </div>
    <ul class="list-group list-group-flush">
      <li
        v-for="similar in similarPayees"
        :key="similar.id"
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a :href="route('account-entity.show', { account_entity: similar.id })">
          {{ similar.name }}
        </a>
        <a
          class="btn btn-sm btn-outline-primary"
          :href="route('payees.merge.form', { payeeSource: similar.id })"
          :title="__('Merge into an other payee')"
        >
          <i class="fa fa-random"></i>
        </a>
      </li>
    </ul>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  // Names below this similarity are noise, not duplicate candidates
  const MIN_SIMILARITY_PERCENTAGE = 60;

  export default {
    name: 'SimilarPayeesCard',
    props: {
      payeeId: { type: Number, required: true },
      payeeName: { type: String, required: true },
    },
    data() {
      return { candidates: [] };
    },
    computed: {
      similarPayees() {
        return this.candidates.filter(
          (candidate) =>
            candidate.id !== this.payeeId &&
            candidate.percentage >= MIN_SIMILARITY_PERCENTAGE,
        );
      },
    },
    mounted() {
      // A failure only hides this optional card
      window.axios
        .get('/api/v1/payees/similar', { params: { query: this.payeeName } })
        .then((response) => {
          this.candidates = response.data;
        })
        .catch(() => {});
    },
    methods: { __ },
  };
</script>
