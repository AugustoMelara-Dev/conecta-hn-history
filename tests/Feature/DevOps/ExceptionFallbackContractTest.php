<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class ExceptionFallbackContractTest extends TestCase
{
    public function test_bootstrap_keeps_inertia_error_fallback_for_5xx(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');
        $this->assertFileExists($bootstrapPath);

        $contents = file_get_contents($bootstrapPath);
        $this->assertIsString($contents);
        $this->assertStringContainsString("if (\$status >= 500 && \$request->header('X-Inertia'))", $contents);
        $this->assertStringContainsString("Inertia::render('Error'", $contents);
    }

    public function test_bootstrap_keeps_inertia_session_expiry_fallback_for_419(): void
    {
        $contents = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertIsString($contents);
        $this->assertStringContainsString("if (\$status === 419 && \$request->header('X-Inertia'))", $contents);
        $this->assertStringContainsString("->with('error', 'Tu sesión expiró por seguridad. Recargamos la vista para que continúes.')", $contents);
    }

    public function test_bootstrap_keeps_token_mismatch_json_and_toast_paths(): void
    {
        $contents = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('TokenMismatchException', $contents);
        $this->assertStringContainsString("'message' => 'Tu sesión expiró. Recarga la página e intenta de nuevo.'", $contents);
        $this->assertStringContainsString("->with('toast', 'Tu sesión expiró por seguridad. Reintentemos desde este paso.')", $contents);
    }
}
