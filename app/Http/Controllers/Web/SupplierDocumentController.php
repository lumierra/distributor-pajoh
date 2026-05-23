<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\UploadDocumentRequest;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;

class SupplierDocumentController extends Controller
{
    public function store(UploadDocumentRequest $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        // Storage path: suppliers/{id}/{type}/{uuid}.{ext}
        $directory = "suppliers/{$supplier->id}/{$data['type']}";
        $filename = (string) \Illuminate\Support\Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'local');

        $supplier->documents()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_mime' => $file->getMimeType(),
            'issued_date' => $data['issued_date'] ?? null,
            'expires_date' => $data['expires_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('flash.success', 'Dokumen diunggah.');
    }

    public function show(SupplierDocument $document): HttpResponse
    {
        $this->authorize('view', $document->supplier);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File dokumen tidak ditemukan.');
        }

        return response()->file(Storage::disk('local')->path($document->file_path), [
            'Content-Type' => $document->file_mime ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.basename($document->file_path).'"',
        ]);
    }

    public function destroy(SupplierDocument $document): RedirectResponse
    {
        $this->authorize('update', $document->supplier);

        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }
        $document->delete();

        return back()->with('flash.success', 'Dokumen dihapus.');
    }
}
