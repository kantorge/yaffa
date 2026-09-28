<template>
  <div :id="`findTransactionsSelectCard-${property}`" class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __(title) }}
      </div>
      <div>
        <button
          class="btn btn-sm btn-danger"
          :disabled="selectedValues.length === 0 || !ready"
          :title="__('Clear selection')"
          @click="clearSelection"
        >
          <i class="fa fa-fw fa-times"></i>
        </button>
      </div>
    </div>
    <div class="card-body">
      <select :id="elementId" class="form-select" style="width: 100%"></select>
    </div>
  </div>
</template>

<script>
  import { markRaw } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import {
    createRemoteSelect,
    setSelected,
    clearSelect,
  } from '@/shared/lib/tom-select';

  export default {
    name: 'FindTransactionSelectCard',
    props: {
      property: {
        type: String,
        required: true,
      },
      title: {
        type: String,
        required: true,
      },
      placeholder: {
        type: String,
        default: 'Select item',
      },
      // API path for the search endpoint
      searchApiPath: {
        type: String,
        required: true,
      },
      // Field to use as label in the search results
      search_label_field: {
        type: String,
        default: 'name',
      },
      presetItemIds: {
        type: Array,
        default: () => [],
      },
      // API path for the details endpoint
      detailsApiPath: {
        type: String,
        required: true,
      },
      detailsLabelField: {
        type: String,
        default: 'name',
      },
    },
    emits: ['update', 'preset-ready'],

    data() {
      return {
        itemsToPreset: this.presetItemIds.map((id) => id),
        selectedValues: [],
        elementId: `select_${this.property}`,
        select: null,
      };
    },

    computed: {
      ready() {
        return this.itemsToPreset.length === 0;
      },
    },

    mounted() {
      this.select = markRaw(
        createRemoteSelect(this.$el.querySelector(`#${this.elementId}`), {
          url: this.searchApiPath,
          params: (term) => ({ q: term || undefined, withInactive: true }),
          mapResult: (data) => ({
            id: data.id,
            text:
              data[this.search_label_field] ??
              data.full_name ??
              data.text ??
              data.name,
          }),
          multiple: true,
          placeholder: __(this.placeholder),
          onChange: (values) => {
            this.selectedValues = values;
            this.$emit('update', values);
          },
        }),
      );

      // Append preset items, if any
      if (this.presetItemIds.length === 0) {
        this.$emit('preset-ready', this.property);
        return;
      }

      this.presetItemIds.forEach((item) => {
        window.axios
          .get(this.detailsApiPath.replace('#id#', item))
          .then(({ data }) => {
            setSelected(this.select, {
              id: data.id,
              text: data[this.detailsLabelField],
            });
          })
          // A failed lookup still settles, so the card doesn't stay not-ready forever
          .finally(() => {
            this.itemsToPreset = this.itemsToPreset.filter((id) => id !== item);

            if (this.itemsToPreset.length === 0) {
              this.$emit('preset-ready', this.property);
            }
          });
      });
    },

    beforeUnmount() {
      this.select?.destroy();
    },

    methods: {
      clearSelection: function () {
        clearSelect(this.select, { silent: true });

        this.selectedValues = [];

        this.$emit(`update`, this.selectedValues);
      },
      __,
    },
  };
</script>
