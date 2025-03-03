<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function generateTrackingCode()
    {
        // Get the current date in YYYYMMDD format
        $date = now()->format('Ymd');

        // Find the last document created today
        $lastDocument = Document::where('tracking_number', 'LIKE', "TRK-{$date}-%")
            ->orderBy('tracking_number', 'desc')
            ->first();

        // Extract the last sequence number and increment it
        $lastSequence = $lastDocument ? (int) substr($lastDocument->tracking_number, -4) : 0;
        $newSequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);

        // Generate new tracking number
        $trackingNumber = "TRK-{$date}-{$newSequence}";

        return response()->json(['tracking_number' => $trackingNumber]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $documents = Document::select(['id', 'tracking_number', 'filename', 'title', 'subject', 'status', 'date_uploaded', 'deadline'])->get();

        return response()->json(['documents' => $documents]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
            'title' => 'required|max:255',
            'subject' => 'required|max:255',
            'status' => 'required|max:255',
            'date_uploaded' => 'required|date',
            'deadline' => 'required|date',
        ]);

        // Generate tracking number from the dedicated function
        $trackingCode = $this->generateTrackingCode();
        $disk = Storage::disk('sftp');
        $file = $request->file('file');

        if ($validated) {
            $filename = $file->getClientOriginalName();
            $path = 'PSTO-SDN-DTS/'.$filename;

            // Check if file already exists in storage
            if ($disk->exists($path)) {
                return response()->json([
                    'message' => 'File already exists on the SFTP server!',
                ], 400);
            }

            // Check if file details already exist in database
            $existingFile = Document::where([
                ['tracking_number', $trackingCode],
                ['title', $request->input('title')],
                ['subject', $request->input('subject')],
                ['status', $request->input('status')],
                ['date_uploaded', $request->input('date_uploaded')],
                ['deadline', $request->input('deadline')],
            ])->exists();

            if ($existingFile) {
                return response()->json([
                    'message' => 'File details already exist in the system!',
                ], 400);
            }

            $fileUploadSuccess = $disk->putFileAs('PSTO-SDN-DTS', $file, $filename);

            if (! $fileUploadSuccess) {
                return response()->json(['message' => 'Upload failed'], 500);
            }

            Document::create([
                'filename' => $filename,
                'tracking_number' => $trackingCode,
                'title' => $request->input('title'),
                'subject' => $request->input('subject'),
                'status' => $request->input('status'),
                'date_uploaded' => $request->input('date_uploaded'),
                'deadline' => $request->input('deadline'),
                'filepath' => $path,
            ]);

            return response()->json(['message' => 'Upload successful']);
        }

        return response()->json(['message' => 'Upload failed'], 400);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
