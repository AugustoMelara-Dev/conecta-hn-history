<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Application\Auth\Enums\AuthStatus;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Identity\Handlers\RegisterUserHandler;
use App\Domain\Shared\Enums\ProviderName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Http\Resources\V1\AuthResource;
use App\Models\Tenant;
use App\Support\Auth\AuthRedirector;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthTokenServiceInterface $tokenService,
        private readonly SocialiteFactory $socialite,
    ) {}

    public function register(
        RegisterRequest $request,
        RegisterUserHandler $handler
    ): JsonResponse {
        $command = $request->toCommand();
        $result = $handler->handle($command);
        $tokenData = $this->tokenService->createTokenForUser($result->id, 'api-token');
        $authContext = AuthRedirector::contextFor($tokenData['user']);

        $responsePayload = (object) [
            'user' => $tokenData['user'],
            'accessToken' => $tokenData['token'],
            'redirectUrl' => $authContext['redirect_to'],
            'homeSurface' => $authContext['home_surface'],
            'audience' => $authContext['audience'],
        ];

        return response()->json(new AuthResource($responsePayload), 201);
    }

    /**
     * @throws ValidationException
     */
    public function socialLogin(
        SocialLoginRequest $request,
        LoginViaSocialProviderHandlerInterface $handler
    ): JsonResponse {
        $validated = $request->validatedData();
        $providerName = ProviderName::tryFrom((string) $validated['provider']);

        if ($providerName === null) {
            throw ValidationException::withMessages([
                'provider' => 'El proveedor solicitado no es compatible con el flujo social actual.',
            ]);
        }

        $providerUser = $this->verifyProviderToken($providerName, (string) $validated['token']);

        $command = new LoginViaSocialProviderCommand(
            tenantId: $this->resolveTenantId(),
            providerName: $providerName,
            providerId: $providerUser['id'],
            email: $providerUser['email'],
            name: $providerUser['name'],
            avatarUrl: $providerUser['avatar'],
            accessToken: $validated['token'],
            refreshToken: null,
            idempotencyKey: (string) $request->header('Idempotency-Key', Str::uuid()->toString()),
        );

        $result = $handler->handle($command);
        $tokenData = $this->tokenService->createTokenForUser($result->userId, 'social-api-token');
        $authContext = AuthRedirector::contextFor($tokenData['user']);

        if ($result->status === AuthStatus::REQUIRES_ONBOARDING || $result->status === AuthStatus::NEW_USER) {
            $authContext = [
                'redirect_to' => route('tenant.register', [], false),
                'home_surface' => AuthRedirector::SURFACE_BUSINESS_APP,
                'audience' => AuthRedirector::AUDIENCE_OWNER,
            ];
        }

        $responsePayload = (object) [
            'user' => $tokenData['user'],
            'accessToken' => $tokenData['token'],
            'redirectUrl' => $authContext['redirect_to'],
            'homeSurface' => $authContext['home_surface'],
            'audience' => $authContext['audience'],
        ];

        return response()->json(new AuthResource($responsePayload));
    }

    /**
     * @return array{id: string, email: string, name: string, avatar: ?string}
     *
     * @throws ValidationException
     */
    private function verifyProviderToken(ProviderName $providerName, string $token): array
    {
        try {
            $provider = $this->socialite->driver($providerName->value);

            if (method_exists($provider, 'stateless')) {
                $provider = $provider->stateless();
            }

            if (! method_exists($provider, 'userFromToken')) {
                throw ValidationException::withMessages([
                    'provider' => 'El proveedor solicitado no soporta verificacion por token en servidor.',
                ]);
            }

            $socialUser = $provider->userFromToken($token);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'provider_token' => 'No pudimos verificar el token con el proveedor OAuth.',
            ]);
        }

        $providerId = (string) $socialUser->getId();
        $email = (string) $socialUser->getEmail();

        if ($providerId === '' || $email === '') {
            throw ValidationException::withMessages([
                'provider_token' => 'El proveedor OAuth no devolvio una identidad completa.',
            ]);
        }

        $raw = is_array($socialUser->user ?? null) ? $socialUser->user : [];
        $emailVerified = $raw['email_verified'] ?? $raw['verified_email'] ?? null;

        if ($emailVerified !== null && filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== true) {
            throw ValidationException::withMessages([
                'provider_token' => 'El proveedor OAuth no confirmo el correo electronico.',
            ]);
        }

        return [
            'id' => $providerId,
            'email' => $email,
            'name' => (string) ($socialUser->getName() ?: explode('@', $email)[0]),
            'avatar' => $socialUser->getAvatar(),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function resolveTenantId(): string
    {
        if (app()->has('currentTenant')) {
            $tenant = app('currentTenant');

            if ($tenant instanceof Tenant) {
                return (string) $tenant->getKey();
            }
        }

        $configuredTenantId = config('app.default_tenant_id');

        if (is_string($configuredTenantId) && Str::isUuid($configuredTenantId)) {
            return $configuredTenantId;
        }

        throw ValidationException::withMessages([
            'tenant' => 'No se pudo resolver el contexto del negocio para autenticación social.',
        ]);
    }
}
