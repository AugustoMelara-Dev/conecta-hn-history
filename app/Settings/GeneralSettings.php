<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * GeneralSettings - Torre de Control del Sistema
 * 
 * DTO tipado para gestión centralizada de configuraciones.
 * Uso: app(GeneralSettings::class)->pro_plan_price
 */
class GeneralSettings extends Settings
{
    // ═══════════════════════════════════════════════════════════════════════
    // FINANZAS Y SUSCRIPCIONES
    // ═══════════════════════════════════════════════════════════════════════
    
    /** Precio mensual del Plan PRO en Lempiras */
    public float $pro_plan_price;
    
    /** Días de gracia antes de suspender servicio */
    public int $grace_period_days;
    
    /** Límite de productos para cuentas gratuitas */
    public int $free_plan_product_limit;
    
    /** Límite de productos para cuentas PRO */
    public int $pro_plan_product_limit;

    // ═══════════════════════════════════════════════════════════════════════
    // INFORMACIÓN BANCARIA Y SOPORTE
    // ═══════════════════════════════════════════════════════════════════════
    
    /** Instrucciones de transferencia bancaria (HTML/Markdown) */
    public string $bank_transfer_instructions;
    
    /** Número de WhatsApp de soporte */
    public string $support_whatsapp_number;
    
    /** Email de soporte */
    public string $support_email;

    // ═══════════════════════════════════════════════════════════════════════
    // INTERRUPTORES DE SISTEMA
    // ═══════════════════════════════════════════════════════════════════════
    
    /** Modo mantenimiento activo */
    public bool $maintenance_mode;
    
    /** Permitir nuevos registros */
    public bool $allow_registrations;

    /**
     * Grupo de configuraciones en la base de datos
     */
    public static function group(): string
    {
        return 'general';
    }
}
