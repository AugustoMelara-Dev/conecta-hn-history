<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function billingColumnNames(string $table): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return collect(DB::select("PRAGMA table_info('{$table}')"))
            ->map(fn (object $column): string => (string) $column->name)
            ->all();
    }

    return collect(DB::select(
        "select column_name from information_schema.columns where table_schema = database() and table_name = ? order by ordinal_position",
        [$table]
    ))->map(fn (object $column): string => (string) $column->column_name)->all();
}

function billingIndexColumns(string $table, string $index): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        $matching = collect(DB::select("PRAGMA index_list('{$table}')"))
            ->first(fn (object $row): bool => (string) $row->name === $index);

        if ($matching === null) {
            return [];
        }

        return collect(DB::select("PRAGMA index_info('{$index}')"))
            ->map(fn (object $column): string => (string) $column->name)
            ->all();
    }

    return collect(DB::select(
        "select column_name from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? order by seq_in_index",
        [$table, $index]
    ))->map(fn (object $column): string => (string) $column->column_name)->all();
}

it('keeps subscription payments as the tenant owned payment evidence ledger', function (): void {
    expect(Schema::hasTable('subscription_payments'))->toBeTrue();

    $columns = billingColumnNames('subscription_payments');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'idempotency_key',
        'amount',
        'bank',
        'reference_number',
        'proof_path',
        'status',
        'version',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'created_at',
        'updated_at'
    );

    expect(billingIndexColumns('subscription_payments', 'unique_payment_idempotency'))
        ->toBe(['tenant_id', 'idempotency_key']);

    expect(billingIndexColumns('subscription_payments', 'subscription_payments_bank_reference_number_unique'))
        ->toBe(['bank', 'reference_number']);

    expect(billingIndexColumns('subscription_payments', 'idx_proof_path_lookup'))
        ->toBe(['proof_path']);
});

it('keeps subscription intents as the tenant scoped checkout idempotency ledger', function (): void {
    expect(Schema::hasTable('subscription_intents'))->toBeTrue();

    $columns = billingColumnNames('subscription_intents');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'client_idempotency_key',
        'plan_identifier',
        'gateway_provider',
        'status',
        'gateway_transaction_id',
        'error_reason',
        'created_at',
        'updated_at'
    );

    expect(billingIndexColumns('subscription_intents', 'unique_client_intent'))
        ->toBe(['tenant_id', 'client_idempotency_key']);
});

it('keeps provider billing references outside the tenants row', function (): void {
    expect(Schema::hasTable('tenant_billing_references'))->toBeTrue();

    $columns = billingColumnNames('tenant_billing_references');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'provider',
        'provider_customer_id',
        'provider_subscription_id',
        'created_at',
        'updated_at'
    );

    expect(billingIndexColumns('tenant_billing_references', 'tenant_billing_refs_tenant_provider_unique'))
        ->toBe(['tenant_id', 'provider']);

    expect(billingIndexColumns('tenant_billing_references', 'tenant_billing_refs_customer_idx'))
        ->toBe(['provider', 'provider_customer_id']);

    expect(billingIndexColumns('tenant_billing_references', 'tenant_billing_refs_subscription_idx'))
        ->toBe(['provider', 'provider_subscription_id']);
});

it('keeps webhook logs as the provider event idempotency audit table', function (): void {
    expect(Schema::hasTable('webhook_logs'))->toBeTrue();

    $columns = billingColumnNames('webhook_logs');

    expect($columns)->toContain(
        'id',
        'provider',
        'external_event_id',
        'event_type',
        'payload',
        'created_at'
    );

    expect($columns)->not->toContain('tenant_id');
    expect(billingIndexColumns('webhook_logs', 'webhook_idempotency_key'))
        ->toBe(['provider', 'external_event_id']);
});

it('documents the legacy payments table kept for compatibility', function (): void {
    expect(Schema::hasTable('payments'))->toBeTrue();

    $columns = billingColumnNames('payments');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'amount',
        'currency',
        'status',
        'reference_id',
        'provider',
        'paid_at',
        'created_at',
        'updated_at'
    );
});
