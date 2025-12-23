<?php

namespace App\Contracts;

/**
 * ResetsStaticCache - Infrastructure Contract for Memory Hygiene
 * 
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INFRASTRUCTURE CONTRACT - NOT FOR BUSINESS LOGIC                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 * 
 * PURPOSE:
 * This interface defines a contract for classes that maintain static state
 * which must be purged between queue job executions to prevent state pollution
 * in long-running daemon processes.
 * 
 * DESIGN PATTERN: Interface Segregation Principle (ISP)
 * By programming against this abstraction, the AppServiceProvider becomes
 * decoupled from concrete Observer implementations. New Observers can be
 * added to the cleanup registry without modifying infrastructure code.
 * 
 * USAGE:
 * 1. Implement this interface on any class with static caches
 * 2. Register the class in AppServiceProvider::$resettableClasses
 * 3. The Queue::after listener will automatically call flushStaticCache()
 * 
 * IMPLEMENTORS:
 * - App\Observers\ProductObserver (tenant city memoization)
 * - Future: Any Observer/Service with static state
 * 
 * @see \App\Providers\AppServiceProvider::boot() for the cleanup loop
 */
interface ResetsStaticCache
{
    /**
     * Purge all static state to factory defaults.
     * 
     * This method MUST:
     * - Reset all static properties to their initial values
     * - Be idempotent (safe to call multiple times)
     * - Be zero-cost when cache is already empty
     * - NOT throw exceptions
     * 
     * Called automatically by Queue::after event listener.
     * 
     * @return void
     */
    public static function flushStaticCache(): void;
}
