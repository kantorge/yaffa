<div class="d-inline-block">
    <label>
        {{ __('Active') }}
    </label>
    <div>
        <div class="btn-group" role="group" data-toggle="buttons">
            <label class="btn btn-primary" title="{{ __('Yes') }}">
                <input type="radio" class="btn-check" name="active" value="{{ __('Yes') }}">
                <span class="fa fa-fw fa-check"></span>
            </label>
            <label class="btn btn-primary" title="{{ __('Any') }}">
                <input type="radio" class="btn-check" name="active" checked="checked" value="">
                <span class="fa fa-fw fa-circle"></span>
            </label>
            <label class="btn btn-primary" title="{{ __('No') }}">
                <input type="radio" class="btn-check" name="active" value="{{ __('No') }}">
                <span class="fa fa-fw fa-close"></span>
            </label>
        </div>
    </div>
</div>
