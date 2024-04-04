<template>
    <div
            class="card mb-3"
            id="onboardingCardContainer"
            v-show="ready"
    >
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">
                {{ cardTitle }}
            </div>
            <div>
                <button
                        class="btn-close"
                        :disabled="busy"
                        :title="__('Temporarly hide this widget')"
                        type="button"
                        @click="hide"
                ></button>
            </div>
        </div>
        <div class="card-body" v-if="cardBody">
            {{ cardBody }}
        </div>
        <ul class="list-group list-group-flush">
            <li
                    class="list-group-item"
                    v-for="(step, index) in onboardingSteps"
                    v-bind:key="index"
            >
                <div class="d-flex justify-content-between">
                    <span>
                        <i v-if="!step.complete" class="text-primary fa-regular fa-square me-2"></i>
                        <i v-if="step.complete" class="text-success fa-regular fa-square-check me-2"></i>
                        {{ step.title }}
                    </span>
                    <span v-show="!step.complete" class="text-end">
                        <a v-if="step.link && step.cta" :href="step.link">{{ step.cta }}</a>
                        <a v-if="step.link && step.icon" :href="step.link">
                            <button class="btn btn-sm btn-outline-primary">
                                <i :class="step.icon"></i>
                            </button>
                        </a>
                        <button
                                v-if="step.tour && step.icon"
                                class="btn btn-sm btn-outline-primary tour-trigger"
                        >
                            <i :class="step.icon"></i>
                        </button>
                    </span>
                </div>
            </li>
        </ul>
        <div class="card-footer d-flex justify-content-between">
            <div>
                <span v-show="onboardingComplete">
                  {{ completedMessage }}
                </span>
            </div>
            <button
                    class="btn btn-sm btn-outline-primary"
                    :disabled="busy"
                    :title="__('Never show this widget')"
                    type="button"
                    @click="dismiss"
            >
                {{ __('Dismiss') }}
            </button>
        </div>
    </div>
</template>

<script>
import * as helpers from '../../helpers';
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

export default {
    name: "OnboardingCard",

    props: {
        cardTitle: {
            type: String
        },
        cardBody: {
            type: String
        },
        completedMessage: {
            type: String
        },
        topic: {
            type: String
        }
    },

    data() {
        return {
            dismissed: false,
            onboardingSteps: [],
            busy: false,
            ready: false,
            tourData: undefined,
        }
    },

    created() {
        this.busy = true;
        let vue = this;

        axios.get('/api/onboarding/' + this.topic)
            .then(response => {
                vue.dismissed = response.data.dismissed;
                vue.onboardingSteps = response.data.steps;

                // Define the onboarding tour for the first step with a tour
                const tourData = response.data.steps.find(step => step.tour);
                if (tourData && window.onboardingTourSteps) {
                    vue.tourData = driver({
                        showProgress: true,
                        steps: window.onboardingTourSteps,
                        onDestroyStarted: () => {
                            if (!vue.tourData.hasNextStep()) {
                                axios.put(`/api/onboarding/${vue.topic}/complete-tour`)
                                    .then(() => {
                                        vue.onboardingSteps.forEach(step => {
                                            if (step.tour) {
                                                step.complete = true;
                                            }
                                        });
                                    });
                                vue.tourData.destroy();
                            } else {
                                vue.tourData.destroy();
                            }
                        },
                    });
                }
            })
            .finally(() => {
                if (vue.dismissed) {
                    vue.hide();
                    return
                }

                vue.ready = true;
                vue.busy = false;

                // Initialize the tour
                document.querySelector('.tour-trigger')
                    .addEventListener('click', function () {
                        vue.tourData.drive();
                    });
            });
    },

    methods: {
        hide() {
            document.getElementById('onboardingCardContainer').classList.add('hidden');
        },

        dismiss() {
            this.busy = true;
            axios.put(`/api/onboarding/${this.topic}/dismiss`)
                .then(() => this.hide());
        },
        /**
         * Import the translation helper function.
         */
        __: function (string, replace) {
            return helpers.__(string, replace);
        },
    },

    computed: {
        onboardingComplete() {
            return this.onboardingSteps
                /**
                 * @param {Object} step
                 * @property {Boolean} step.complete
                 */
                .filter(step => !step.complete)
                /**
                 * @param {Object} step
                 * @property {Boolean} step.excluded
                 */
                .filter(step => !step.excluded)
                .length === 0;
        }
    }
}
</script>
