<div class="input-group mb-3">
    <span class="input-group-text">
        <i class="fa-regular fa-envelope"></i>
    </span>
    <input
        @if ($autofocus)
        autofocus
        @endif
        class="form-control @error('email') is-invalid @enderror"
        id="email"
        name="email"
        placeholder="{{ __('Email') }}"
        required
        type="email"
        value="{{ old('email') }}"
    >
    @error('email')
        <span class="invalid-feedback" role="alert">
            <strong>{{ __($message) }}</strong>
        </span>
    @enderror
</div>
