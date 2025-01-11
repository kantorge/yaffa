{{-- All these scripts are primarily used by the sandbox environment to enable basic anonymous tracking capabilities --}}

{{-- Conditionally load the scripts related to Google Tag Manager --}}
@if(config('yaffa.gtm_container_id'))
    <!-- Default consent settings for Google Tag Manager -->
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() {
            dataLayer.push(arguments);
        }
        gtag("consent", "default", {
            ad_user_data: "denied",
            ad_personalization: "denied",
            ad_storage: "denied",
            analytics_storage: "denied",
            functionality_storage: "denied",
            personalization_storage: "denied",
            security_storage: "granted",
            wait_for_update: 500,
        });
        gtag("set", "ads_data_redaction", true);
    </script>

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{config('yaffa.gtm_container_id')}}');</script>
    <!-- End Google Tag Manager -->

    @if (isset($dataLayerEvents))
        <!-- Load dataLayer events defined on server side -->
        <script>
            @forEach($dataLayerEvents as $event)
            window.dataLayer.push(@json($event));
            @endforEach
        </script>
    @endif
@endif

{{-- Conditionally load CookieYes, which is the CMP used by the sandbox --}}
@if(config('yaffa.cookieyes_id'))
    <!-- Start cookieyes banner -->
    <script
            id="cookieyes"
            type="text/javascript"
            src="https://cdn-cookieyes.com/client_data/{{config('yaffa.cookieyes_id')}}/script.js"
    ></script>
    <!-- End cookieyes banner -->
@endif
