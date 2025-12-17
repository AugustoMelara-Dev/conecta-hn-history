<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Migración de valores iniciales para GeneralSettings
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // FINANZAS Y SUSCRIPCIONES
        // ═══════════════════════════════════════════════════════════════════
        $this->migrator->add('general.pro_plan_price', 500.00);
        $this->migrator->add('general.grace_period_days', 3);
        $this->migrator->add('general.free_plan_product_limit', 10);
        $this->migrator->add('general.pro_plan_product_limit', 100);

        // ═══════════════════════════════════════════════════════════════════
        // INFORMACIÓN BANCARIA Y SOPORTE
        // ═══════════════════════════════════════════════════════════════════
        $this->migrator->add('general.bank_transfer_instructions', '<p><strong>Instrucciones de Pago:</strong></p>
<p>Depositar a Cuenta BAC Honduras #123456789</p>
<p>A nombre de: Conecta HN</p>
<p>RTN: 0801-1234-56789</p>
<p>Después de realizar el depósito, suba su comprobante en esta plataforma.</p>');

        $this->migrator->add('general.support_whatsapp_number', '+50499999999');
        $this->migrator->add('general.support_email', 'soporte@conectahn.com');

        // ═══════════════════════════════════════════════════════════════════
        // INTERRUPTORES DE SISTEMA
        // ═══════════════════════════════════════════════════════════════════
        $this->migrator->add('general.maintenance_mode', false);
        $this->migrator->add('general.allow_registrations', true);
    }
};
