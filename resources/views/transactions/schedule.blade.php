                    <!-- schedule settings -->
                    <div class="box" id="schedule_container">
                        <div class="box-header with-border">
                            <h3 class="box-title">Schedule</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body" id="">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="schedule_frequency" class="control-label">Frequency</label>
                                    <select
                                        class="form-control valid"
                                        id="schedule_frequency"
                                        name="schedule_frequency"
                                    >
                                        <option value="DAILY" {{ (old("schedule_frequency", $transaction['transactionSchedule']['frequency'] ?? '') == "DAILY" ? "selected":"") }}>Daily</option>
                                        <option value="WEEKLY" {{ (old("schedule_frequency", $transaction['transactionSchedule']['frequency'] ?? '') == "WEEKLY" ? "selected":"") }}>Weekly</option>
                                        <option value="MONTHLY" {{ (old("schedule_frequency", $transaction['transactionSchedule']['frequency'] ?? '') == "MONTHLY" ? "selected":"") }}>Monthly</option>
                                        <option value="YEARLY" {{ (old("schedule_frequency", $transaction['transactionSchedule']['frequency'] ?? '') == "YEARLY" ? "selected":"") }}>Yearly</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="schedule_count" class="control-label">Count</label>
                                    <input
                                        class="form-control"
                                        id="schedule_count"
                                        name="schedule_count"
                                        type="text"
                                        value="{{old('schedule_count', $transaction['transactionSchedule']['count'] ?? '')}}"
                                    >
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="schedule_interval" class="control-label">Count</label>
                                    <input
                                        class="form-control"
                                        id="schedule_interval"
                                        name="schedule_interval"
                                        type="text"
                                        value="{{old('schedule_interval', $transaction['transactionSchedule']['interval'] ?? '')}}"
                                    >
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <label for="schedule_start" class="control-label">Start date</label>
                                    <input
                                        class="form-control"
                                        id="schedule_start"
                                        name="schedule_start"
                                        type="text"
                                        value="{{old('schedule_start', $transaction['transactionSchedule']['start_date'] ?? '')}}"
                                    >
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="schedule_next" class="control-label">Next date</label>
                                    <input
                                        class="form-control"
                                        id="schedule_next"
                                        name="schedule_next"
                                        type="text"
                                        value="{{old('schedule_next', $transaction['transactionSchedule']['next_date'] ?? '')}}"
                                    >
                                </div>
                                <div class="col-md-4">
                                    <label for="schedule_end" class="control-label">Next date</label>
                                    <input
                                        class="form-control"
                                        id="schedule_end"
                                        name="schedule_end"
                                        type="text"
                                        value="{{old('schedule_end', $transaction['transactionSchedule']['end_date'] ?? '')}}"
                                    >
                                </div>
                            </div>
                        </div>
                        <!-- /.box-body -->

                        <!-- div class="box-footer">
                        </div !-->
                    </div>
                    <!-- /.box -->