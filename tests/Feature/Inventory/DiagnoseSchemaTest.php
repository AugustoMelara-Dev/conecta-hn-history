<?php

/**
 * Diagnóstico ejecutado como test para usar DB testing correcta
 */

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiagnoseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnose_schema(): void
    {
        // Step 1: Verify tables exist
        echo "\n=== SCHEMA DIAGNOSTIC ===\n";

        // Check users table columns
        echo "\n1. Users columns:\n";
        foreach (Schema::getColumnListing('users') as $column) {
            echo "   - {$column}\n";
        }

        // Check tenants table columns
        echo "\n2. Tenants columns:\n";
        foreach (Schema::getColumnListing('tenants') as $column) {
            echo "   - {$column}\n";
        }

        // Check products table columns
        echo "\n3. Products columns:\n";
        foreach (Schema::getColumnListing('products') as $column) {
            echo "   - {$column}\n";
        }

        // Check orders table columns
        echo "\n4. Orders columns:\n";
        foreach (Schema::getColumnListing('orders') as $column) {
            echo "   - {$column}\n";
        }

        $this->assertTrue(true);
    }
}
