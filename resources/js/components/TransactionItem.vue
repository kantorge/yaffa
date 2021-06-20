<template>
    <div class="list-group-item transaction_item_row">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Category</label>
                    <select
                        class="form-control category"
                        :name="'transactionItems[' + index + '][category_id]'"
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
                        <input
                            class="form-control transaction_item_amount"
                            :name="'transactionItems[' + index + '][amount]'"
                            type="text"
                            v-model.number="amount"
                            @blur="updateAmount"
                        >
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-info load_remainder" title="Assign remaining amount to this item"><i class="fa fa-copy"></i></button>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-info toggle_transaction_detail" title="Show item details"><i class="fa fa-edit"></i></button>
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
                        :name="'transactionItems[' + index + '][comment]'"
                        v-model="comment"
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
                        :name="'transactionItems[' + index + '][tags][]'">
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    let math = require("mathjs");

    export default {
        props: {
            index: Number,
            amount: Number,
            category_id: Number,
            category: Object,
            currency: String,
            comment: String,
            tags: Array,
        },

        data() {
            return {};
        },

        mounted() {
            // Add select2 functionality to category
            let elementCategory = $("select[name='transactionItems[" + this.index + "][category_id]']");
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
                            results: data
                        };
                    },
                    cache: true
                },
                selectOnClose: true,
                placeholder: "Select category",
                allowClear: true
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
            let elementTags = $("select[name='transactionItems[" + this.index + "][tags][]']");
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
            });

        },

        methods: {
            updateAmount: function (event) {
                let amount = math.evaluate(event.target.value.replace(/\s/g,""));

                if(amount <= 0) throw Error("Positive number expected");

                event.target.value = amount;
                this.$emit('updateItemAmount', amount);
            },
            removeTransactionItem() {
                this.$emit('removeTransactionItem')
            }
        }
    }
</script>
