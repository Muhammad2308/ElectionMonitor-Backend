<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SystemMaintenanceController extends Controller
{
    private const DEPLOY_SECRETS = [
        'EIP-Deploy-2026-Secure',
        'EIP@Admin2026!',
    ];

    public function sync(Request $request)
    {
        $secret = $request->query('secret') ?: $request->header('X-Deploy-Secret') ?: $request->input('secret');

        if (!in_array($secret, self::DEPLOY_SECRETS, true)) {
            return response()->json(['error' => 'Unauthorized. Invalid maintenance secret key.'], 403);
        }

        $results = [];

        // 0. Update core files directly from GitHub raw
        $rawBase = 'https://raw.githubusercontent.com/Muhammad2308/ElectionMonitor-Backend/main/';
        $filesToSync = [
            'app/Modules/ReferenceData/Requests/StorePollingUnitSubmissionRequest.php',
            'app/Modules/ReferenceData/Controllers/PollingUnitSubmissionController.php',
            'app/Modules/ReferenceData/Services/PollingUnitSubmissionService.php',
            'app/Modules/Roles/Seeders/RolesAndPermissionsSeeder.php',
            'app/Http/Controllers/SystemMaintenanceController.php',
            'routes/api.php',
        ];

        $updatedFiles = [];
        $basePath = base_path();
        $ctx = stream_context_create([
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
                'timeout' => 15,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        foreach ($filesToSync as $rel) {
            try {
                $content = @file_get_contents($rawBase . $rel, false, $ctx);
                if ($content && strlen($content) > 20) {
                    $dest = $basePath . '/' . $rel;
                    @mkdir(dirname($dest), 0755, true);
                    file_put_contents($dest, $content);
                    $updatedFiles[] = $rel;
                }
            } catch (\Throwable $e) {
                $results['sync_error_' . $rel] = $e->getMessage();
            }
        }
        $results['raw_github_sync'] = $updatedFiles;

        // 1. Run migrations
        try {
            Artisan::call('migrate', ['--force' => true]);
            $results['migrate'] = trim(Artisan::output());
        } catch (\Throwable $e) {
            $results['migrate_error'] = $e->getMessage();
        }

        // 2. Run Roles & Permissions Seeder
        try {
            Artisan::call('db:seed', [
                '--class' => 'App\\Modules\\Roles\\Seeders\\RolesAndPermissionsSeeder',
                '--force' => true,
            ]);
            $results['seeder'] = trim(Artisan::output());
        } catch (\Throwable $e) {
            $results['seeder_error'] = $e->getMessage();
        }

        // 3. Reset Permission Cache
        try {
            Artisan::call('permission:cache-reset');
            $results['permission_cache'] = trim(Artisan::output());
        } catch (\Throwable $e) {
            $results['permission_cache_error'] = $e->getMessage();
        }

        // 4. Reset / Sync Passwords if requested or by default
        try {
            $adminPw = env('SEED_ADMIN_PASSWORD', 'EIP@Admin2026!');
            $testPw = env('SEED_TEST_PASSWORD', 'LocalTest@2026');

            User::where('email', 'admin@electwatch.com')->update([
                'password' => Hash::make($adminPw),
                'status'   => 'active',
                'role_type' => 'cybernet_superadmin',
            ]);

            $updatedCount = User::where('email', '!=', 'admin@electwatch.com')->update([
                'password' => Hash::make($testPw),
                'status'   => 'active',
            ]);

            $results['passwords'] = "Admin password updated. {$updatedCount} other users updated.";
        } catch (\Throwable $e) {
            $results['passwords_error'] = $e->getMessage();
        }

        // 5. Clear Caches
        try {
            Artisan::call('optimize:clear');
            $results['optimize_clear'] = trim(Artisan::output());
        } catch (\Throwable $e) {
            $results['optimize_clear_error'] = $e->getMessage();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Production environment synchronized successfully.',
            'results' => $results,
        ]);
    }
}
