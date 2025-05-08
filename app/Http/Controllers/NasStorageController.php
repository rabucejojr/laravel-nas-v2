<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class NasStorageController extends Controller
{
    public function getNasStorageInfo()
    {
        try {
            // Fetch from config (.env)
            $baseUrl = rtrim(config('nas.base_url'), '/');
            $username = config('nas.username');
            $password = config('nas.password');

            // Step 1: Login and get SID
            $loginResponse = Http::withoutVerifying()->get("{$baseUrl}/webapi/auth.cgi", [
                'api' => 'SYNO.API.Auth',
                'version' => 6,
                'method' => 'login',
                'account' => $username,
                'passwd' => $password,
                'session' => 'FileStation',
                'format' => 'sid',
            ]);

            $loginData = $loginResponse->json();

            if (
                !$loginResponse->ok() ||
                empty($loginData['success']) ||
                empty($loginData['data']['sid'])
            ) {
                return response()->json(['message' => 'Failed to login to NAS.'], $loginResponse->status());
            }

            $sid = $loginData['data']['sid'];

            // Step 2: Fetch Storage Info
            $storageResponse = Http::withoutVerifying()->get("{$baseUrl}/webapi/entry.cgi", [
                'api' => 'SYNO.Storage.CGI.Storage',
                'method' => 'load_info',
                'version' => 1,
                '_sid' => $sid,
            ]);

            $storageData = $storageResponse->json();

            if (
                !$storageResponse->ok() ||
                empty($storageData['success']) ||
                empty($storageData['data']['volumes'][0]['size'])
            ) {
                return response()->json(['message' => 'Failed to fetch NAS Storage Info.'], $storageResponse->status());
            }

            // Extract only used and free storage
            $sizeInfo = $storageData['data']['volumes'][0]['size'];

            // Convert bytes to gigabytes (GB)
            $total = (float) $sizeInfo['total'] / (1024 ** 3);
            $used = (float) $sizeInfo['used'] / (1024 ** 3);
            $free = $total - $used;

            return response()->json([
                'used_storage' => $used,
                'free_storage' => $free,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
