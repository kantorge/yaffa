<div class="d-inline-block">
    <label>
        {{ __('Active') }}
    </label>
    <div>
        <div class="btn-group" role="group" aria-label="Toggle button group for active">
            <input type="radio" class="btn-check" name="active" id="active_yes" value="{{ __('Yes') }}">
            <label class="btn btn-outline-primary" for="active_yes" title="{{ __('Yes') }}">
                <span class="fa fa-fw fa-check"></span>
            </label>

            <input type="radio" class="btn-check" name="active" id="active_any" value="" checked>
            <label class="btn btn-outline-primary" for="active_any" title="{{ __('Any') }}">
                <span class="fa fa-fw fa-circle"></span>
            </label>

            <input type="radio" class="btn-check" name="active" id="active_no" value="{{ __('No') }}">
            <label class="btn btn-outline-primary" for="active_no" title="{{ __('No') }}">
                <span class="fa fa-fw fa-close"></span>
            </label>
        </div>
    </div>
</div>
