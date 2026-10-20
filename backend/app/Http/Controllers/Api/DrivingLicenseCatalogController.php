<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DrivingLicenseCatalog;
use App\Models\RunnerLicenseVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DrivingLicenseCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $licenses = DrivingLicenseCatalog::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'licenses' => $licenses,
            ],
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        $licenses = DrivingLicenseCatalog::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'licenses' => $licenses,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeCode($request);
        $this->normalizeName($request);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
                'unique:driving_license_catalog,code',
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $license = DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
            'is_active' => true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lesen berjaya ditambah.',
            'data' => [
                'license' => $license,
            ],
        ], 201);
    }

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        $license = DrivingLicenseCatalog::query()->find($id);

        if ($license === null) {
            return $this->notFound();
        }

        $this->normalizeCode($request);
        $this->normalizeName($request);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('driving_license_catalog', 'code')
                    ->ignore($license->id, 'id'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (
            isset($validated['code'])
            && $validated['code'] !== $license->code
        ) {
            $codeIsReferenced = RunnerLicenseVerification::query()
                ->where('license_class', $license->code)
                ->exists();

            if ($codeIsReferenced) {
                return response()->json([
                    'success' => false,
                    'message' => (
                        'Kod kelas tidak boleh ditukar kerana sudah '
                        .'digunakan dalam rekod lesen. Nyahaktifkan '
                        .'kelas lama dan tambah kelas baharu.'
                    ),
                ], 422);
            }
        }

        if (
            array_key_exists('description', $validated)
            && $validated['description'] !== null
        ) {
            $validated['description'] = trim(
                $validated['description']
            );
        }

        $license->fill($validated);
        $license->save();

        return response()->json([
            'success' => true,
            'message' => 'Maklumat lesen berjaya dikemas kini.',
            'data' => [
                'license' => $license->fresh(),
            ],
        ]);
    }

    public function deactivate(string $id): JsonResponse
    {
        $license = DrivingLicenseCatalog::query()->find($id);

        if ($license === null) {
            return $this->notFound();
        }

        if (!$license->is_active) {
            return response()->json([
                'success' => true,
                'message' => 'Lesen ini sudah dinyahaktifkan.',
                'data' => [
                    'license' => $license,
                ],
            ]);
        }

        $license->is_active = false;
        $license->save();

        return response()->json([
            'success' => true,
            'message' => 'Lesen berjaya dinyahaktifkan.',
            'data' => [
                'license' => $license->fresh(),
            ],
        ]);
    }

    private function normalizeCode(Request $request): void
    {
        if (
            $request->exists('code')
            && is_string($request->input('code'))
        ) {
            $request->merge([
                'code' => strtoupper(trim($request->input('code'))),
            ]);
        }
    }

    private function normalizeName(Request $request): void
    {
        if (
            $request->exists('name')
            && is_string($request->input('name'))
        ) {
            $request->merge([
                'name' => trim($request->input('name')),
            ]);
        }
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Rekod katalog lesen tidak ditemui.',
        ], 404);
    }
}
