<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UploadDocumentRequest;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Services\Fleet\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DriverDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $service) {}

    public function store(UploadDocumentRequest $request, Driver $driver): RedirectResponse
    {
        $this->service->uploadDriverDoc(
            $driver,
            $request->file('file'),
            $request->validated(),
            $request->user(),
        );

        return back()->with('flash.success', 'Dokumen driver diunggah.');
    }

    public function show(DriverDocument $document): Response
    {
        $this->authorize('view', $document->driver);

        abort_unless(Storage::disk(DocumentService::DISK)->exists($document->file_path), 404);

        return response()->file(
            Storage::disk(DocumentService::DISK)->path($document->file_path),
        );
    }

    public function destroy(DriverDocument $document): RedirectResponse
    {
        $this->authorize('deleteDocument', $document->driver);

        $this->service->deleteDriverDoc($document);

        return back()->with('flash.success', 'Dokumen driver dihapus.');
    }
}
