<?php

namespace App\Http\Requests;

class DeviceRequest extends FormRequest
{
    public function rules(): array
    {
        $endpoint = ['required', 'string', 'max:2048'];

        // A UnifiedPush endpoint is a URL; an FCM registration token is an opaque string.
        // ponytail: a URL check only. Add PublicEndpointUrlValidator (SSRF) when the server starts sending pushes.
        if ($this->input('type') === 'unifiedpush') {
            $endpoint[] = 'url:http,https';
        }

        return [
            'type' => ['required', 'in:unifiedpush,fcm'],
            'endpoint' => $endpoint,
        ];
    }
}
