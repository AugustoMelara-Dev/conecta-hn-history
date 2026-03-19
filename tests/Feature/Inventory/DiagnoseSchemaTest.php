<?php

/**
 * Diagnóstico ejecutado como test para usar DB testing correcta
 */

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $userCols = DB::select('SHOW COLUMNS FROM users');
        foreach ($userCols as $col) {
            echo "   - {$col->Field} ({$col->Type}) ".($col->Null === 'NO' ? 'NOT NULL' : 'NULL')."\n";
        }

        // Check tenants table columns
        echo "\n2. Tenants columns:\n";
        $tenantCols = DB::select('SHOW COLUMNS FROM tenants');
        foreach ($tenantCols as $col) {
            echo "   - {$col->Field} ({$col->Type}) ".($col->Null === 'NO' ? 'NOT NULL' : 'NULL')."\n";
        }

        // Check products table columns
        echo "\n3. Products columns:\n";
        $productCols = DB::select('SHOW COLUMNS FROM products');
        foreach ($productCols as $col) {
            echo "   - {$col->Field} ({$col->Type}) ".($col->Null === 'NO' ? 'NOT NULL' : 'NULL')."\n";
        }

        // Check orders table columns
        echo "\n4. Orders columns:\n";
        $orderCols = DB::select('SHOW COLUMNS FROM orders');
        foreach ($orderCols as $col) {
            echo "   - {$col->Field} ({$col->Type}) ".($col->Null === 'NO' ? 'NOT NULL' : 'NULL')."\n";
        }

        $this->assertGreaterThan(0, count($orderCols));
    }
}
