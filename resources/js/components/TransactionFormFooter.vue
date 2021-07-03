<template>
    <footer class="main-footer navbar-fixed-bottom hidden">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-2">
                    <label class="control-label">After saving</label>
                </div>
                <div class="col-sm-8">
                    <div class="btn-group">
                        <button
                            class="btn btn-default"
                            :class="callback == 'new' ? 'active' : ''"
                            type="button"
                            value="new"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Add an other transaction
                        </button>
                        <button
                            class="btn btn-default"
                            :class="callback == 'clone' ? 'active' : ''"
                            type="button"
                            value="clone"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Clone this transaction
                        </button>
                        <button
                            class="btn btn-default"
                            :class="callback == 'returnToAccount' ? 'active' : ''"
                            type="button"
                            value="returnToAccount"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Return to selected account
                        </button>
                        <button
                            class="btn btn-default"
                            :class="callback == 'returnToDashboard' ? 'active' : ''"
                            type="button"
                            value="returnToDashboard"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Return to dashboard
                        </button>
                    </div>
                </div>
                <div class="box-tools col-sm-2">
                    <div class="pull-right">
                        <button class="btn btn-sm btn-default" type="button" @click="onCancel">
                            Cancel
                        </button>
                        <Button class="btn btn-primary" :form="form">Save</Button>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>

<script>
    export default {
        props: {
            callback: String,
        }
    }
</script>
