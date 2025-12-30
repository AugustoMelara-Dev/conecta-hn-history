<?php

declare(strict_types=1);

namespace App\Casts;

use App\DataTransferObjects\TenantSettingsDTO;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * TenantConfigCast - JSON Column to DTO Transformation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Structural Consistency in NoSQL Columns        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: JSON columns in SQL databases behave like NoSQL documents.     ║
 * ║    - No schema enforcement at database level                            ║
 * ║    - Each row can have different JSON structure                         ║
 * ║    - Application crashes when accessing missing keys                     ║
 * ║                                                                          ║
 * ║  SOLUTION: The Cast acts as a "Schema Adapter" layer                     ║
 * ║    - INBOUND: JSON → DTO with default hydration for missing keys        ║
 * ║    - OUTBOUND: DTO → Validated JSON, preventing malformed persistence   ║
 * ║                                                                          ║
 * ║  DEPLOYMENT SAFETY:                                                      ║
 * ║    Old JSON in DB: {"brand_color": "#3B82F6"}                           ║
 * ║    New deployment adds: whatsapp_enabled, invoice_prefix                 ║
 * ║    Result: Old rows hydrate correctly with new defaults                  ║
 * ║                                                                          ║
 * ║  This eliminates the need for data migrations when adding new config    ║
 * ║  keys, as the DTO provides sensible defaults automatically.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE in Model:
 *   protected $casts = ['settings' => TenantConfigCast::class];
 *   $tenant->settings->brandColor; // Property access with IDE support
 */
class TenantConfigCast implements CastsAttributes
{
    /**
     * Transform the database JSON into a TenantSettingsDTO.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value Raw JSON string or array from database
     * @param array $attributes
     * @return TenantSettingsDTO
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): TenantSettingsDTO
    {
        // Handle null or empty values - return DTO with all defaults
        if ($value === null || $value === '') {
            return TenantSettingsDTO::fromArray([]);
        }

        // Decode JSON string if needed
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            
            // Handle JSON decode errors gracefully
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Illuminate\Support\Facades\Log::warning(
                    "TenantConfigCast: Invalid JSON in '{$key}' column, using defaults",
                    [
                        'model' => get_class($model),
                        'model_id' => $model->getKey(),
                        'json_error' => json_last_error_msg(),
                    ]
                );
                
                return TenantSettingsDTO::fromArray([]);
            }
            
            $value = $decoded;
        }

        // If it's already an array (from Laravel's JSON casting layer)
        if (is_array($value)) {
            return TenantSettingsDTO::fromArray($value);
        }

        // Fallback: unexpected type, return defaults
        return TenantSettingsDTO::fromArray([]);
    }

    /**
     * Transform the TenantSettingsDTO for database storage.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value DTO object, array, or null
     * @param array $attributes
     * @return string JSON string for storage
     * @throws InvalidArgumentException if invalid type is passed
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        // Handle null - store empty JSON object
        if ($value === null) {
            return '{}';
        }

        // Handle DTO (THE CORRECT WAY)
        if ($value instanceof TenantSettingsDTO) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
        }

        // Handle raw array (convenience for mass assignment)
        if (is_array($value)) {
            // Validate by round-tripping through DTO
            $dto = TenantSettingsDTO::fromArray($value);
            return json_encode($dto->toArray(), JSON_THROW_ON_ERROR);
        }

        throw new InvalidArgumentException(
            "Invalid type for '{$key}'. Expected TenantSettingsDTO or array, got " . get_debug_type($value)
        );
    }
}
