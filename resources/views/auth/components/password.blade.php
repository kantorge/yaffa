<div class="input-group mb-3">
    <span class="input-group-text">
        <i class="cil-lock-locked"></i>
    </span>
    <input
        class="form-control @error('password') is-invalid @enderror"
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
