<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function search(Request $request)
    {
        $query = Document::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('tracking_number', 'LIKE', "%{$searchTerm}%")
                ->orWhere('title', 'LIKE', "%{$searchTerm}%")
                ->orWhere('subject', 'LIKE', "%{$searchTerm}%");
        }

        return response()->json($query->paginate(10)); // Paginate results
    }

    /**
     * Generate a unique tracking number for each document.
     * Format: TRK-YYYYMMDD-XXXX (incremental per day)
     */
    public function generateTrackingCode(): string
    {
        $date = now()->format('Ymd'); // Current date in YYYYMMDD format

        // Retrieve the last document for today based on tracking number
        $lastDocument = Document::where('tracking_number', 'LIKE', "TRK-{$date}-%")
            ->orderBy('tracking_number', 'desc')
            ->first();

        // Extract the last sequence number and increment it
        $lastSequence = $lastDocument ? (int) substr($lastDocument->tracking_number, -4) : 0;
        $newSequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);

        return "TRK-{$date}-{$newSequence}"; // Return formatted tracking number
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $documents = Document::select([
            'id',
            'tracking_number',
            'document',
            'title',
            'subject',
            'status',
            'date_uploaded',
            'deadline',
        ])->get();

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
        // Validate the request input
        $validated = $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,ppt,pptx|max:10240',
            'title' => 'required|max:255',
            'subject' => 'required|max:255',
            'status' => 'required|max:255',
            'date_uploaded' => 'required|date',
            'deadline' => 'required|date',
        ]);

        $file = $request->file('document'); // Retrieve uploaded file
        $trackingCode = $this->generateTrackingCode(); // Generate unique tracking number
        $documentName = $file->getClientOriginalName(); // Get original file name
        $path = 'PSTO-SDN-DTS/'.$documentName; // Define storage path
        $disk = Storage::disk('sftp'); // Define storage disk

        try {
            // Check if the file already exists on the SFTP server
            if ($disk->exists($path)) {
                return response()->json(['message' => 'File already exists on the SFTP server!'], 400);
            }

            // Check for duplicate entries in the database
            if (Document::where([
                ['tracking_number', $trackingCode],
                ['title', $validated['title']],
                ['subject', $validated['subject']],
                ['status', $validated['status']],
                ['date_uploaded', $validated['date_uploaded']],
                ['deadline', $validated['deadline']],
            ])->exists()) {
                return response()->json(['message' => 'File details already exist in the system!'], 400);
            }

            DB::beginTransaction(); // Start database transaction

            // Upload file to SFTP server
            if (! $disk->putFileAs('PSTO-SDN-DTS', $file, $documentName)) {
                DB::rollBack(); // Rollback transaction if upload fails

                return response()->json(['message' => 'File upload failed!'], 500);
            }

            // Store document details in the database
            Document::create([
                'tracking_number' => $trackingCode,
                'document' => $documentName,
                'title' => $validated['title'],
                'subject' => $validated['subject'],
                'status' => $validated['status'],
                'date_uploaded' => $validated['date_uploaded'],
                'deadline' => $validated['deadline'],
                'filepath' => $path,
            ]);

            DB::commit(); // Commit transaction

            return response()->json(['message' => 'Upload successful'], 201);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback changes in case of error

            return response()->json(['message' => 'An error occurred during upload', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Document $document)
    {
        return response()->json(['document' => $document]);
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
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Document $document)
    {
        $disk = Storage::disk('sftp');

        // Validate the request
        $validated = $request->validate([
            'document' => 'nullable|file|max:10240',
            'title' => 'required|max:255',
            'subject' => 'required|max:255',
            'status' => 'required|max:255',
            'date_uploaded' => 'required|date',
            'deadline' => 'required|date',
        ]);

        if ($request->hasFile('document')) {
            $uploadedFile = $request->file('document');
            $documentName = time().'_'.$uploadedFile->getClientOriginalName(); // Ensure unique filename
            $path = 'PSTO-SDN-FMS/'.$documentName;

            // Check if the file already exists
            if ($disk->exists($path)) {
                return response()->json([
                    'message' => 'File already exists on the SFTP server!',
                ], 400);
            }

            // Attempt to upload new file before deleting old one
            if ($disk->put($path, file_get_contents($uploadedFile))) {
                // Delete the old file only if a previous file exists and is different
                if ($document->document) {
                    $oldPath = 'PSTO-SDN-FMS/'.$document->document;
                    if ($disk->exists($oldPath)) {
                        $disk->delete($oldPath);
                    }
                }

                // Update filename in database
                $document->document = $documentName;
            } else {
                return response()->json([
                    'message' => 'Failed to upload document to SFTP server.',
                ], 500);
            }
        }

        // Update other file details in database
        $document->update([
            'document' => $document->document, // Fixed incorrect reference
            'title' => $validated['title'],
            'subject' => $validated['subject'],
            'status' => $validated['status'],
            'date_uploaded' => $validated['date_uploaded'],
            'deadline' => $validated['deadline'],
        ]);

        return response()->json([
            'message' => 'Document details updated successfully!',
            'file' => $document,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $document = Document::find($id); // Find document by ID

        if (! $document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        try {
            $disk = Storage::disk('sftp');

            // Remove the file from SFTP storage if it exists
            if ($disk->exists($document->filepath)) {
                $disk->delete($document->filepath);
            }

            $document->delete(); // Delete record from database

            return response()->json(['message' => 'Document deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete document', 'error' => $e->getMessage()], 500);
        }
    }
}
