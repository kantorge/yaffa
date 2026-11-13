<template>
  <div
    :id="'transaction_item_' + id"
    class="list-group-item mb-2 transaction_item_row"
  >
    <!-- Item description banner for AI recommendations -->
    <div
      v-if="recommended_category_id || description"
      class="alert mb-2 py-2 px-3 d-flex justify-content-between align-items-center"
      :class="aiAlertClass"
    >
      <div class="d-flex align-items-center gap-3">
        <i class="fa fa-receipt me-2"></i>

        <div class="d-inline-flex align-items-center">
          <input
            :id="learnRecommendationToggleId"
            v-model="learnRecommendation"
            type="checkbox"
            class="btn-check"
            :disabled="!categoryIdData"
            :title="learnRecommendationTooltip"
            :aria-label="learnRecommendationTooltip"
            autocomplete="off"
            value="1"
            @change="onLearnRecommendationChange"
          />
          <label
            class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1"
            :for="learnRecommendationToggleId"
            :title="learnRecommendationTooltip"
            :aria-label="learnRecommendationTooltip"
          >
            <i class="fa fa-database" aria-hidden="true"></i>
          </label>
        </div>

        <div>
          {{ description }}
          <span v-if="showAddButton">
            → {{ recommended_category_full_name }}
          </span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <!-- Status badge for AI suggestions -->
        <span
          v-if="recommended_category_id && isRecommendationAccepted"
          class="badge bg-success text-white ms-1"
          :title="__('Using AI recommendation')"
        >
          <i class="fa fa-check me-1"></i>{{ __('AI suggested') }}
        </span>
        <span
          v-else-if="recommended_category_id && !isRecommendationAccepted"
          class="badge bg-warning text-dark ms-1"
          :title="__('AI recommendation overridden')"
        >
          <i class="fa fa-edit me-1"></i>{{ __('Modified') }}
        </span>

        <!-- Confidence badge for AI suggestions -->
        <span
          v-if="match_type === 'ai' && confidence_score !== null"
          class="badge"
          :class="confidenceBadgeClass"
          :title="__('AI confidence score')"
        >
          {{ formatConfidence }}%
        </span>

        <span
          v-else-if="description !== null && confidence_score === null"
          class="badge"
          :class="'bg-danger'"
          :title="__('No AI match found')"
        >
          {{ __('No AI match found') }}
        </span>

        <!-- Exact match badge -->
        <span
          v-else-if="match_type === 'exact'"
          class="badge bg-success"
          :title="__('Exact match from learning')"
        >
          <i class="fa fa-check me-1"></i>{{ __('Exact') }}
        </span>

        <!-- Remove button for high-confidence matches -->
        <button
          v-if="showRemoveButton"
          type="button"
          class="btn btn-sm btn-outline-warning"
          :title="__('Remove suggestion (will not learn)')"
          style="white-space: nowrap"
          @click="removeSuggestion"
        >
          <i class="fa fa-trash me-1"></i>{{ __('Remove') }}
        </button>

        <!-- Add button for low-confidence or no match -->
        <button
          v-if="showAddButton"
          type="button"
          class="btn btn-sm btn-outline-success"
          :title="__('Accept suggestion')"
          style="white-space: nowrap"
          @click="acceptSuggestion"
        >
          <i class="fa fa-check me-1"></i>{{ __('Add') }}
        </button>
      </div>
    </div>

    <div class="row">
      <div class="col-12 col-sm-4 form-group">
        <span class="form-label">
          {{ __('Category') }}
        </span>
        <select
          class="form-select category"
          :data-testid="'transaction-item-category-' + id"
        ></select>
      </div>
      <div class="col-12 col-sm-2 form-group">
        <span class="form-label">
          {{ __('Amount') }}
          <span v-if="currencySymbol">({{ currencySymbol }})</span>
        </span>
        <div class="input-group">
          <MathInput
            v-model="amountData"
            class="form-control transaction_item_amount"
          ></MathInput>

          <button
            type="button"
            class="btn btn-info load_remainder"
            :title="__('Assign remaining amount to this item')"
            @click="loadRemainder"
          >
            <span class="fa fa-copy"></span>
          </button>
        </div>
      </div>
      <div
        class="col-12 col-sm-2 form-group transaction_detail_container transaction_item_tags_container"
        :class="{ 'd-none d-md-block': !tagRequired }"
      >
        <span class="form-label">
          {{ __('Tags') }}
        </span>
        <select
          class="form-select tag"
          :data-testid="'transaction-item-tags-' + id"
        ></select>
        <div
          v-if="isMissingRequiredTag"
          class="form-text text-warning"
          :data-testid="'transaction-item-tag-required-' + id"
        >
          <i class="fa fa-triangle-exclamation"></i>
          {{ __('This category expects a tag') }}
        </div>
      </div>
      <div
        class="col-12 col-sm-3 form-group transaction_detail_container"
        :class="{ 'd-none d-md-block': !recommended_category_id }"
      >
        <span class="form-label">
          {{ __('Comment') }}
        </span>
        <input
          v-model="commentData"
          class="form-control transaction_item_comment"
          type="text"
          :placeholder="__('Add comment')"
          @blur="$emit('update:comment', $event.target.value)"
        />
      </div>
      <div class="col-12 col-sm-1 justify-content-end d-flex align-items-start">
        <button
          type="button"
          class="btn btn-sm btn-info d-sm-none"
          :title="__('Show item details')"
          @click="toggleItemDetails"
        >
          <span class="fa fa-edit"></span>
        </button>
        <button
          type="button"
          class="btn btn-sm btn-danger"
          style="margin-left: 10px"
          :title="__('Remove transaction item')"
          @click="removeItem"
        >
          <span class="fa fa-minus"></span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
  import Decimal from 'decimal.js';
  import MathInput from '@/shared/ui/form/MathInput.vue';
  import { markRaw } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import {
    createLocalSelect,
    setSelected,
    clearSelect,
    lockSelect,
    unlockSelect,
  } from '@/shared/lib/tom-select';
  import {
    loadSelectData,
    loadTagRequiredCategoryIds,
    searchEntities,
    suggestCategories,
  } from '@/shared/lib/select-data';

  export default {
    components: {
      MathInput,
    },

    props: {
      id: Number,
      amount: [Number, String],
      category_id: Number,
      category_full_name: String,
      recommended_category_id: Number,
      recommended_category_full_name: String,
      description: String,
      currencySymbol: String,
      comment: String,
      tags: Array,
      tagReminder: {
        type: Boolean,
        default: true,
      },
      remainingAmount: Number,
      payee: [Number, String],
      match_type: {
        type: String,
        default: null,
      },
      confidence_score: {
        type: [Number, null],
        default: null,
      },
      confidenceThreshold: {
        type: Number,
        default: 0.7,
      },
    },

    emits: [
      'update:amount',
      'update:category_id',
      'update:comment',
      'update:tags',
      'removeItem',
      'update:learnRecommendation',
      'updateItemAmount',
    ],

    data() {
      return {
        categoryIdData: this.category_id,
        amountData: this.amount,
        commentData: this.comment,
        isRecommendationAccepted: false,
        suggestionRemoved: false,
        learnRecommendation: this.match_type === 'exact' ? false : true,
        tagRequiredCategoryIds: new Set(),
      };
    },

    computed: {
      // The selected category expects a tag (e.g. a recharge claim number)
      tagRequired() {
        return (
          this.tagReminder &&
          this.tagRequiredCategoryIds.has(this.categoryIdData)
        );
      },

      isMissingRequiredTag() {
        return this.tagRequired && (this.tags || []).length === 0;
      },

      /**
       * Determine which alert class to use based on match type and confidence
       */
      aiAlertClass() {
        if (this.match_type === 'exact') {
          return 'alert-success';
        }
        if (this.match_type === 'ai') {
          if (this.confidence_score >= this.confidenceThreshold) {
            return 'alert-info';
          }
          return 'alert-warning';
        }
        return 'alert-info';
      },

      /**
       * Format confidence score as percentage
       */
      formatConfidence() {
        if (this.confidence_score === null) {
          return '--';
        }
        return Math.round(this.confidence_score * 100);
      },

      /**
       * Badge styling for confidence score
       */
      confidenceBadgeClass() {
        if (this.confidence_score >= 0.8) {
          return 'bg-success';
        }
        if (this.confidence_score >= this.confidenceThreshold) {
          return 'bg-info';
        }
        return 'bg-warning';
      },

      learnRecommendationToggleId() {
        return `learn-recommendation-toggle-${this.id}`;
      },

      learnRecommendationTooltip() {
        if (!this.categoryIdData) {
          return __('Select category first to store learning');
        }

        if (this.learnRecommendation) {
          return this.recommended_category_id
            ? __(
                'AI recommendation will be stored for future matches. Click to disable.',
              )
            : __(
                'Selected category will be stored for future matches. Click to disable.',
              );
        }

        return this.recommended_category_id
          ? __(
              'AI recommendation will not be stored for future matches. Click to enable.',
            )
          : __(
              'Selected category will not be stored for future matches. Click to enable.',
            );
      },

      /**
       * Show remove button for high-confidence AI matches or exact matches
       * Exact: auto-selected with no button initially, but show if user tries to change
       * High confidence (>=0.7): auto-selected + remove button to reject
       */
      showRemoveButton() {
        // Only show if there's a recommendation that was auto-accepted OR accepted by the user
        if (!this.recommended_category_id) {
          return false;
        }

        return (
          (this.match_type === 'exact' || this.match_type === 'ai') &&
          this.isRecommendationAccepted &&
          !this.suggestionRemoved
        );
      },

      /**
       * Show add button for low-confidence suggestions or no match at all
       * Low confidence (<0.7): show button to accept
       * No match: no button (don't suggest something that wasn't AI-suggested)
       */
      showAddButton() {
        if (!this.recommended_category_id) {
          return false;
        }

        return (
          (this.match_type === 'exact' || this.match_type === 'ai') &&
          !this.isRecommendationAccepted
        );
      },
    },

    watch: {
      amountData(newAmount) {
        this.$emit('update:amount', newAmount);
      },
    },

    mounted() {
      loadTagRequiredCategoryIds().then((ids) => {
        this.tagRequiredCategoryIds = ids;
      });

      // Shared, memoized preload (the form usually has it already); null until it arrives
      this.selectData = null;

      this.categorySelect = markRaw(
        createLocalSelect(this.$el.querySelector('select.category'), {
          suggestions: () => this.categorySuggestions(),
          search: (term) => this.searchCategories(term),
          placeholder: __('Select category'),
          onChange: (value) => {
            this.categoryIdData = value ? Number(value) : null;

            // Track if user is changing from recommendation
            if (this.recommended_category_id) {
              this.isRecommendationAccepted =
                this.categoryIdData === this.recommended_category_id;
            }

            if (!value) {
              this.learnRecommendation = false;
              this.onLearnRecommendationChange();
            }

            this.$emit('update:category_id', this.categoryIdData);
          },
        }),
      );

      // Prefer the saved category when editing; only auto-load recommendation
      // when there is no existing category. Not silent: the change handler
      // above emits the value and tracks the recommendation state.
      const shouldAutoLoadRecommendation =
        !this.category_id &&
        this.recommended_category_id &&
        (this.match_type === 'exact' ||
          (this.match_type === 'ai' &&
            this.confidence_score >= this.confidenceThreshold));

      if (this.category_id && this.category_full_name) {
        setSelected(this.categorySelect, {
          id: this.category_id,
          text: this.category_full_name,
        });
      } else if (
        shouldAutoLoadRecommendation &&
        this.recommended_category_full_name
      ) {
        setSelected(this.categorySelect, {
          id: this.recommended_category_id,
          text: this.recommended_category_full_name,
        });
      }

      this.tagSelect = markRaw(
        createLocalSelect(this.$el.querySelector('select.tag'), {
          suggestions: () => this.tagSuggestions(),
          search: (term) => this.searchTags(term),
          multiple: true,
          create: true,
          placeholder: __('Select tag(s)'),
          // Existing tags are sent as their ID, new ones as the typed text
          onChange: (values) => this.$emit('update:tags', values),
        }),
      );

      // Not silent: replaces the parent's {id, name} tag objects with the submitted value format
      if (this.tags.length > 0) {
        setSelected(
          this.tagSelect,
          this.tags.map((tag) => ({ id: tag.id, text: tag.name })),
        );
      }

      lockSelect(this.categorySelect, __('Loading…'));
      lockSelect(this.tagSelect, __('Loading…'));
      loadSelectData()
        .then((data) => {
          this.selectData = data;
        })
        .catch(() => {
          // Selects stay usable for presets; their lists are just empty
        })
        .finally(() => {
          // The item may have been removed while loading
          if (this.unmounted) {
            return;
          }
          unlockSelect(this.categorySelect, __('Select category'));
          unlockSelect(this.tagSelect, __('Select tag(s)'));
        });
    },

    beforeUnmount() {
      this.unmounted = true;
      this.categorySelect?.destroy();
      this.tagSelect?.destroy();
    },

    methods: {
      __,

      // After a failed preload the form's retry only reaches the form: pick the data up here on first use
      retryLoad() {
        // Backoff: every keystroke in a search calls this, so allow one attempt per 10s
        const now = Date.now();
        if (this.lastRetryAt && now - this.lastRetryAt < 10000) {
          return;
        }
        this.lastRetryAt = now;

        loadSelectData()
          .then((data) => {
            this.selectData = data;
            [this.categorySelect, this.tagSelect].forEach((select) => {
              if (!this.unmounted && select.isOpen) {
                select.load(select.control_input.value);
              }
            });
          })
          .catch(() => {});
      },

      // "Usual for this payee": the payee's default category, then its most used ones
      categorySuggestions() {
        if (!this.selectData) {
          this.retryLoad();
          return [];
        }
        const { categories, payees, usage } = this.selectData;
        const payee = this.payee
          ? payees.find((p) => p.id === Number(this.payee))
          : null;
        const items = suggestCategories(categories, usage, { payee }).map(
          (c) => ({ id: c.id, text: c.full_name }),
        );

        return [
          {
            id: 'usual',
            label: payee ? __('Usual for this payee') : __('Most used'),
            items,
          },
        ];
      },

      searchCategories(term) {
        if (!this.selectData) {
          this.retryLoad();
          return [];
        }
        const { categories, usage } = this.selectData;
        const payeeId = Number(this.payee);
        const deferred = new Set(
          usage.deferred_categories
            .filter((d) => d.payee_id === payeeId)
            .map((d) => d.category_id),
        );

        return searchEntities(
          categories
            .filter((c) => c.active && !deferred.has(c.id))
            .map((c) => ({ id: c.id, name: c.full_name, active: true })),
          term,
        ).map((c) => ({ id: c.id, text: c.name }));
      },

      // Tags have no usage ranking: an empty search lists the active ones by name
      tagSuggestions() {
        const tags = (this.selectData?.tags ?? [])
          .filter((t) => t.active)
          .sort((a, b) => a.name.localeCompare(b.name))
          .map((t) => ({ id: t.id, text: t.name }));

        return [{ id: 'tags', label: __('Tags'), items: tags }];
      },

      searchTags(term) {
        if (!this.selectData) {
          this.retryLoad();
          return [];
        }

        return searchEntities(
          this.selectData.tags.filter((t) => t.active),
          term,
        ).map((t) => ({ id: t.id, text: t.name }));
      },
      updateItemAmount: function (event) {
        this.$emit('updateItemAmount', event.target.value);
      },

      // Emit an event to instruct items container to remove this item
      removeItem() {
        this.$emit('removeItem');
      },

      // Toggle the visibility of event details (comment / tags)
      toggleItemDetails() {
        // A required tag field stays visible: its visibility is bound to tagRequired instead
        $(this.$el)
          .find('.transaction_detail_container')
          .not(this.tagRequired ? '.transaction_item_tags_container' : [])
          .toggleClass('d-none d-md-block');
      },

      // Add the currently available remainder amount to this item, using the
      // live bound amount (not the possibly-stale `amount` prop) and exact
      // decimal arithmetic - Decimal.plus() introduces no rounding error of
      // its own, so the result matches the exact remainder, with no digit
      // limit applied. Assigning amountData (rather than poking the DOM
      // directly) keeps Vue's own reactive state in sync and reuses the
      // existing amountData watcher to emit update:amount.
      loadRemainder() {
        this.amountData = new Decimal(this.amountData || 0)
          .plus(this.remainingAmount || 0)
          .toNumber();
      },

      /**
       * Remove suggestion without accepting it
       * Sets learnRecommendation to false
       */
      removeSuggestion() {
        // Mark that suggestion was removed
        this.suggestionRemoved = true;
        this.isRecommendationAccepted = false;

        // Disable learning from this suggestion
        this.learnRecommendation = false;
        this.onLearnRecommendationChange();

        // Clear the category field since we're rejecting the suggestion
        this.categoryIdData = null;

        // Emit the category change to parent component
        this.$emit('update:category_id', null);

        clearSelect(this.categorySelect, { silent: true });
      },

      /**
       * Accept a low-confidence or removed suggestion
       * Resets the "removed" flag and applies the recommendation
       */
      acceptSuggestion() {
        // Reset the removed flag
        this.suggestionRemoved = false;

        // Enable learning when accepting a suggestion
        this.learnRecommendation = true;
        this.onLearnRecommendationChange();

        // Apply the recommended category
        if (
          this.recommended_category_id &&
          this.recommended_category_full_name
        ) {
          // Not silent: the change handler emits the category and marks the recommendation accepted
          clearSelect(this.categorySelect, { silent: true });
          setSelected(this.categorySelect, {
            id: this.recommended_category_id,
            text: this.recommended_category_full_name,
          });
        }
      },

      /**
       * Handle learn recommendation toggle change
       * Emits event to parent component to update the learning flag
       */
      onLearnRecommendationChange() {
        this.$emit('update:learnRecommendation', {
          itemId: this.id,
          learnRecommendation: this.learnRecommendation,
        });
      },
    },
  };
</script>
