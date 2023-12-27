<div class="input-group mb-3">
    <span class="input-group-text">
        <i class="fa-solid fa-fw fa-lock"></i>
    </span>
    <input
        @class([
            'form-control',
            'is-invalid' => $errors->has('password')
        ])
        id="password"
        name="password"
        placeholder="{{ __('Password') }}"
        required
        type="password"
    >
    @error('password')
        <span class="invalid-feedback" role="alert">
            <strong>{{ __($message) }}</strong>
        </span>
    @enderror
</div>
