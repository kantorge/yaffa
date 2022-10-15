<div class="form-group d-inline-block">
    <label class="control-label">
        {{ __('Active') }}
    </label>
    <div>
        <div class="btn-group" data-toggle="buttons">
            <label class="btn btn-primary" title="{{ __('Yes') }}">
                <input type="radio" name="active" value="Yes" class="radio-inline">
                <span class="fa fa-fw fa-check"></span>
            </label>
            <label class="btn btn-primary active" title="{{ __('Any') }}">
                <input type="radio" name="active" value="" class="radio-inline" checked="checked">
                <span class="fa fa-fw fa-circle-o"></span>
            </label>
            <label class="btn btn-primary" title="{{ __('No') }}">
                <input type="radio" name="active" value="No" class="radio-inline">
                <span class="fa fa-fw fa-close"></span>
            </label>
        </div>
    </div>
</div>
