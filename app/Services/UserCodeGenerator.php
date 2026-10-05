<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UserCodeGenerator
{
    /**
     * Generates a readable, collision-free user code for a given tenant and role.
     * Format: {ORG_SHORT_CODE}-{STATE_OR_NG}-{ROLE_PREFIX}-{SEQUENCE}
     * E.g., APC-KN-OB-00042
     *
     * @param int $tenantId
     * @param string $orgShortCode e.g. "APC"
     * @param string $stateCode e.g. "KN" or "NG"
     * @param string $roleType e.g. "observer"
     * @return string
     */
    public static function generate(int $tenantId, string $orgShortCode, string $stateCode, string $roleType): string
    {
        $rolePrefix = self::getRolePrefix($roleType);

        // We use a database transaction with a row lock to ensure concurrency safety.
        return DB::transaction(function () use ($tenantId, $orgShortCode, $stateCode, $rolePrefix) {
            $scopeKey = $rolePrefix;

            // Fetch the current sequence with a write lock
            $sequence = DB::table('user_code_sequences')
                ->where('tenant_id', $tenantId)
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $nextValue = $sequence->next_value;
                DB::table('user_code_sequences')
                    ->where('tenant_id', $tenantId)
                    ->where('scope_key', $scopeKey)
                    ->update(['next_value' => $nextValue + 1]);
            } else {
                $nextValue = 1;
                DB::table('user_code_sequences')->insert([
                    'tenant_id' => $tenantId,
                    'scope_key' => $scopeKey,
                    'next_value' => 2,
                ]);
            }

            // Pad the sequence based on role (observers might have many, admins few)
            $padding = $rolePrefix === 'OB' ? 5 : 3;
            $paddedSequence = str_pad($nextValue, $padding, '0', STR_PAD_LEFT);

            return sprintf('%s-%s-%s-%s', $orgShortCode, $stateCode, $rolePrefix, $paddedSequence);
        });
    }

    /**
     * Maps the database role_type enum to a short code for the user_code string.
     */
    private static function getRolePrefix(string $roleType): string
    {
        return match ($roleType) {
            'national_master_admin' => 'NM',
            'state_master_admin'    => 'SM',
            'state_admin'           => 'SA',
            'observer'              => 'OB',
            'cybernet_superadmin'   => 'CS',
            default                 => 'UN', // unknown
        };
    }
}
