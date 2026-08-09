<?php

declare(strict_types=1);

namespace App\Application\Identity\Permissions;

use JsonSerializable;

final readonly class PermissionModelKeyMigrationReport implements JsonSerializable
{
    /**
     * @param  array<string, array<string, mixed>>  $source
     * @param  array<string, array<string, mixed>>  $shadow
     * @param  array<string, int>  $anomalies
     * @param  list<string>  $blockingIssues
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $checkpoint
     * @param  array<string, mixed>  $artifact
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $phase,
        public string $status,
        public string $shape,
        public string $verificationScope = 'LOCALLY_VERIFIED_SQLITE',
        public string $driver = '',
        public string $databaseVersion = '',
        public array $source = [],
        public array $shadow = [],
        public array $anomalies = [],
        public array $blockingIssues = [],
        public array $schema = [],
        public array $checkpoint = [],
        public array $artifact = [],
        public int $examined = 0,
        public int $copied = 0,
        public int $alreadyPresent = 0,
        public bool $dryRun = false,
        public bool $writesPerformed = false,
        public array $details = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $anomalies = $this->anomalies;
        ksort($anomalies);

        $blockingIssues = array_values(array_unique($this->blockingIssues));
        sort($blockingIssues);

        return [
            'phase' => $this->phase,
            'status' => $this->status,
            'shape' => $this->shape,
            'verification_scope' => $this->verificationScope,
            'driver' => $this->driver,
            'database_version' => $this->databaseVersion,
            'source' => self::canonicalize($this->source),
            'shadow' => self::canonicalize($this->shadow),
            'anomalies' => $anomalies,
            'blocking_issues' => $blockingIssues,
            'schema' => self::canonicalize($this->schema),
            'checkpoint' => self::canonicalize($this->checkpoint),
            'artifact' => self::canonicalize($this->artifact),
            'examined' => $this->examined,
            'copied' => $this->copied,
            'already_present' => $this->alreadyPresent,
            'dry_run' => $this->dryRun,
            'writes_performed' => $this->writesPerformed,
            'details' => self::canonicalize($this->details),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
