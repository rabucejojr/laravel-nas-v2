<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');
        $files = File::where('filename', 'like', "%{$query}%")
            ->orWhere('uploader', 'like', "%{$query}%")
            ->orWhere('category', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $files = $query->paginate(10); // Paginate results

        return response()->json($files);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $files = File::select(['id', 'filename', 'uploader', 'date', 'category'])->get();

        return response()->json(['files' => $files]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        return response()->json(['message' => 'file create endpoint']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,ppt,pptx|max:10240',
            'uploader' => 'required|max:255',
            'category' => 'required|max:255',
            'date' => 'required|date',
        ]);

        $disk = Storage::disk('sftp');

        if ($validated) {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            $path = 'PSTO-SDN-FMS/'.$filename;

            // Check if file already exists in storage
            if ($disk->exists($path)) {
                return response()->json([
                    'message' => 'File already exists on the SFTP server!',
                ], 400);
            }

            // Check if file details already exist in database
            $existingFile = File::where('filename', $filename)
                ->where('uploader', $request->input('uploader'))
                ->where('category', $request->input('category'))
                ->where('date', $request->input('date'))
                ->first();

            if ($existingFile) {
                return response()->json([
                    'message' => 'File details already exist in the system!',
                ], 400);
            }

            $fileUploadSuccess = $disk->put($path, file_get_contents($file));

            if (! $fileUploadSuccess) {
                return response()->json(['message' => 'Upload failed'], 500);
            }

            File::create([
                'filename' => $filename,
                'uploader' => $request->input('uploader'),
                'category' => $request->input('category'),
                'date' => $request->input('date'),
                'filepath' => $path,
            ]);

            return response()->json(['message' => 'Upload successful']);
        }

        return response()->json(['message' => 'Upload failed'], 400);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(File $file)
    {
        return response()->json(['file' => $file]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, File $file)
    {
        $disk = Storage::disk('sftp');

        // Validate the request
        $validated = $request->validate([
            'file' => 'nullable|file|max:10240',
            'uploader' => 'required|max:255',
            'category' => 'required|max:255',
            'date' => 'required|date',
        ]);

        // Check if a new file is uploaded
        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $filename = $uploadedFile->getClientOriginalName(); // Ensure unique filename
            $path = 'PSTO-SDN-FMS/'.$filename;

            // Check if the file already exists
            if ($disk->exists($path)) {
                return response()->json([
                    'message' => 'File already exists on the SFTP server!',
                ], 400);
            }

            // Attempt to upload new file before deleting old one
            if ($disk->put($path, file_get_contents($uploadedFile))) {
                // Delete the old file only if the new one was uploaded successfully
                $oldPath = 'PSTO-SDN-FMS/'.$file->filename;
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }

                // Update filename in database
                $file->filename = $filename;
                $file->filepath = $path; // Update file path
            } else {
                return response()->json([
                    'message' => 'Failed to upload file to SFTP server.',
                ], 500);
            }
        }

        // Update other file details in the database
        $file->update([
            'filename' => $file->filename, // Ensure filename is updated
            'uploader' => $validated['uploader'],
            'category' => $validated['category'],
            'date' => $validated['date'],
            'filepath' => $file->filepath ?? null, // Ensure filepath is not undefined
        ]);

        return response()->json([
            'message' => 'File details updated successfully!',
            'file' => $file,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(File $file)
    {
        $disk = Storage::disk('sftp');
        $filePath = 'PSTO-SDN-FMS/'.$file->filename;

        if ($disk->exists($filePath)) {
            $disk->delete($filePath);
        }

        $file->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }
}
