<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CsvImportProfileStoreRequest;
use App\Http\Requests\CsvImportProfileUpdateRequest;
use App\Models\CsvImportProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;

class CsvImportProfileApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', CsvImportProfile::class);

        $user = request()->user();

        $profiles = CsvImportProfile::query()
            ->selectableForUser($user)
            ->orderByDesc('type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $profiles,
        ], Response::HTTP_OK);
    }

    public function store(CsvImportProfileStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', CsvImportProfile::class);

        $user = $request->user();

        $profile = CsvImportProfile::query()->create([
            'user_id' => $user?->id,
            'key' => null,
            'type' => 'user',
            'name' => (string) $request->input('name'),
            'delimiter' => (string) $request->input('delimiter', ','),
            'has_header_row' => (bool) $request->input('has_header_row', true),
            'date_format' => $request->input('date_format'),
            'decimal_separator' => $request->input('decimal_separator'),
            'thousand_separator' => $request->input('thousand_separator'),
            'sign_handling' => $request->input('sign_handling'),
            'mapping_json' => (array) $request->input('mapping_json', []),
            'options_json' => (array) $request->input('options_json', []),
            'active' => (bool) $request->input('active', true),
        ]);

        return response()->json(['data' => $profile], Response::HTTP_CREATED);
    }

    public function update(CsvImportProfileUpdateRequest $request, CsvImportProfile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);

        $profile->fill($request->validated());
        $profile->save();

        return response()->json(['data' => $profile], Response::HTTP_OK);
    }

    public function destroy(CsvImportProfile $profile): JsonResponse
    {
        Gate::authorize('delete', $profile);

        $profile->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function clone(Request $request, CsvImportProfile $profile): JsonResponse
    {
        Gate::authorize('clone', $profile);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'min:2', 'max:191'],
        ]);

        $clone = CsvImportProfile::query()->create(
            $profile->toUserCloneAttributes($request->user(), $validated['name'] ?? null)
        );

        return response()->json(['data' => $clone], Response::HTTP_CREATED);
    }
}
