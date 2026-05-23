<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicle\UploadDocumentRequest;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Fleet\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class VehicleDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $service) {}

    public function store(UploadDocumentRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->service->uploadVehicleDoc(
            $vehicle,
            $request->file('file'),
            $request->validated(),
            $request->user(),
        );

        return back()->with('flash.success', 'Dokumen vehicle diunggah.');
    }

    public function show(VehicleDocument $document): Response
    {
        $this->authorize('view', $document->vehicle);

        abort_unless(Storage::disk(DocumentService::DISK)->exists($document->file_path), 404);

        return response()->file(
            Storage::disk(DocumentService::DISK)->path($document->file_path),
        );
    }

    public function destroy(VehicleDocument $document): RedirectResponse
    {
        $this->authorize('deleteDocument', $document->vehicle);

        $this->service->deleteVehicleDoc($document);

        return back()->with('flash.success', 'Dokumen vehicle dihapus.');
    }
}
