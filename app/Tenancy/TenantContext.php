<?php

namespace App\Tenancy;

use Exception;

class TenantContext
{
    private static ?int $currentTenantId = null;
    private static bool $isBypassed = false;
    private static ?int $bypassedByUserId = null;

    /**
     * Set the current tenant context.
     */
    public static function setTenantId(int $tenantId): void
    {
        self::$currentTenantId = $tenantId;
        self::$isBypassed = false;
        self::$bypassedByUserId = null;
    }

    /**
     * Get the current tenant context ID.
     */
    public static function getTenantId(): ?int
    {
        return self::$currentTenantId;
    }

    /**
     * Clear the current tenant context.
     */
    public static function clear(): void
    {
        self::$currentTenantId = null;
        self::$isBypassed = false;
        self::$bypassedByUserId = null;
    }

    /**
     * Temporarily bypass the tenant scope.
     * This is intended for platform-level actions (e.g. cybernet_superadmin with a grant)
     * and MUST be used carefully.
     *
     * @param int $userId The ID of the superadmin bypassing the scope for audit purposes.
     * @param callable $callback The code to execute without tenant scope.
     * @return mixed
     * @throws Exception
     */
    public static function runAsPlatform(int $userId, callable $callback)
    {
        $previousTenantId = self::$currentTenantId;
        $previousBypassed = self::$isBypassed;
        $previousBypassedBy = self::$bypassedByUserId;

        try {
            self::$currentTenantId = null;
            self::$isBypassed = true;
            self::$bypassedByUserId = $userId;

            return $callback();
        } finally {
            self::$currentTenantId = $previousTenantId;
            self::$isBypassed = $previousBypassed;
            self::$bypassedByUserId = $previousBypassedBy;
        }
    }

    /**
     * Check if the scope is currently bypassed.
     */
    public static function isBypassed(): bool
    {
        return self::$isBypassed;
    }
}
