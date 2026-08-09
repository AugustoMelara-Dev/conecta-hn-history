<?php

declare(strict_types=1);

namespace App\Application\Imports\Imports;

use App\Application\Imports\Concerns\HasImportTemplate;
use App\Application\Imports\Concerns\Importable;
use App\Models\User;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable as ExcelImportable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class UsersImport implements Importable, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use ExcelImportable;
    use HasImportTemplate;

    public function __construct(private readonly string $tenantId) {}

    public function entity(): string
    {
        return 'users';
    }

    public function modelClass(): string
    {
        return User::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', 'in:owner,manager,agent,viewer'],
        ];
    }

    public function uniqueBy(): array
    {
        return ['email'];
    }

    public function templateHeaders(): array
    {
        return ['name', 'email', 'role', 'password'];
    }

    public function model(array $row): ?User
    {
        $user = User::create([
            'tenant_id' => $this->tenantId,
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => bcrypt($row['password'] ?? Str::random(16)),
        ]);
        if (! empty($row['role'])) {
            $user->assignRole($row['role']);
        }

        return $user;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }
}
