<template>
    <div class="list-group-item transaction_item_row">
        <div class="row">
            <div class="col-md-6">
                <div
                    class="form-group"
                >
                    <label>Category</label>
                    <select
                        class="form-control category"
                        style="width:100%"
                        v-model.number="category_id"
                    >
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label class="control-label">
                        Amount
                        <span v-if="currency">({{currency}})</span>
                    </label>
                    <div class="input-group">
                        <MathInput
                            class="form-control transaction_item_amount"
                            v-model="amount"
                        ></MathInput>

                        <span class="input-group-btn">
                            <button
                                type="button"
                                class="btn btn-info load_remainder"
                                title="Assign remaining amount to this item"
                                @click="loadRemainder"
                            ><i class="fa fa-copy"></i></button>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button
                        type="button"
                        class="btn btn-info"
                        title="Show item details"
                        @click="toggleItemDetails"
                    >
                        <i class="fa fa-edit"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button
                        type="button"
                        class="btn btn-danger"
                        @click='removeTransactionItem'
                        title="Remove transaction item"><i class="fa fa-minus"></i></button>
                </div>
            </div>
        </div>
        <div class="row transaction_detail_container" style="display:none;">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Comment</label>
                    <input
                        class="form-control transaction_item_comment"
                        v-model="comment"
                        @blur="updateComment"
                        type="text">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Tags</label>
                    <select
                        style="width: 100%"
                        class="form-control tag"
                        multiple="multiple"
                        v-model="tags">
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    require('select2');

    import MathInput from './MathInput.vue'

    export default {
        components: {
            MathInput
        },

        props: {
            index: Number,
            amount: [Number, String],
            category_id: Number,
            category: Object,
            currency: String,
            comment: String,
            tags: Array,
            remainingAmount: Number,
        },

        emits: ['amountChanged'],

        data() {
            return {};
        },

        mounted() {
            let $vm = this;

            // Add select2 functionality to category
            let elementCategory = $(this.$el).find('select.category');

            elementCategory.select2({
                ajax: {
                    url: '/api/assets/category',
                    dataType: 'json',
                    delay: 150,
                    data: function (params) {
                        return {
                        q: params.term,
                        active: 1,
                        payee: null //TODO: apply payee (from parent) if present
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data,
                        };
                    },
                    cache: true
                },
                selectOnClose: true,
                placeholder: "Select category",
                allowClear: true
            })
            .on('select2:select', function (e) {
                const event = new Event("change", { bubbles: true, cancelable: true });
                e.target.dispatchEvent(event);

                $vm.$emit('updateItemCategory', event.target.value);
            });

            // Load selected item for category select2
            if (this.category_id) {
                const data = this.category;

                var option = new Option(data.full_name, data.id, true, true);
                elementCategory.append(option).trigger('change');

                // Manually trigger the `select2:select` event
                elementCategory.trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
            }

            // Add select2 functionality to tag
            let elementTags = $(this.$el).find('select.tag');
            elementTags.select2({
                tags: true,
                createTag: function (params) {
                    return {
                    id: params.term,
                    text: params.term,
                    newOption: true
                    }
                },
                insertTag: function (data, tag) {
                    // Insert the tag at the end of the results
                    data.push(tag);
                },
                templateResult: function (data) {
                    var $result = $("<span></span>");

                    $result.text(data.text);

                    if (data.newOption) {
                    $result.append(" <em>(new)</em>");
                    }

                    return $result;
                },
                ajax: {
                    url:  '/api/assets/tag',
                    dataType: 'json',
                    delay: 150,
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                //selectOnClose: true,
                placeholder: "Select tag(s)",
                allowClear: true
            })
            .on('select2:select', function (e) {
                const event = new Event("change", { bubbles: true, cancelable: true });
                e.target.dispatchEvent(event);

                $vm.$emit('updateItemTag', $(e.target).select2('val'));
            });

        },

        methods: {
            updateAmount: function (event) {
                console.log('item amount update:', event);
                this.$emit('amountChanged', event.target.value);
            },

            // Emmit an event to have the parent container update the value
            updateComment: function (event) {
                this.$emit('updateItemComment', event.target.value);
            },

            // Emmit an event to instruct items container to remove this item
            removeTransactionItem() {
                this.$emit('removeTransactionItem')
            },

            // Toggle the visibility of event details (comment / tags)
            toggleItemDetails() {
                $(this.$el).find(".transaction_detail_container").toggle();
            },

            // Add the currently available remainder amount to this item
            loadRemainder() {
                var element = $(this.$el).find("input.transaction_item_amount");
                var amount = this.amount + this.remainingAmount;

                element.val(amount);
                this.$emit('updateItemAmount', amount);
            }
        },

        watch: {
            amount (newAmount) {
                this.$emit('amountChanged', newAmount);
            }
        }
    }
</script>
