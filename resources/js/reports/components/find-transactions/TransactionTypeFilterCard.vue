<template>
  <div class="card mb-3" id="findTransactionsTypeCard">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Transaction type') }}
      </div>
      <span
        v-if="currentSelectedTypes.length === 0"
        class="fa fa-info-circle text-info"
        data-bs-toggle="tooltip"
        data-bs-placement="right"
        :title="
          __(
            'No type is selected, so transactions of all types will be searched.',
          )
        "
      ></span>
    </div>
    <div class="card-body">
      <div ref="tree" id="find-transactions-type-tree-container"></div>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
  import 'jstree/src/themes/default/style.css';
  import 'jstree';

  const ROOT_ID = 'root';
  const GROUP_IDS = {
    standard: 'group_standard',
    investment: 'group_investment',
  };
  const GROUP_LABELS = {
    standard: () => __('Standard'),
    investment: () => __('Investment'),
  };

  export default {
    name: 'TransactionTypeFilterCard',
    emits: ['update', 'preset-ready'],
    props: {
      // Type values (e.g. 'deposit', 'withdrawal') preselected from the URL.
      // An empty array means no explicit selection was provided, so all types are selected by default.
      presetTypeValues: {
        type: Array,
        default: () => [],
      },
    },
    data() {
      return {
        allTypeValues: Object.keys(window.YAFFA.config.transactionTypes || {}),
        // Tracks the live selection so the "no types selected" hint can be shown/hidden;
        // set to the initial selection once the tree is created.
        currentSelectedTypes: [],
      };
    },
    computed: {
      initialSelectedTypes() {
        if (this.presetTypeValues.length === 0) {
          return this.allTypeValues.slice();
        }

        return this.presetTypeValues.filter((value) =>
          this.allTypeValues.includes(value),
        );
      },
      treeData() {
        const types = window.YAFFA.config.transactionTypes || {};
        const selected = new Set(this.initialSelectedTypes);

        const nodes = [
          {
            id: ROOT_ID,
            parent: '#',
            text: __('All types'),
            state: { opened: false },
          },
          {
            id: GROUP_IDS.standard,
            parent: ROOT_ID,
            text: GROUP_LABELS.standard(),
            state: { opened: false },
          },
          {
            id: GROUP_IDS.investment,
            parent: ROOT_ID,
            text: GROUP_LABELS.investment(),
            state: { opened: false },
          },
        ];

        Object.values(types).forEach((type) => {
          nodes.push({
            id: type.value,
            parent: GROUP_IDS[type.category] || ROOT_ID,
            text: type.label,
            state: { selected: selected.has(type.value) },
          });
        });

        return nodes;
      },
    },
    mounted() {
      const vue = this;

      $(this.$refs.tree)
        .jstree({
          core: {
            data: this.treeData,
            // Without this, jstree auto-expands any node containing a checked
            // descendant, which would force the tree open by default since all
            // types are checked initially.
            expand_selected_onload: false,
            themes: {
              dots: false,
              icons: false,
            },
          },
          plugins: ['checkbox'],
          checkbox: {
            keep_selected_style: false,
          },
        })
        .on('changed.jstree', function () {
          vue.emitSelectionFromTree();
        });

      // No asynchronous loading is needed for this filter (data is available locally),
      // so the initial selection can be emitted and marked ready immediately.
      this.currentSelectedTypes = this.initialSelectedTypes;
      this.$emit('update', this.initialSelectedTypes);
      this.$emit('preset-ready', 'types');
    },
    updated() {
      initializeBootstrapTooltips(this.$el);
    },
    beforeUnmount() {
      if ($(this.$refs.tree).jstree(true)) {
        $(this.$refs.tree).jstree('destroy');
      }
    },
    methods: {
      emitSelectionFromTree() {
        const checkedIds = $(this.$refs.tree).jstree().get_checked();
        const selected = checkedIds.filter((id) =>
          this.allTypeValues.includes(id),
        );

        this.currentSelectedTypes = selected;
        this.$emit('update', selected);
      },
      __,
    },
  };
</script>
