<?php

declare(strict_types=1);

namespace App\Support\Release;

use App\Application\Shared\Contracts\TraceContextInterface;
use App\Mail\ReleaseAlertMail;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final readonly class ReleaseAlertNotifier
{
    public function __construct(
        private TraceContextInterface $traceContext,
    ) {}

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function capture(string $featureKey, callable $callback, ?Tenant $tenant = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->notify($featureKey, $exception, $tenant);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function notify(string $featureKey, Throwable $exception, ?Tenant $tenant = null, ?Request $request = null, array $extra = []): void
    {
        $context = $this->context($featureKey, $exception, $tenant, $request, $extra);

        Log::error('ReleaseAlert: gated feature failed.', $context);

        $recipient = (new GeneralSettings)->support_email ?? null;

        if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::warning('ReleaseAlert: support email is not configured.', [
                'feature_key' => $context['feature_key'],
                'trace_id' => $context['trace_id'],
            ]);

            return;
        }

        try {
            Mail::to($recipient)->send(new ReleaseAlertMail($context));
        } catch (Throwable $mailException) {
            Log::warning('ReleaseAlert: email delivery failed.', [
                'feature_key' => $context['feature_key'],
                'trace_id' => $context['trace_id'],
                'mail_error' => $mailException->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(string $featureKey, Throwable $exception, ?Tenant $tenant, ?Request $request, array $extra): array
    {
        $request ??= request();
        $user = $request->user();

        return array_filter([
            'feature_key' => $featureKey,
            'trace_id' => $this->traceContext->getTraceId(),
            'tenant_id' => $tenant?->getKey(),
            'tenant_slug' => $tenant?->slug,
            'user_id' => $user instanceof User ? $user->getKey() : null,
            'request_method' => $request->getMethod(),
            'request_path' => '/'.ltrim($request->path(), '/'),
            'request_host' => $request->getHost(),
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'extra' => $this->redact($extra),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            $name = strtolower((string) $key);

            $safe[$key] = str_contains($name, 'secret')
                || str_contains($name, 'token')
                || str_contains($name, 'password')
                || str_contains($name, 'key')
                    ? '[redacted]'
                    : $value;
        }

        return $safe;
    }
}
