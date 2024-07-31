<template>
    <div class="row">
        <div class="col-md-4">
            <div
                    class="card mb-3"
                    id="country"
            >
                <div class="card-header">
                    <div class="card-title">
                        {{ __('Select your country') }}
                    </div>
                </div>
                <div class="card-body">
                   <input
                            class="form-control mb-3"
                            id="search-country"
                            v-model="searchCountry"
                            :disabled="typeof this.country !== 'undefined'"
                            autocomplete="off"
                            :placeholder="__('Search for a country')"
                    >
                    <ul class="list-group">
                        <li
                                v-for="country in filteredCountries"
                                :key="country.iso2"
                                @click="selectCountry(country.iso2)"
                                class="list-group-item"
                                :class="{
                                    active: country.iso2 === this.country?.iso2,
                                    disabled: typeof this.country !== 'undefined'
                                }"
                        >
                            {{ country.name }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div
                    class="card mb-3"
                    id="institution"
                    v-show="typeof this.country !== 'undefined'"
            >
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Select your bank') }}
                    </div>
                    <div>
                        <button
                                class="btn btn-sm btn-ghost-danger"
                                @click="country = undefined"
                                :title="__('Back to country selection')"
                        >
                            <i class="fas fa-arrow-left"></i>
                        </button>
                    </div>
                </div>
                <ul class="list-group list-group-flush" v-if="institutionsLoading">
                    <li
                            aria-hidden="true"
                            class="list-group-item placeholder-glow"
                            v-for="i in 5"
                            v-bind:key="i"
                    >
                        <span class="placeholder col-12"></span>
                    </li>
                </ul>
                <div class="card-body" v-else-if="institutions.length === 0">
                    <div class="alert alert-warning">
                        {{ __('Sorry, no banks found for the selected country.') }}
                    </div>
                </div>
                <div class="card-body" v-else>
                    <div class="input-group mb-3">
                        <input
                                class="form-control"
                                id="search-bank"
                                v-model="searchInstitution"
                                autocomplete="off"
                                :placeholder="__('Search for a bank')"
                        >
                        <span class="input-group-text">
                            {{ filteredInstitutions.length }} / {{ institutions.length }}
                        </span>
                    </div>
                    <ul class="list-group">
                        <li
                                v-for="institution in filteredInstitutions"
                                :key="institution.id"
                                class="list-group-item"
                                @click="getInstitutionDetails(institution)"
                                :class="{
                                    active: institution.id === this.institution?.id,
                                    disabled: typeof this.institution !== 'undefined'
                                }"
                        >
                            {{ institution.name }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div
                    class="card mb-3"
                    id="requisition"
                    v-show="typeof this.institution !== 'undefined'"
            >
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Create a bank connection') }}
                    </div>
                    <button
                            class="btn btn-sm btn-ghost-danger"
                            @click="institution = undefined"
                            :title="__('Back to bank selection')"
                    >
                         <i class="fas fa-arrow-left"></i>
                    </button>
                </div>
                <ul class="list-group list-group-flush" v-if="institutionDetailLoading">
                    <li
                            aria-hidden="true"
                            class="list-group-item placeholder-glow"
                            v-for="i in 5"
                            v-bind:key="i"
                    >
                        <span class="placeholder col-12"></span>
                    </li>
                </ul>
                <div class="card-body" v-else>
                    <button
                            @click="selectInstitution(institution)"
                            class="btn btn-primary"
                    >
                        {{ __('Start authorization') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { getCountryDataList } from 'countries-list'
    import * as helpers from '../helpers'
    export default {
        name: 'CreateRequisition',
        data: () => ({
            searchCountry: '',
            searchInstitution: '',
            country: undefined,
            institution: undefined,
            institutions: [],
            institutionsLoading: false,
            institutionDetailLoading: false,
        }),
        mounted() {
            const queryParams = new URLSearchParams(window.location.search);
            const defaultCountry = queryParams.get('country');

            if (defaultCountry) {
                if (!getCountryDataList().find(country => country.iso2 === defaultCountry.toUpperCase())) {
                    return;
                }
                this.selectCountry(defaultCountry.toUpperCase());
            }
        },
        computed: {
            filteredCountries() {
                // Return an empty array if the search input is empty.
                // If the initial country is set, return that country.
                if (!this.searchCountry) {
                    if (!this.country) {
                        return [];
                    }

                    return getCountryDataList()
                        .filter(
                            country => country.iso2 === this.country.iso2
                        );
                }

                return getCountryDataList()
                    .filter(
                    country => country.name.toLowerCase().includes(this.searchCountry.toLowerCase())
                )
                    .slice(0, 10);
            },
            filteredInstitutions() {
                // Return the first 10 institutions if the search input is empty.
                if (!this.searchInstitution) {
                    return this.institutions.slice(0, 10);
                }

                return this.institutions
                    .filter(
                        institution => institution.name.toLowerCase().includes(this.searchInstitution.toLowerCase())
                    )
                    .slice(0, 10);
            },
        },
        methods: {
            selectCountry(iso2) {
                this.country = getCountryDataList().find(country => country.iso2 === iso2);
                this.getListOfInstitutions();
            },
            selectInstitution(institution) {
                //SANDBOXFINANCE_SFIN0000
                const url = '/api/gocardless/authentication-url/' + institution.id + '?institution_name='
                            + encodeURIComponent(institution.name);
                window.axios.get(url)
                    .then(response => {
                        console.log(response.data)
                        window.location.href = response.data.link;
                    })
                    .catch(error => {
                        console.error(error);
                    });
            },

            /**
             * Import the translation helper function.
             */
            __: function (string, replace) {
                return helpers.__(string, replace);
            },

            /**
             * Import the toast display helper function.
             */
            showToast: function (header, body, toastClass, otherProperties) {
                return helpers.showToast(header, body, toastClass, otherProperties);
            },
            getListOfInstitutions() {
                this.institutionsLoading = true;
                // Fetch the list of institutions for the selected country.
                window.axios.get(`/api/gocardless/institutions-by-country/${this.country.iso2}`)
                    .then(response => {
                        if (!response.data.status_code) {
                            this.institutions = response.data;
                        } else {
                            this.institutions = [];
                            this.showToast(
                                this.__('Error'),
                                response.data.country.summary,
                                'bg-danger',
                                {
                                    headerSmall: this.__('Failed to fetch the list of institutions.'),
                                }
                            );
                        }
                    })
                    .catch(error => {
                        console.error(error);
                    })
                    .finally(() => {
                        this.institutionsLoading = false;
                    });
            },

            getInstitutionDetails(institution) {
                this.institutionDetailLoading = true;
                this.institution = institution;

                window.axios.get(`/api/gocardless/institutions/${institution.id}`)
                    .then(response => {
                        console.log(response.data)
                    })
                    .catch(error => {
                        console.error(error);
                    })
                    .finally(() => {
                        this.institutionDetailLoading = false;
                    });
            },
        },
    }
</script>
