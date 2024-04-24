<template>
    <div
        class="list-group-item mb-2 transaction_item_row"
        :id="'transaction_item_' + id"
    >
        <div class="row">
            <div class="col-12 col-sm-4 form-group">
                <span class="form-label">
                    {{ __('Category') }}
                </span>
                <select
                    class="form-select category"
                    v-model.number="categoryIdData"
                >
                </select>
            </div>
            <div class="col-12 col-sm-2 form-group">
                <span class="form-label">
                    {{ __('Amount') }}
                    <span v-if="currency">({{currency}})</span>
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
                    ><span class="fa fa-copy"></span></button>
                </div>
            </div>
            <div class="col-12 col-sm-2 form-group transaction_detail_container d-none d-md-block">
                <span class="form-label">
                    {{ __('Tags') }}
                </span>
                <select
                    class="form-select tag"
                    multiple="multiple"
                    data-width="100%"
                    v-model="tagsData">
                </select>
            </div>
            <div class="col-12 col-sm-3 form-group transaction_detail_container d-none d-md-block">
                <span class="form-label">
                    {{ __('Comment') }}
                </span>
                <input
                    class="form-control transaction_item_comment"
                    v-model="commentData"
                    @blur="$emit('update:comment', $event.target.value)"
                    type="text">
            </div>
            <div class="col-12 col-sm-1">
                <button
                type="button"
                class="btn btn-sm btn-info d-sm-none"
                :title="__('Show item details')"
                @click="toggleItemDetails"
            ><span class="fa fa-edit"></span></button>
            <button
                type="button"
                class="btn btn-sm btn-danger"
                @click='removeItem'
                style="margin-left: 10px;"
                :title="__('Remove transaction item')"
            ><span class="fa fa-minus"></span></button>
            </div>
        </div>
    </div>
</template>

<script>
    require('select2');
    $.fn.select2.amd.define(
        'select2/i18n/' + window.YAFFA.language,
        [],
        require("select2/src/js/select2/i18n/" + window.YAFFA.language)
    );

    import MathInput from './MathInput.vue';
    import * as helpers from '../helpers';

    export default {
        components: {
            MathInput
        },

        props: {
            id: Number,
            amount: [Number, String],
            category_id: Number,
            category: Object,
            currency: String,
            comment: String,
            tags: Array,
            remainingAmount: Number,
            payee: [Number, String],
        },

        emits: [
            'update:amount',
            'update:category_id',
            'update:comment',
            'update:tags',
            'removeItem',
        ],

        data() {
            return {
                categoryIdData: this.category_id,
                amountData: this.amount,
                tagsData: this.tags,
                commentData: this.comment,
            };
        },

        mounted() {
            let $vm = this;

            // Add select2 functionality to category
            let elementCategory = $('#transaction_item_' + this.id + ' select.category');

            elementCategory.select2({
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
                    cache: true
                },
                selectOnClose: true,
                placeholder: __("Select category"),
                allowClear: true,
                // Component should not be aware where it is used, but we need to hint Select2
                dropdownParent: $(document.getElementById("modal-transaction-form-standard") || document.querySelector('body'))
            })
            .on('select2:select select2:unselect', function (e) {
                const event = new Event("change", { bubbles: true, cancelable: true });
                e.target.dispatchEvent(event);

                $vm.$emit('update:category_id', event.target.value);
            });

            // Load selected item for category select2
            if (this.category_id) {
                const data = this.category;

                const option = new Option(data.full_name, data.id, true, true);
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
            let elementTags = $('#transaction_item_' + this.id + ' select.tag');
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
                    let $result = $("<span></span>");

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
                placeholder: __('Select tag(s)'),
                allowClear: true,
                // Component should not be aware where it is used, but we need to hint Select2
                dropdownParent: $(document.getElementById("modal-transaction-form-standard") || document.querySelector('body'))
            })
            .on('select2:select select2:unselect', function (e) {
                const event = new Event("change", { bubbles: true, cancelable: true });
                e.target.dispatchEvent(event);

                $vm.$emit('update:tags', $(e.target).select2('val'));
            });

            // Add already existing tags as labels
            if (this.tags.length > 0) {
                let data = [];
                this.tags.forEach(function(tag) {
                    data.push({
                        id: tag.id,
                        name: tag.name,
                    });

                    const option = new Option(tag.name, tag.id, true, true);
                    elementTags.append(option).trigger('change');
                })

                // Manually trigger the `select2:select` event
                elementTags.trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
            }
        },

        methods: {
            updateItemAmount: function (event) {
                this.$emit('updateItemAmount', event.target.value);
            },

            // Emmit an event to instruct items container to remove this item
            removeItem() {
                this.$emit('removeItem')
            },

            // Toggle the visibility of event details (comment / tags)
            toggleItemDetails() {
                $(this.$el).find(".transaction_detail_container").toggleClass('d-none d-md-block');
            },

            // Add the currently available remainder amount to this item
            loadRemainder() {
                const element = $(this.$el).find("input.transaction_item_amount");
                const amount = (this.amount || 0) + this.remainingAmount;

                element.val(amount);
                this.$emit('update:amount', amount);
            },

            /**
             * Import the translation helper function.
             */
            __: function (string, replace) {
                return helpers.__(string, replace);
            },
        },

        watch: {
            amountData (newAmount) {
                this.$emit('update:amount', newAmount);
            }
        }
    }
</script>
