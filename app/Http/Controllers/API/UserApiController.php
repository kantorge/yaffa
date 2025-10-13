<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class UserApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }
    public function updateSettings(UserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Variable to hold warning messages
        $warningMessages = [];

        /** @var User $user */
        $user = auth()->user();
        $user->fill($validated);

        // If the end_date has changed, we need to recalculate the monthly summaries
        if ($user->isDirty('end_date')) {
            Artisan::call('app:cache:account-monthly-summaries', [
                'userId' => $user->id,
            ]);

            $warningMessages[] = __('Cached monthly summaries need to be recalculated. This may take a while. Please be patient.');
        }

        // Notify the user that the UI language update needs a page refresh
        if ($user->isDirty('language')) {
            $warningMessages[] = __('The language has been updated. Please refresh the page to see the changes.');
        }

        $user->save();

        // Return a JSON response
        return response()->json([
            'warnings' => $warningMessages,
            'data' => [
                'language' => $user->language,
                'locale' => $user->locale,
                'start_date' => $user->start_date,
                'end_date' => $user->end_date,
                'account_details_date_range' => $user->account_details_date_range,
            ]
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        // This endpoint is not allowed in sandbox mode
        if (config('yaffa.sandbox_mode')) {
            return response()->json([
                'message' => __('This action is not allowed in sandbox mode.'),
            ], 403);
        }

        // This is a very specific endpoint without a dedicated FormRequest, so we need to use a local validator
        $this->validator($request->all())->validate();

        /** @var User $user */
        $user = auth()->user();
        $user->password = Hash::make($request['password']);
        $user->save();

        return response()->json();
    }

    protected function validator(array $data): \Illuminate\Contracts\Validation\Validator
    {
        // The rules must be in line with the rules in RegisterController.php
        return Validator::make($data, [
            'current_password' => ['current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);
    }
}
