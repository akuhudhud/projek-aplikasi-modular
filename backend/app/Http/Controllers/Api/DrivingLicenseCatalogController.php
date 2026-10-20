<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
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
    public function index(Request $request): JsonResponse
    {
        $query = DrivingLicenseCatalog::query()
            ->where('is_active', true)
            ->whereHas('licenseType', function ($typeQuery): void {
                $typeQuery->where('is_active', true);
            })
            ->with('licenseType:id,code,name')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('driving_license_type_id')) {
            $validated = $request->validate([
                'driving_license_type_id' => [
                    'required',
                    'uuid',
                    Rule::exists('driving_license_types', 'id')
                        ->where('is_active', true),
                ],
            ]);

            $query->where(
                'driving_license_type_id',
                $validated['driving_license_type_id']
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'licenses' => $query->get(),
            ],
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driving_license_type_id' => [
                'sometimes',
                'required',
                'uuid',
                'exists:driving_license_types,id',
            ],
        ]);

        $query = DrivingLicenseCatalog::query()
            ->with('licenseType:id,code,name,is_active')
            ->orderBy('sort_order')
            ->orderBy('name');

        if (isset($validated['driving_license_type_id'])) {
            $query->where(
                'driving_license_type_id',
                $validated['driving_license_type_id']
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'licenses' => $query->get(),
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
            'driving_license_type_id' => [
                'required',
                'uuid',
                Rule::exists('driving_license_types', 'id')
                    ->where('is_active', true),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
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

        $duplicate = DrivingLicenseCatalog::query()
            ->where(
                'driving_license_type_id',
                $validated['driving_license_type_id']
            )
            ->where('code', $validated['code'])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Kod kelas sudah wujud dalam jenis lesen ini.',
            ], 422);
        }

        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        $license = DB::transaction(function () use (
            $validated,
            $actor,
            $auditLogService
        ): DrivingLicenseCatalog {
            $type = DrivingLicenseType::query()
                ->whereKey($validated['driving_license_type_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($type === null) {
                throw new DomainException(
                    'Jenis lesen tidak aktif atau tidak ditemui.'
                );
            }

            $license = DrivingLicenseCatalog::query()->create([
                'id' => (string) Str::uuid(),
                'driving_license_type_id' => $type->id,
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
                'Created driving license class '
                    .$license->code.' under type '.$type->code.'.'
            );

            return $license->load('licenseType:id,code,name');
        });

        return response()->json([
            'success' => true,
            'message' => 'Kelas lesen berjaya ditambah.',
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
            'driving_license_type_id' => [
                'sometimes',
                'required',
                'uuid',
                'exists:driving_license_types,id',
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
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

                $newTypeId = $validated['driving_license_type_id']
                    ?? $lockedLicense->driving_license_type_id;

                $newCode = $validated['code']
                    ?? $lockedLicense->code;

                $typeChanged = $newTypeId
                    !== $lockedLicense->driving_license_type_id;

                $codeChanged = $newCode !== $lockedLicense->code;

                if ($typeChanged || $codeChanged) {
                    $isReferenced = RunnerLicenseVerification::query()
                        ->where(
                            'license_class',
                            $lockedLicense->code
                        )
                        ->whereHas('account', function ($query): void {
                            // Relationship exists for the verification record.
                        })
                        ->exists();

                    if ($isReferenced) {
                        throw new DomainException(
                            'Jenis induk atau kod kelas tidak boleh ditukar '
                            .'kerana kelas ini telah digunakan dalam rekod '
                            .'pengesahan lesen. Nyahaktifkan kelas lama dan '
                            .'tambah kelas baharu.'
                        );
                    }
                }

                $type = DrivingLicenseType::query()
                    ->whereKey($newTypeId)
                    ->lockForUpdate()
                    ->first();

                if ($type === null) {
                    throw new DomainException(
                        'Jenis lesen tidak ditemui.'
                    );
                }

                if (
                    $typeChanged
                    && $type->is_active !== true
                    && $type->is_active !== 1
                ) {
                    throw new DomainException(
                        'Kelas baharu hanya boleh dipindahkan ke jenis lesen aktif.'
                    );
                }

                $duplicate = DrivingLicenseCatalog::query()
                    ->where('driving_license_type_id', $newTypeId)
                    ->where('code', $newCode)
                    ->where('id', '!=', $lockedLicense->id)
                    ->exists();

                if ($duplicate) {
                    throw new DomainException(
                        'Kod kelas sudah wujud dalam jenis lesen ini.'
                    );
                }

                $previousActiveState = (bool) $lockedLicense->is_active;

                $lockedLicense->fill($validated);
                $lockedLicense->driving_license_type_id = $newTypeId;
                $lockedLicense->save();

                $action = 'DRIVING_LICENSE_CATALOG_UPDATED';
                $reason = 'Updated driving license class '
                    .$lockedLicense->code.'.';

                if (
                    array_key_exists('is_active', $validated)
                    && $previousActiveState !== (bool) $lockedLicense->is_active
                ) {
                    if ($lockedLicense->is_active) {
                        $action = 'DRIVING_LICENSE_CATALOG_REACTIVATED';
                        $reason = 'Reactivated driving license class '
                            .$lockedLicense->code.'.';
                    } else {
                        $action = 'DRIVING_LICENSE_CATALOG_DEACTIVATED';
                        $reason = 'Deactivated driving license class '
                            .$lockedLicense->code.'.';
                    }
                }

                $auditLogService->record(
                    $actor,
                    $actor,
                    $action,
                    $reason
                );

                return $lockedLicense->fresh()
                    ->load('licenseType:id,code,name');
            });
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Maklumat kelas lesen berjaya dikemas kini.',
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
        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        $result = DB::transaction(function () use (
            $id,
            $actor,
            $auditLogService
        ): array {
            $license = DrivingLicenseCatalog::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if ($license === null) {
                return ['not_found' => true];
            }

            if (!$license->is_active) {
                return ['license' => $license];
            }

            $license->is_active = false;
            $license->save();

            $auditLogService->record(
                $actor,
                $actor,
                'DRIVING_LICENSE_CATALOG_DEACTIVATED',
                'Deactivated driving license class '
                    .$license->code.'.'
            );

            return ['license' => $license->fresh()];
        });

        if (isset($result['not_found'])) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kelas lesen berjaya dinyahaktifkan.',
            'data' => [
                'license' => $result['license'],
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
            'message' => 'Rekod katalog kelas lesen tidak ditemui.',
        ], 404);
    }
}
