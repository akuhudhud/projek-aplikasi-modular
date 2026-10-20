<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DrivingLicenseType;
use App\Models\RunnerLicenseVerification;
use App\Services\AuditLogService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DrivingLicenseTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = DrivingLicenseType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types,
            ],
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        $types = DrivingLicenseType::query()
            ->withCount('classes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types,
            ],
        ]);
    }

    public function store(
        Request $request,
        AuditLogService $auditLogService
    ): JsonResponse {
        $this->normalize($request);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
                'unique:driving_license_types,code',
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

        $type = DB::transaction(function () use (
            $validated,
            $actor,
            $auditLogService
        ): DrivingLicenseType {
            $type = DrivingLicenseType::query()->create([
                'id' => (string) Str::uuid(),
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            $auditLogService->record(
                $actor,
                $actor,
                'DRIVING_LICENSE_TYPE_CREATED',
                'Created driving license type '.$type->code.'.'
            );

            return $type;
        });

        return response()->json([
            'success' => true,
            'message' => 'Jenis lesen berjaya ditambah.',
            'data' => [
                'type' => $type,
            ],
        ], 201);
    }

    public function update(
        Request $request,
        string $id,
        AuditLogService $auditLogService
    ): JsonResponse {
        $type = DrivingLicenseType::query()->find($id);

        if ($type === null) {
            return $this->notFound();
        }

        $this->normalize($request);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('driving_license_types', 'code')
                    ->ignore($type->id, 'id'),
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
            $updatedType = DB::transaction(function () use (
                $type,
                $validated,
                $actor,
                $auditLogService
            ): DrivingLicenseType {
                $lockedType = DrivingLicenseType::query()
                    ->whereKey($type->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    isset($validated['code'])
                    && $validated['code'] !== $lockedType->code
                ) {
                    $isReferenced = RunnerLicenseVerification::query()
                        ->where('license_type', $lockedType->code)
                        ->exists();

                    if ($isReferenced) {
                        throw new DomainException(
                            'Kod jenis tidak boleh ditukar kerana sudah '
                            .'digunakan dalam rekod pengesahan lesen. '
                            .'Nyahaktifkan jenis lama dan tambah jenis baharu.'
                        );
                    }
                }

                $previousActiveState = (bool) $lockedType->is_active;

                $lockedType->fill($validated);
                $lockedType->save();

                $action = 'DRIVING_LICENSE_TYPE_UPDATED';
                $reason = 'Updated driving license type '
                    .$lockedType->code.'.';

                if (
                    array_key_exists('is_active', $validated)
                    && $previousActiveState !== (bool) $lockedType->is_active
                ) {
                    if ($lockedType->is_active) {
                        $action = 'DRIVING_LICENSE_TYPE_REACTIVATED';
                        $reason = 'Reactivated driving license type '
                            .$lockedType->code.'.';
                    } else {
                        $action = 'DRIVING_LICENSE_TYPE_DEACTIVATED';
                        $reason = 'Deactivated driving license type '
                            .$lockedType->code.'.';
                    }
                }

                $auditLogService->record(
                    $actor,
                    $actor,
                    $action,
                    $reason
                );

                return $lockedType->fresh();
            });
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jenis lesen berjaya dikemas kini.',
            'data' => [
                'type' => $updatedType,
            ],
        ]);
    }

    public function deactivate(
        Request $request,
        string $id,
        AuditLogService $auditLogService
    ): JsonResponse {
        $type = DrivingLicenseType::query()->find($id);

        if ($type === null) {
            return $this->notFound();
        }

        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        $deactivatedType = DB::transaction(function () use (
            $type,
            $actor,
            $auditLogService
        ): DrivingLicenseType {
            $lockedType = DrivingLicenseType::query()
                ->whereKey($type->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedType->is_active) {
                return $lockedType;
            }

            $lockedType->is_active = false;
            $lockedType->save();

            $auditLogService->record(
                $actor,
                $actor,
                'DRIVING_LICENSE_TYPE_DEACTIVATED',
                'Deactivated driving license type '
                    .$lockedType->code.'.'
            );

            return $lockedType->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Jenis lesen berjaya dinyahaktifkan.',
            'data' => [
                'type' => $deactivatedType,
            ],
        ]);
    }

    private function normalize(Request $request): void
    {
        if (
            $request->exists('code')
            && is_string($request->input('code'))
        ) {
            $request->merge([
                'code' => strtoupper(trim($request->input('code'))),
            ]);
        }

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
            'message' => 'Jenis lesen tidak ditemui.',
        ], 404);
    }
}
