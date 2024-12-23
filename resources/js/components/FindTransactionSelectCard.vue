<template>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">
                {{ __(title) }}
            </div>
            <div>
                <button
                        class="btn btn-sm btn-ghost-danger"
                        @click="clearSelection"
                        :disabled="selectedValues.length === 0 || !ready"
                        :title="__('Clear selection')"
                >
                    <i class="fa fa-fw fa-times"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <select :id="elementId" class="form-select"></select>
        </div>
    </div>
</template>

<script>
import { __ as translator } from "../helpers"

require('select2');
$.fn.select2.amd.define(
    'select2/i18n/' + window.YAFFA.language,
    [],
    require("select2/src/js/select2/i18n/" + window.YAFFA.language)
);

export default {
    name: 'FindTransactionSelectCard',
    emits: ['update', 'preset-ready'],
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
            default: "Select item",
        },
        // API path for the search endpoint
        searchApiPath: {
            type: String,
            required: true
        },
        // Field to use as label in the search results
        search_label_field: {
            type: String,
            default: 'name'
        },
        presetItemIds: {
            type: Array,
            default: []
        },
        // API path for the details endpoint
        detailsApiPath: {
            type: String,
            required: true
        },
        detailsLabelField: {
            type: String,
            default: 'name'
        },
    },

    data() {
        return {
            itemsToPreset: this.presetItemIds.map(id => id),
            selectedValues: [],
            elementId: `select_${this.property}`,
            elementSelector: `#select_${this.property}`,
        }
    },

    computed: {
        ready() {
            return this.itemsToPreset.length === 0;
        }
    },

    mounted() {
        const vue = this;

        // Initialize the select2 plugin
        $(this.elementSelector).select2({
            theme: "bootstrap-5",
            multiple: true,
            ajax: {
                url: this.searchApiPath,
                dataType: 'json',
                delay: 150,
                data: function (params) {
                    return {
                        q: params.term,
                        withInactive: true,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.map(function(data) {
                            return {
                                id: data.id,
                                text: data[vue.search_label_field],
                            }
                        }),
                    };
                },
                cache: true
            },
            selectOnClose: false,
            placeholder: __(this.placeholder),
            allowClear: true
        })
        // Attach select2 event listeners, emit events for parent component
        .on('select2:select select2:unselect', function (e) {
            const event = new Event("change", { bubbles: true, cancelable: true });
            e.target.dispatchEvent(event);

            vue.selectedValues = $(e.target).select2('val');

            vue.$emit(
                `update`,
                $(e.target).select2('val')
            );
        });

        // Append preset items, if any
        if (this.presetItemIds.length > 0) {
            this.presetItemIds.forEach(function(item) {
                $.ajax({
                    url: vue.detailsApiPath.replace('#id#', item),
                    data: {},
                    success: function(data) {
                        $(vue.elementSelector)
                            .append(new Option(data[vue.detailsLabelField], data.id, true, true))
                            .trigger('change')
                            .trigger({
                                type: 'select2:select',
                                params: {
                                    data: {
                                        id: data.id,
                                        name: data[vue.detailsLabelField],
                                    }
                                }
                            });

                        // Remove item from itemsToPreset
                        vue.itemsToPreset = vue.itemsToPreset.filter(id => id !== item);

                        if (vue.itemsToPreset.length === 0) {
                            vue.$emit('preset-ready', vue.property)
                        }
                    }
                });
            });
        } else {
            this.$emit('preset-ready', this.property)
        }
    },

    methods: {
        clearSelection: function() {
            $(this.elementSelector).val(null).trigger('change');

            this.selectedValues = [];

            this.$emit(
                `update`,
                this.selectedValues
            );
        },
        /**
         * Define the translation helper function locally.
         */
        __: function (string, replace) {
            return translator(string, replace);
        },
    }


}
</script>
