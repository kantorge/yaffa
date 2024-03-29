<div class="input-group mb-3">
    <span class="input-group-text">
        <i class="fa-solid fa-fw fa-lock"></i>
    </span>
    <input
        @class([
            'form-control',
            'is-invalid' => $errors->has('password_confirmation')
        ])
        id="password_confirmation"
        name="password_confirmation"
        placeholder="{{ __('Confirm password') }}"
        required
        type="password"
    >
    @error('password_confirmation')
        <span class="invalid-feedback" role="alert">
            <strong>{{ __($message) }}</strong>
        </span>
    @enderror
</div>
