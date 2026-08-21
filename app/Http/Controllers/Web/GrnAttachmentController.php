<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grn\UploadGrnAttachmentRequest;
use App\Models\GoodsReceipt;
use App\Models\GrnAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GrnAttachmentController extends Controller
{
    public function store(UploadGrnAttachmentRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files', []);

        foreach ($files as $file) {
            $ext = $file->getClientOriginalExtension();
            $filename = (string) Str::uuid().($ext !== '' ? ".{$ext}" : '');
            $path = $file->storeAs("grns/{$goodsReceipt->id}/attachments", $filename, 'local');

            $goodsReceipt->attachments()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_mime' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $count = count($files);

        return back()->with('flash.success', "{$count} lampiran diunggah.");
    }

    public function show(GrnAttachment $grnAttachment): BinaryFileResponse
    {
        $this->authorize('view', $grnAttachment->goodsReceipt);

        if (! Storage::disk('local')->exists($grnAttachment->file_path)) {
            abort(404, 'File lampiran tidak ditemukan.');
        }

        return response()->file(Storage::disk('local')->path($grnAttachment->file_path), [
            'Content-Type' => $grnAttachment->file_mime ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$grnAttachment->original_name.'"',
        ]);
    }

    public function destroy(GrnAttachment $grnAttachment): RedirectResponse
    {
        $this->authorize('manageAttachments', $grnAttachment->goodsReceipt);

        if (Storage::disk('local')->exists($grnAttachment->file_path)) {
            Storage::disk('local')->delete($grnAttachment->file_path);
        }
        $grnAttachment->delete();

        return back()->with('flash.success', 'Lampiran dihapus.');
    }
}
