<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

interface DynamicCompiledCacheInterface
{
    /**
     * @param  \Closure():array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function rememberSchema(?string $tenantId, string $entityKey, int $schemaVersion, \Closure $resolver): array;

    /**
     * @param  \Closure():array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function rememberTemplate(?string $tenantId, string $templateKey, int $templateVersion, \Closure $resolver): array;

    public function forgetSchema(?string $tenantId, string $entityKey, ?int $schemaVersion = null): void;

    public function forgetTemplate(?string $tenantId, string $templateKey, ?int $templateVersion = null): void;

    public function flushTenant(?string $tenantId): void;

    public function schemaCacheKey(?string $tenantId, string $entityKey, int $schemaVersion): string;

    public function templateCacheKey(?string $tenantId, string $templateKey, int $templateVersion): string;
}

