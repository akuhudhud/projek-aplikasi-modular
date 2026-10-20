<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\RunnerLicenseVerification;
use App\Services\AuditLogService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(
        Request $request,
        AuditLogService $auditLogService
    ): JsonResponse {
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

        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        $license = DB::transaction(function () use (
            $validated,
            $actor,
            $auditLogService
        ): DrivingLicenseCatalog {
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

            $auditLogService->record(
                $actor,
                $actor,
                'DRIVING_LICENSE_CATALOG_CREATED',
                'Created driving license catalog entry '
                    .$license->code.'.'
            );

            return $license;
        });

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
        string $id,
        AuditLogService $auditLogService
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
            array_key_exists('description', $validated)
            && $validated['description'] !== null
        ) {
            $validated['description'] = trim(
                $validated['description']
            );
        }

        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        try {
            $updatedLicense = DB::transaction(function () use (
                $license,
                $validated,
                $actor,
                $auditLogService
            ): DrivingLicenseCatalog {
                $lockedLicense = DrivingLicenseCatalog::query()
                    ->whereKey($license->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    isset($validated['code'])
                    && $validated['code'] !== $lockedLicense->code
                ) {
                    $codeIsReferenced = RunnerLicenseVerification::query()
                        ->where(
                            'license_class',
                            $lockedLicense->code
                        )
                        ->exists();

                    if ($codeIsReferenced) {
                        throw new DomainException(
                            'Kod kelas tidak boleh ditukar kerana sudah '
                            .'digunakan dalam rekod lesen. Nyahaktifkan '
                            .'kelas lama dan tambah kelas baharu.'
                        );
                    }
                }

                $lockedLicense->fill($validated);
                $lockedLicense->save();

                $auditLogService->record(
                    $actor,
                    $actor,
                    'DRIVING_LICENSE_CATALOG_UPDATED',
                    'Updated driving license catalog entry '
                        .$lockedLicense->code.'.'
                );

                return $lockedLicense->fresh();
            });
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Maklumat lesen berjaya dikemas kini.',
            'data' => [
                'license' => $updatedLicense,
            ],
        ]);
    }

    public function deactivate(
        Request $request,
        string $id,
        AuditLogService $auditLogService
    ): JsonResponse {
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

        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        $deactivatedLicense = DB::transaction(function () use (
            $license,
            $actor,
            $auditLogService
        ): DrivingLicenseCatalog {
            $lockedLicense = DrivingLicenseCatalog::query()
                ->whereKey($license->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedLicense->is_active) {
                return $lockedLicense;
            }

            $lockedLicense->is_active = false;
            $lockedLicense->save();

            $auditLogService->record(
                $actor,
                $actor,
                'DRIVING_LICENSE_CATALOG_DEACTIVATED',
                'Deactivated driving license catalog entry '
                    .$lockedLicense->code.'.'
            );

            return $lockedLicense->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Lesen berjaya dinyahaktifkan.',
            'data' => [
                'license' => $deactivatedLicense,
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
