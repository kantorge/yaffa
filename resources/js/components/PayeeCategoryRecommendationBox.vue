<template>
    <transition name="fade">
        <div ref="PayeeCategoryRecommendationBox" class="box box-info" v-if="payeeSuggestion">
            <div class="box-header with-border">
                <h3 class="box-title">Tip on your payee!</h3>

                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
                </div>
                <!-- /.box-tools -->
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <p>
                    Your payee <strong><a :href="editlink">{{ payeeSuggestion.payee }}</a></strong>
                    used category <strong>{{ payeeSuggestion.category }}</strong>
                    {{ payeeSuggestion.max }} times out of {{ payeeSuggestion.sum }} transactions.
                </p>
                <p>
                    It might be a good idea to set it as default category.
                </p>
                <div class="btn-toolbar" v-if="!success">
                    <button class="btn btn-success" title="Accept recommendation and set it as default category for payee" @click="accept" :disabled="busy">
                        OK, let's do it!
                    </button>
                    <button class="btn btn-primary" title="Hide this recommendation" @click="hide" :disabled="busy">
                        Maybe later
                    </button>
                    <button class="btn btn-default" title="Don't show category recommendations for this payee any more" @click="dismiss" :disabled="busy">
                        No, thanks
                    </button>
                </div>
            </div>
            <!-- /.box-body -->
            <div class="box-footer" v-if="error || success">
                <span class="text-danger" v-if="error">Something failed</span>
                <span class="text-success" v-if="success">Saved successfully</span>
            </div>
            <!-- box-footer -->
        </div>
    </transition>
</template>

<script>
    export default {
        data() {
            return {
                payeeSuggestion: null,
                error: false,
                busy: false,
                success: false,
            }
        },

        created() {
            axios.get('/api/assets/get_default_category_suggestion')
            .then(response => this.payeeSuggestion = response.data)
        },

        methods: {
            accept() {
                this.busy = true;
                let vue = this;

                axios.get('/api/assets/accept_default_category_suggestion/' + this.payeeSuggestion.payee_id + '/' + this.payeeSuggestion.max_category_id)
                .then(function() {
                     vue.success = true;
                     vue.error = false;
                })
                .catch(function() {
                     vue.success = false;
                     vue.error = true;
                })

                this.busy = false;
            },

            dismiss() {
                this.busy = true;
                let vue = this;

                axios.get('/api/assets/dismiss_default_category_suggestion/' + this.payeeSuggestion.payee_id)
                .then(this.hide())
                .catch(function() {
                     vue.success = false;
                     vue.error = true;
                })

                this.busy = false;
            },

            hide() {
                $(this.$refs.PayeeCategoryRecommendationBox).boxWidget('remove');
            }
        },

        computed: {
            editlink() {
                return route('account-entity.edit', {type: 'payee', account_entity: this.payeeSuggestion.payee_id});
            }
        }
    }
</script>

<style scoped>
    .fade-enter-active, .fade-leave-active {
        transition: opacity .5s ease;
    }

    .fade-enter-from, .fade-leave-to {
        opacity: 0;
    }
</style>
