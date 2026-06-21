<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloneFileImportProfileRequest;
use App\Http\Requests\FileImportProfileStoreRequest;
use App\Http\Requests\FileImportProfileUpdateRequest;
use App\Models\FileImportProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;

class FileImportProfileApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FileImportProfile::class);

        $user = $request->user();

        $query = FileImportProfile::query()
            ->selectableForUser($user)
            ->orderByDesc('type')
            ->orderBy('name');

        $allowedFileTypes = ['csv', 'qif'];
        $fileType = $request->input('file_type');
        if ($request->filled('file_type') && in_array($fileType, $allowedFileTypes, true)) {
            $query->where('file_type', $fileType);
        }

        return response()->json([
            'data' => $query->get(),
        ], Response::HTTP_OK);
    }

    public function store(FileImportProfileStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', FileImportProfile::class);

        $user = $request->user();

        $profile = FileImportProfile::query()->create([
            'user_id' => $user?->id,
            'key' => null,
            'type' => 'user',
            'file_type' => $request->input('file_type', 'csv'),
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

    public function update(FileImportProfileUpdateRequest $request, FileImportProfile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);

        $profile->fill($request->validated());
        $profile->save();

        return response()->json(['data' => $profile], Response::HTTP_OK);
    }

    public function destroy(FileImportProfile $profile): JsonResponse
    {
        Gate::authorize('delete', $profile);

        $profile->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function clone(CloneFileImportProfileRequest $request, FileImportProfile $profile): JsonResponse
    {
        Gate::authorize('clone', $profile);

        $clone = FileImportProfile::query()->create(
            $profile->toUserCloneAttributes($request->user(), $request->input('name'))
        );

        return response()->json(['data' => $clone], Response::HTTP_CREATED);
    }
}
