<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;

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
        //
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
     */
    public function store(Request $request)
    {
        // Generate tracking number
        $trackingNumber = $this->generateTrackingCode()->getData()->tracking_number;

        // Save the document
        $document = Document::create([
            'tracking_number' => $trackingNumber,
            'title' => $request->title,
            'subject' => $request->subject,
            'status' => $request->status,
            'date_uploaded' => now(),
            'deadline' => $request->deadline,
        ]);

        return response()->json($document, 201);
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
