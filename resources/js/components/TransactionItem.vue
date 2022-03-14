<template>
    <div
        class="list-group-item transaction_item_row"
        :id="'transaction_item_' + id"
    >
        <div class="row">
            <div
                class="col-xs-12 col-sm-4 form-group"
            >
                <label>Category</label>
                <select
                    class="form-control category"
                    style="width:100%"
                    v-model.number="category_id"
                >
                </select>
            </div>
            <div class="col-xs-12 col-sm-2 form-group">
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
                        ><span class="fa fa-copy"></span></button>
                    </span>
                </div>
            </div>
            <div class="col-xs-12 col-sm-3 form-group transaction_detail_container d-xs-none">
                <label class="control-label">Tags</label>
                <select
                    style="width: 100%"
                    class="form-control tag"
                    multiple="multiple"
                    v-model="tags">
                </select>
            </div>
            <div class="col-xs-12 col-sm-2 form-group transaction_detail_container d-xs-none">
                <label class="control-label">Comment</label>
                <input
                    class="form-control transaction_item_comment"
                    v-model="comment"
                    @blur="updateComment"
                    type="text">
            </div>
            <div class="col-xs-12 col-sm-1">
                <button
                type="button"
                class="btn btn-sm btn-info d-sm-none"
                title="Show item details"
                @click="toggleItemDetails"
            ><span class="fa fa-edit"></span></button>
            <button
                type="button"
                class="btn btn-sm btn-danger"
                @click='removeItem'
                style="margin-left: 10px;"
                title="Remove transaction item"
            ><span class="fa fa-minus"></span></button>
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
            'updateItemAmount',
            'updateItemCategory',
            'updateItemComment',
            'updateItemTag',
            'removeItem',
        ],

        data() {
            return {};
        },

        mounted() {
            let $vm = this;

            // Add select2 functionality to category
            let elementCategory = $('#transaction_item_' + this.id + ' select.category');

            elementCategory.select2({
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
            .on('select2:select select2:unselect', function (e) {
                const event = new Event("change", { bubbles: true, cancelable: true });
                e.target.dispatchEvent(event);

                $vm.$emit('updateItemTag', $(e.target).select2('val'));
            });

            // Add already existing tags as labels
            if (this.tags.length > 0) {
                let data = [];
                this.tags.forEach(function(tag) {
                    data.push({
                        id: tag.id,
                        name: tag.name,
                    });

                    var option = new Option(tag.name, tag.id, true, true);
                    elementTags.append(option).trigger('change');
                })

                // Manually trigger the `select2:select` event
                elementTags.trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
                /*
                elementTags.select2(
                    'data',
                    this.tags.map(function(tag) {
                        return {
                            id: tag.id,
                            tag: tag.name,
                        }
                    })
                );
                */
                //console.log(elementTags.select2('data'));
            }
        },

        methods: {
            updateItemAmount: function (event) {
                this.$emit('updateItemAmount', event.target.value);
            },

            // Emmit an event to have the parent container update the value
            updateComment: function (event) {
                this.$emit('updateItemComment', event.target.value);
            },

            // Emmit an event to instruct items container to remove this item
            removeItem() {
                this.$emit('removeItem')
            },

            // Toggle the visibility of event details (comment / tags)
            toggleItemDetails() {
                $(this.$el).find(".transaction_detail_container").toggleClass('d-xs-none');
            },

            // Add the currently available remainder amount to this item
            loadRemainder() {
                var element = $(this.$el).find("input.transaction_item_amount");
                var amount = (this.amount || 0) + this.remainingAmount;

                element.val(amount);
                this.$emit('updateItemAmount', amount);
            }
        },

        watch: {
            amount (newAmount) {
                this.$emit('updateItemAmount', newAmount);
            }
        }
    }
</script>

<style scoped>
    /* TODO: decide on highlighting */
    TBD.input.transaction_item_amount:focus {
        width: 200%;
        left: -100%;
        box-shadow: -4px 3px 4px;
    }

    @media (min-width: 576px) {
        .d-sm-none {
            display: none;
        }
    }
    @media (max-width: 575.98px) {
        .d-xs-none {
            display: none;
        }
    }
</style>
