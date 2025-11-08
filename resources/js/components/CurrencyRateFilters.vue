<template>
    <div class="card mb-3">
        <div class="card-header">
            <div
                class="card-title collapse-control"
                data-coreui-toggle="collapse"
                data-coreui-target="#cardFilters"
            >
                <i class="fa fa-angle-down"></i>
                {{ __('Filters') }}
            </div>
        </div>
        <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardFilters">
            <li class="list-group-item">
                <label for="table_filter_search_text" class="form-label">{{ __('Search') }}</label>
                <input
                    type="text"
                    class="form-control"
                    id="table_filter_search_text"
                    :placeholder="__('Search')"
                    v-model="searchText"
                    @input="onSearchChange"
                >
            </li>
        </ul>
    </div>
</template>

<script>
export default {
    name: 'CurrencyRateFilters',
    emits: ['search-change'],
    data() {
        return {
            searchText: '',
        };
    },
    methods: {
        onSearchChange() {
            this.$emit('search-change', this.searchText);
        },
        __: function (string, replace) {
            return window.__(string, replace);
        },
    },
};
</script>
