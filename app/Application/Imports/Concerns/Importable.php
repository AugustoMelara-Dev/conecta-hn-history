<?php

declare(strict_types=1);

namespace App\Application\Imports\Concerns;

/**
 * Importable - Interface que cada Import class implementa.
 *
 * Define las reglas de validación y la lógica de mapeo de columnas.
 */
interface Importable
{
    /**
     * Nombre de la entidad (e.g. 'contacts', 'products').
     */
    public function entity(): string;

    /**
     * Eloquent model class destino.
     */
    public function modelClass(): string;

    /**
     * Reglas de validación por columna (key => rule).
     *
     * @return array<string, string|array>
     */
    public function rules(): array;

    /**
     * Columna(s) que identifican unicidad (para update o skip).
     *
     * @return array<int, string>
     */
    public function uniqueBy(): array;

    /**
     * Headers esperados en el archivo CSV/Excel (orden importa para templates).
     *
     * @return array<int, string>
     */
    public function templateHeaders(): array;

    /**
     * Fila de ejemplo para template (valores dummy).
     *
     * @return array<string, mixed>
     */
    public function templateSampleRow(): array;
}
