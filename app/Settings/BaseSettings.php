<?php

declare(strict_types=1);

namespace App\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionProperty;

/**
 * Native Laravel replacement for Spatie/LaravelSettings.
 * Maps class properties to the 'settings' table.
 */
abstract class BaseSettings
{
    protected static array $runtimeCache = [];

    abstract public static function group(): string;

    public function __construct()
    {
        $this->load();
    }

    public function load(): void
    {
        $group = static::group();

        if (! isset(self::$runtimeCache[$group])) {
            self::$runtimeCache[$group] = Cache::rememberForever("settings.{$group}", function () use ($group) {
                return DB::table('settings')
                    ->where('group', $group)
                    ->pluck('payload', 'name')
                    ->toArray();
            });
        }

        $payloads = self::$runtimeCache[$group];
        $properties = (new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            if (array_key_exists($name, $payloads)) {
                $this->{$name} = json_decode((string) $payloads[$name], true);
            }
        }
    }

    public function save(): void
    {
        $group = static::group();
        $properties = (new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC);

        DB::transaction(function () use ($group, $properties) {
            foreach ($properties as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $name = $property->getName();

                if ($property->isInitialized($this)) {
                    $value = $property->getValue($this);

                    DB::table('settings')->updateOrInsert(
                        ['group' => $group, 'name' => $name],
                        ['payload' => json_encode($value), 'locked' => false, 'updated_at' => now()]
                    );
                }
            }
        });

        Cache::forget("settings.{$group}");
        unset(self::$runtimeCache[$group]);
    }
}
