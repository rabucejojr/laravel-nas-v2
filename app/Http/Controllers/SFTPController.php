<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class SFTPController extends Controller
{
    public function getStorageDetails()
    {
        try {
            $disk = Storage::disk('sftp');

            // Get file count and total size
            $files = $disk->allFiles();
            $fileCount = count($files);  // Calculate total size (in bytes)
            $storage_used = array_sum(array_map(fn ($file) => $disk->size($file), $files));
            $storage_used_in_gb = round($storage_used / 1024 / 1024 / 1024, 2);

            return response()->json([
                'file_count' => $fileCount,
                'storage_used' => $storage_used_in_gb, // Size in gb
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch SFTP storage details',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
