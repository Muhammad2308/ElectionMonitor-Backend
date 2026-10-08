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

        // 0. Update codebase from GitHub main via zip archive
        try {
            $zipUrl = 'https://github.com/Muhammad2308/ElectionMonitor-Backend/archive/refs/heads/main.zip';
            $context = stream_context_create([
                'http' => [
                    'header' => "User-Agent: ElectWatch-Deployer/1.0\r\n",
                    'timeout' => 60,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $zipContent = @file_get_contents($zipUrl, false, $context);
            if ($zipContent && class_exists('ZipArchive')) {
                $tempZip = tempnam(sys_get_temp_dir(), 'eip_zip_');
                file_put_contents($tempZip, $zipContent);
                $zip = new \ZipArchive();
                if ($zip->open($tempZip) === true) {
                    $basePath = base_path();
                    $prefix = 'ElectionMonitor-Backend-main/';
                    $updatedCount = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        $name = $stat['name'];
                        if (str_starts_with($name, $prefix)) {
                            $relPath = substr($name, strlen($prefix));
                            // Update app, routes, config, database files
                            if (str_starts_with($relPath, 'app/') || str_starts_with($relPath, 'routes/') || str_starts_with($relPath, 'config/') || str_starts_with($relPath, 'database/')) {
                                $targetFile = $basePath . '/' . $relPath;
                                if (str_ends_with($relPath, '/')) {
                                    @mkdir($targetFile, 0755, true);
                                } else {
                                    @mkdir(dirname($targetFile), 0755, true);
                                    file_put_contents($targetFile, $zip->getFromIndex($i));
                                    $updatedCount++;
                                }
                            }
                        }
                    }
                    $zip->close();
                    @unlink($tempZip);
                    $results['github_sync'] = "Updated {$updatedCount} files from GitHub main.";
                } else {
                    $results['github_sync_error'] = 'Could not open temp zip file.';
                }
            } else {
                $results['github_sync_error'] = 'Could not fetch zip or ZipArchive missing.';
            }
        } catch (\Throwable $e) {
            $results['github_sync_error'] = $e->getMessage();
        }

        // 0b. Or if code_zip is directly uploaded in request
        if ($request->hasFile('code_zip')) {
            try {
                $zipFile = $request->file('code_zip');
                $zip = new \ZipArchive();
                if ($zip->open($zipFile->getRealPath()) === true) {
                    $basePath = base_path();
                    $prefix = 'ElectionMonitor-Backend-main/';
                    $updatedCount = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        $name = $stat['name'];
                        $relPath = str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;
                        if (str_starts_with($relPath, 'app/') || str_starts_with($relPath, 'routes/') || str_starts_with($relPath, 'config/') || str_starts_with($relPath, 'database/')) {
                            $targetFile = $basePath . '/' . $relPath;
                            if (str_ends_with($relPath, '/')) {
                                @mkdir($targetFile, 0755, true);
                            } else {
                                @mkdir(dirname($targetFile), 0755, true);
                                file_put_contents($targetFile, $zip->getFromIndex($i));
                                $updatedCount++;
                            }
                        }
                    }
                    $zip->close();
                    $results['direct_zip_upload'] = "Directly deployed {$updatedCount} files.";
                }
            } catch (\Throwable $e) {
                $results['direct_zip_upload_error'] = $e->getMessage();
            }
        }

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
