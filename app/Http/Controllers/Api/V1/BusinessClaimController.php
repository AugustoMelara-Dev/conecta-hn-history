<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Auth\VerifyOtpAction;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException;
use App\Domain\Auth\Exceptions\OtpExpiredException;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantBusinessClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BusinessClaimController extends Controller
{
    public function __construct(
        private readonly VerifyOtpAction $verifyOtpAction
    ) {}

    public function submit(Request $request, Tenant $tenant): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $isOwner = (string) $tenant->user_id === (string) $user->getAuthIdentifier();
        $isSuperAdmin = $user->isSuperAdmin();

        if (! $isOwner && ! $isSuperAdmin) {
            return response()->json([
                'error_code' => 'BUSINESS_CLAIM_FORBIDDEN',
                'message' => 'No tienes permisos para enviar esta verificacion.',
            ], 403);
        }

        $validated = $request->validate([
            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^\+[1-9]\d{1,14}$/'],
            'whatsapp_otp_code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'evidence_type' => ['required', 'in:website,social,document,other'],
            'evidence_url' => ['nullable', 'url', 'max:500'],
            'evidence_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $whatsappNumber = $validated['whatsapp_number'] ?? $tenant->whatsapp_number;
        if (! is_string($whatsappNumber) || $whatsappNumber === '') {
            return response()->json([
                'error_code' => 'BUSINESS_CLAIM_WHATSAPP_REQUIRED',
                'message' => 'Debes registrar un numero de WhatsApp para verificar el negocio.',
            ], 422);
        }

        try {
            $this->verifyOtpAction->execute(
                tenantId: (string) $tenant->getKey(),
                identifier: $whatsappNumber,
                code: (string) $validated['whatsapp_otp_code'],
                ipAddress: $request->ip() ?? '127.0.0.1',
                idempotencyKey: $request->header('Idempotency-Key')
            );
        } catch (InvalidOtpException|OtpExpiredException|MaxOtpAttemptsExceededException) {
            return response()->json([
                'error_code' => 'BUSINESS_CLAIM_WHATSAPP_OTP_INVALID',
                'message' => 'No pudimos verificar el codigo OTP de WhatsApp.',
            ], 422);
        }

        $claim = TenantBusinessClaim::query()->updateOrCreate(
            ['tenant_id' => (string) $tenant->getKey()],
            [
                'user_id' => (string) $user->getAuthIdentifier(),
                'status' => 'pending',
                'email_verified_at' => $user->email_verified_at,
                'whatsapp_number' => $whatsappNumber,
                'whatsapp_otp_verified_at' => now(),
                'evidence_type' => $validated['evidence_type'],
                'evidence_url' => $validated['evidence_url'] ?? null,
                'evidence_notes' => $validated['evidence_notes'] ?? null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'decision_notes' => null,
            ]
        );

        $tenant->forceFill([
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'verified_at' => null,
            'verification_reviewed_at' => null,
            'verification_rejection_reason' => null,
        ])->save();

        return response()->json([
            'message' => 'Verificacion enviada. Nuestro equipo la revisara pronto.',
            'data' => [
                'claim_id' => $claim->id,
                'status' => $claim->status,
                'tenant_verification_status' => $tenant->verification_status,
            ],
        ], 202);
    }

    public function review(Request $request, Tenant $tenant): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $isSuperAdmin = $user->isSuperAdmin();
        if (! $isSuperAdmin) {
            return response()->json([
                'error_code' => 'BUSINESS_CLAIM_REVIEW_FORBIDDEN',
                'message' => 'Solo superadmin puede revisar claims.',
            ], 403);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $claim = TenantBusinessClaim::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->first();

        if (! $claim instanceof TenantBusinessClaim) {
            return response()->json([
                'error_code' => 'BUSINESS_CLAIM_NOT_FOUND',
                'message' => 'No existe un claim pendiente para este negocio.',
            ], 404);
        }

        $decision = (string) $validated['decision'];
        $approved = $decision === 'approved';

        $claim->forceFill([
            'status' => $decision,
            'reviewed_by' => (string) $user->getAuthIdentifier(),
            'reviewed_at' => now(),
            'decision_notes' => $validated['decision_notes'] ?? null,
        ])->save();

        $tenant->forceFill([
            'verification_status' => $approved
                ? Tenant::VERIFICATION_STATUS_VERIFIED
                : Tenant::VERIFICATION_STATUS_REJECTED,
            'verified_at' => $approved ? now() : null,
            'verification_reviewed_at' => now(),
            'verification_rejection_reason' => $approved ? null : ($validated['decision_notes'] ?? 'Claim rechazado'),
            'publication_paused_at' => $approved ? null : $tenant->publication_paused_at,
            'publication_pause_reason' => $approved ? null : $tenant->publication_pause_reason,
        ])->save();

        return response()->json([
            'message' => $approved
                ? 'Claim aprobado y negocio verificado.'
                : 'Claim rechazado. Se mantiene fuera de publicacion.',
            'data' => [
                'claim_id' => $claim->id,
                'claim_status' => $claim->status,
                'tenant_verification_status' => $tenant->verification_status,
            ],
        ]);
    }
}
