<?php

declare(strict_types=1);

namespace Tests\Architecture;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;

uses(\Tests\TestCase::class);

test('Actions map all inputs to Command constructor parameters', function () {
    // 1. Discovery: Find all Actions
    $actionsPath = __DIR__ . '/../../app/Actions';
    $actionFiles = \Illuminate\Support\Facades\File::allFiles($actionsPath);
    
    foreach ($actionFiles as $file) {
        $className = 'App\\Actions\\' . str_replace(
            ['/', '.php'], 
            ['\\', ''], 
            $file->getRelativePathname()
        );
        
        if (!class_exists($className)) {
            continue;
        }

        $reflector = new ReflectionClass($className);
        
        // Skip abstract classes or traits
        if ($reflector->isAbstract() || $reflector->isTrait()) {
            continue;
        }

        // 2. Identify Dispatched Command
        // We look for 'new CommandName(...)' inside the file content to guess the dispatched command.
        // This is a heuristic because we can't easily execute the code to see what it dispatches without mocking.
        $content = file_get_contents($file->getPathname());
        
        // Regex to find instantiation of a Command ending in 'Command'
        // Pattern: new \App\Application\...\SomeCommand( or new SomeCommand(
        preg_match_all('/new\s+([a-zA-Z0-9_\\\\]*Command)\s*\(/', $content, $matches);
        
        if (empty($matches[1])) {
            // Check if it uses a command alias or import
            continue; 
        }

        foreach ($matches[1] as $commandClassShortName) {
            $commandClass = resolveFullClassName($className, $commandClassShortName);
            
            if (!class_exists($commandClass)) {
                continue;
            }

            // 3. Verify Constructor Parity
            // Ideally, we'd verify that the Action *passes* all required parameters.
            // Since static analysis of the *values* passed is hard, we enforce a weaker but useful contract:
            // "If an Action dispatches a Command, that Command MUST explicitly type all its parameters (no mixed/untyped)."
            // "And the Action file MUST contain strings that resemble the parameter names." 
            
            $cmdReflector = new ReflectionClass($commandClass);
            $constructor = $cmdReflector->getConstructor();
            
            if (!$constructor) {
                continue;
            }

            foreach ($constructor->getParameters() as $param) {
                // Skip optional parameters (they have default values)
                if ($param->isOptional()) {
                    continue;
                }

                // Architectural Rule: Commands must have typed properties
                expect($param->hasType())
                    ->toBeTrue("Command parameter [\${$param->getName()}] in [{$commandClass}] must be typed.");
                
                // Heuristic Rule: The Action source code must mention the parameter name.
                // This catches typos like passing 'email' to 'username'.
                // It's not perfect but catches 80% of mapping errors.
                // We search for "name:" (named argument) or "$input['name']" usage.
                $paramName = $param->getName();
                
                // Allow some common mismatches if explicitly handled (e.g., 'tenantId' vs 'id')
                // But generally, we want named arguments usage.
                $hasNamedArg = str_contains($content, "$paramName:");
                $hasVariable = str_contains($content, "\$$paramName");
                $hasArrayKey = str_contains($content, "['$paramName']");
                
                // Special Case: 'userId' is often passed as 'getAuthIdentifier' or 'id'
                if ($paramName === 'userId') {
                    $hasAuthIdentifier = str_contains($content, 'getAuthIdentifier') || str_contains($content, '->id');
                } else {
                    $hasAuthIdentifier = false;
                }

                // Special Case: 'tenantId' might just be resolved via 'getTenantId'
                if ($paramName === 'tenantId') {
                    $hasTenantId = str_contains($content, 'getTenantId');
                } else {
                    $hasTenantId = false;
                }
                
                if (!($hasNamedArg || $hasVariable || $hasArrayKey || $hasAuthIdentifier || $hasTenantId)) {
                    // Debug output for failure
                    echo "\n[FAIL] Action: $className\nCommand: $commandClass\nMissing Param: \$$paramName\n";
                }

                // We enforce that the parameter name appears in the Action file.
                // This encourages using named arguments: new Command(param: $value)
                expect($hasNamedArg || $hasVariable || $hasArrayKey || $hasAuthIdentifier || $hasTenantId)
                    ->toBeTrue("Action [{$className}] dispatches [{$commandClass}] but does not seem to reference parameter [\${$paramName}]. Use named arguments for safety.");
            }
        }
    }
});

function resolveFullClassName(string $contextClass, string $shortName): string {
    if (str_starts_with($shortName, '\\')) {
        return $shortName;
    }
    
    // Simple resolution: check imports in the file
    $reflector = new ReflectionClass($contextClass);
    $filename = $reflector->getFileName();
    $content = file_get_contents($filename);
    
    // Look for "use App\...\ShortName;"
    if (preg_match("/use\s+([a-zA-Z0-9_\\\\]+)\\\\${shortName};/", $content, $matches)) {
        return $matches[1] . '\\' . $shortName;
    }
    
    // If inside same namespace
    return $reflector->getNamespaceName() . '\\' . $shortName;
}
