{{-- All these scripts are primarily used by the sandbox environment to enable basic anonymous tracking capabilities --}}

{{-- Conditionally load the scripts related to Google Tag Manager --}}
@if(config('yaffa.gtm_container_id'))
    @if (isset($dataLayerEvents))
        <!-- Load dataLayer events defined on server side -->
        <script>
            @forEach($dataLayerEvents as $event)
            window.dataLayer.push(@json($event));
            @endforEach
        </script>
    @endif
@endif
