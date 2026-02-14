<template>
  <div
    class="list-group-item mb-2 transaction_item_row"
    :id="'transaction_item_' + id"
  >
    <!-- Item description banner for AI recommendations -->
    <div
      v-if="recommended_category_id || description"
      class="alert mb-2 py-2 px-3 d-flex justify-content-between align-items-center"
      :class="aiAlertClass"
    >
      <div>
        <i class="fa fa-receipt me-2"></i>
        <strong>{{ __('Item') }}:</strong>
        {{ description || recommended_category.full_name }}
      </div>
      <div class="d-flex align-items-center gap-2">
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
          @click="removeSuggestion"
          style="white-space: nowrap"
        >
          <i class="fa fa-trash me-1"></i>{{ __('Remove') }}
        </button>

        <!-- Add button for low-confidence or no match -->
        <button
          v-if="showAddButton"
          type="button"
          class="btn btn-sm btn-outline-success"
          :title="__('Accept suggestion')"
          @click="acceptSuggestion"
          style="white-space: nowrap"
        >
          <i class="fa fa-check me-1"></i>{{ __('Add') }}
        </button>

        <!-- AI suggested badge (generic indicator) -->
        <span
          v-if="
            recommended_category_id &&
            match_type !== 'exact' &&
            !showRemoveButton
          "
          class="badge bg-primary"
        >
          <i class="fa fa-robot me-1"></i>{{ __('AI') }}
        </span>
      </div>
    </div>

    <div class="row">
      <div class="col-12 col-sm-4 form-group">
        <span class="form-label">
          {{ __('Category') }}
          <span
            v-if="recommended_category_id && isRecommendationAccepted"
            class="badge bg-success text-white ms-1"
            :title="__('Using AI recommendation')"
          >
            <i class="fa fa-check me-1"></i>{{ __('AI') }}
          </span>
          <span
            v-else-if="recommended_category_id && !isRecommendationAccepted"
            class="badge bg-warning text-dark ms-1"
            :title="__('AI recommendation overridden')"
          >
            <i class="fa fa-edit me-1"></i>{{ __('Modified') }}
          </span>
        </span>
        <select
          class="form-select category"
          v-model.number="categoryIdData"
        ></select>
      </div>
      <div class="col-12 col-sm-2 form-group">
        <span class="form-label">
          {{ __('Amount') }}
          <span v-if="currencySymbol">({{ currencySymbol }})</span>
        </span>
        <div class="input-group">
          <MathInput
            class="form-control transaction_item_amount"
            v-model="amountData"
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
        class="col-12 col-sm-2 form-group transaction_detail_container d-none d-md-block"
      >
        <span class="form-label">
          {{ __('Tags') }}
        </span>
        <select
          class="form-select tag"
          multiple="multiple"
          data-width="100%"
          v-model="tagsData"
        ></select>
      </div>
      <div
        class="col-12 col-sm-3 form-group transaction_detail_container"
        :class="{ 'd-none d-md-block': !recommended_category_id }"
      >
        <span class="form-label">
          {{ __('Comment') }}
          <span v-if="recommended_category_id" class="text-muted small">
            ({{ __('AI extracted') }})
          </span>
        </span>
        <input
          class="form-control transaction_item_comment"
          v-model="commentData"
          @blur="$emit('update:comment', $event.target.value)"
          type="text"
          :placeholder="
            recommended_category_id
              ? __('Edit item description')
              : __('Add comment')
          "
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
          @click="removeItem"
          style="margin-left: 10px"
          :title="__('Remove transaction item')"
        >
          <span class="fa fa-minus"></span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
  import MathInput from '@components/MathInput.vue';
  import { __, loadSelect2Language } from '@/helpers';

  import select2 from 'select2';
  select2();
  loadSelect2Language(window.YAFFA.language);

  export default {
    components: {
      MathInput,
    },

    props: {
      id: Number,
      amount: [Number, String],
      category_id: Number,
      category: Object,
      recommended_category_id: Number,
      recommended_category: Object,
      description: String,
      currencySymbol: String,
      comment: String,
      tags: Array,
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
      dropdownParentSelector: {
        type: String,
        default: 'body',
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
      'update:dontLearn',
    ],

    data() {
      return {
        categoryIdData: this.category_id,
        amountData: this.amount,
        tagsData: this.tags,
        commentData: this.comment,
        isRecommendationAccepted: false,
        suggestionRemoved: false,
      };
    },

    computed: {
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

      /**
       * Show remove button for high-confidence AI matches or exact matches
       * Exact: auto-selected with no button initially, but show if user tries to change
       * High confidence (≥0.7): auto-selected + remove button to reject
       */
      showRemoveButton() {
        // Only show if there's a recommendation that was auto-accepted
        if (!this.recommended_category_id) {
          return false;
        }

        // If suggestion was removed, don't show remove button anymore
        if (this.suggestionRemoved) {
          return false;
        }

        // Show for exact matches or high confidence AI matches
        if (this.match_type === 'exact') {
          return false;
        }

        if (
          this.match_type === 'ai' &&
          this.confidence_score >= this.confidenceThreshold
        ) {
          return true;
        }

        return false;
      },

      /**
       * Show add button for low-confidence suggestions or no match at all
       * Low confidence (<0.7): show button to accept
       * No match: no button (don't suggest something that wasn't AI-suggested)
       */
      showAddButton() {
        // Show button if suggestion was removed and user might want to re-accept it
        if (this.suggestionRemoved && this.recommended_category_id) {
          return true;
        }

        // Show button for low-confidence AI suggestions
        if (
          this.match_type === 'ai' &&
          this.confidence_score !== null &&
          this.confidence_score < this.confidenceThreshold &&
          !this.suggestionRemoved
        ) {
          return true;
        }

        // Don't show button if there was no AI recommendation at all
        return false;
      },
    },

    mounted() {
      let $vm = this;

      // Add select2 functionality to category
      let elementCategory = $(
        '#transaction_item_' + this.id + ' select.category',
      );

      elementCategory
        .select2({
          language: window.YAFFA.language,
          theme: 'bootstrap-5',
          ajax: {
            url: '/api/assets/category',
            dataType: 'json',
            delay: 150,
            data: function (params) {
              return {
                q: params.term,
                payee: $vm.payee,
              };
            },
            processResults: function (data) {
              return {
                results: data,
              };
            },
            cache: true,
          },
          selectOnClose: true,
          placeholder: __('Select category'),
          allowClear: true,
          dropdownParent: $($vm.dropdownParentSelector),
        })
        .on('select2:select select2:unselect', function (e) {
          const event = new Event('change', {
            bubbles: true,
            cancelable: true,
          });
          e.target.dispatchEvent(event);

          // Track if user is changing from recommendation
          const newValue = event.target.value;
          if ($vm.recommended_category_id) {
            $vm.isRecommendationAccepted =
              newValue == $vm.recommended_category_id;
          }

          $vm.$emit('update:category_id', event.target.value);
        });

      // Load selected item for category select2
      // Priority: explicit category_id > recommended_category_id (if confidence permits)
      const shouldAutoLoadRecommendation =
        this.recommended_category_id &&
        (this.match_type === 'exact' ||
          (this.match_type === 'ai' &&
            this.confidence_score >= this.confidenceThreshold));

      const effectiveCategoryId =
        this.category_id ||
        (shouldAutoLoadRecommendation ? this.recommended_category_id : null);
      const effectiveCategory =
        this.category ||
        (shouldAutoLoadRecommendation ? this.recommended_category : null);

      if (effectiveCategoryId && effectiveCategory) {
        const data = effectiveCategory;

        const option = new Option(data.full_name, data.id, true, true);
        elementCategory.append(option).trigger('change');

        // Manually trigger the `select2:select` event
        elementCategory.trigger({
          type: 'select2:select',
          params: {
            data: data,
          },
        });

        // Track if we're using the recommendation
        if (!this.category_id && shouldAutoLoadRecommendation) {
          this.isRecommendationAccepted = true;
          this.categoryIdData = this.recommended_category_id;
        }
      }

      // Add select2 functionality to tag
      let elementTags = $('#transaction_item_' + this.id + ' select.tag');
      elementTags
        .select2({
          tags: true,
          createTag: function (params) {
            return {
              id: params.term,
              text: params.term,
              newOption: true,
            };
          },
          insertTag: function (data, tag) {
            // Insert the tag at the end of the results
            data.push(tag);
          },
          templateResult: function (data) {
            let $result = $('<span></span>');

            $result.text(data.text);

            if (data.newOption) {
              $result.append(' <em>(new)</em>');
            }

            return $result;
          },
          ajax: {
            url: '/api/assets/tag',
            dataType: 'json',
            delay: 150,
            processResults: function (data) {
              return {
                results: data,
              };
            },
            cache: true,
          },
          placeholder: __('Select tag(s)'),
          allowClear: true,
          dropdownParent: $($vm.dropdownParentSelector),
        })
        .on('select2:select select2:unselect', function (e) {
          const event = new Event('change', {
            bubbles: true,
            cancelable: true,
          });
          e.target.dispatchEvent(event);

          $vm.$emit('update:tags', $(e.target).select2('val'));
        });

      // Add already existing tags as labels
      if (this.tags.length > 0) {
        let data = [];
        this.tags.forEach(function (tag) {
          data.push({
            id: tag.id,
            name: tag.name,
          });

          const option = new Option(tag.name, tag.id, true, true);
          elementTags.append(option).trigger('change');
        });

        // Manually trigger the `select2:select` event
        elementTags.trigger({
          type: 'select2:select',
          params: {
            data: data,
          },
        });
      }
    },

    methods: {
      __,
      updateItemAmount: function (event) {
        this.$emit('updateItemAmount', event.target.value);
      },

      // Emit an event to instruct items container to remove this item
      removeItem() {
        this.$emit('removeItem');
      },

      // Toggle the visibility of event details (comment / tags)
      toggleItemDetails() {
        $(this.$el)
          .find('.transaction_detail_container')
          .toggleClass('d-none d-md-block');
      },

      // Add the currently available remainder amount to this item
      loadRemainder() {
        const element = $(this.$el).find('input.transaction_item_amount');
        const amount = (this.amount || 0) + this.remainingAmount;

        element.val(amount);
        this.$emit('update:amount', amount);
      },

      /**
       * Remove suggestion without accepting it
       * Marks the item so it doesn't learn from this suggestion
       */
      removeSuggestion() {
        // Mark that suggestion was removed
        this.suggestionRemoved = true;

        // Clear the category field since we're rejecting the suggestion
        this.categoryIdData = null;

        // Emit event to indicate we don't want to learn from this
        this.$emit('update:dontLearn', { itemId: this.id, dontLearn: true });

        // Emit the category change
        this.$emit('update:category_id', null);
      },

      /**
       * Accept a low-confidence or removed suggestion
       * Resets the "removed" flag and applies the recommendation
       */
      acceptSuggestion() {
        // Reset the removed flag
        this.suggestionRemoved = false;

        // Apply the recommended category
        if (this.recommended_category_id) {
          this.categoryIdData = this.recommended_category_id;
          this.isRecommendationAccepted = true;

          // Re-initialize select2 with the category
          const $category = $(
            '#transaction_item_' + this.id + ' select.category',
          );
          const option = new Option(
            this.recommended_category.full_name,
            this.recommended_category.id,
            true,
            true,
          );
          $category.append(option).trigger('change');

          // Emit the category change
          this.$emit('update:category_id', this.recommended_category_id);

          // Clear the "don't learn" flag
          this.$emit('update:dontLearn', { itemId: this.id, dontLearn: false });
        }
      },
    },

    watch: {
      amountData(newAmount) {
        this.$emit('update:amount', newAmount);
      },
    },
  };
</script>
