<?php

namespace App\Services\Fleet;

use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public const DISK = 'local';

    /**
     * @param  array<string, mixed>  $meta  type, title, issued_date, expires_date, notes
     */
    public function uploadDriverDoc(Driver $driver, UploadedFile $file, array $meta, User $by): DriverDocument
    {
        $path = $this->store("drivers/{$driver->id}", $meta['type'], $file);

        return DriverDocument::create([
            'driver_id' => $driver->id,
            'type' => $meta['type'],
            'title' => $meta['title'],
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_mime' => $file->getMimeType(),
            'issued_date' => $meta['issued_date'] ?? null,
            'expires_date' => $meta['expires_date'] ?? null,
            'notes' => $meta['notes'] ?? null,
            'uploaded_by' => $by->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function uploadVehicleDoc(Vehicle $vehicle, UploadedFile $file, array $meta, User $by): VehicleDocument
    {
        $path = $this->store("vehicles/{$vehicle->id}", $meta['type'], $file);

        return VehicleDocument::create([
            'vehicle_id' => $vehicle->id,
            'type' => $meta['type'],
            'title' => $meta['title'],
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_mime' => $file->getMimeType(),
            'issued_date' => $meta['issued_date'] ?? null,
            'expires_date' => $meta['expires_date'] ?? null,
            'notes' => $meta['notes'] ?? null,
            'uploaded_by' => $by->id,
        ]);
    }

    public function deleteDriverDoc(DriverDocument $doc): void
    {
        Storage::disk(self::DISK)->delete($doc->file_path);
        $doc->delete();
    }

    public function deleteVehicleDoc(VehicleDocument $doc): void
    {
        Storage::disk(self::DISK)->delete($doc->file_path);
        $doc->delete();
    }

    private function store(string $baseDir, string $type, UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'pdf';
        $filename = Str::uuid()->toString().'.'.$ext;
        $path = "{$baseDir}/{$type}/{$filename}";

        Storage::disk(self::DISK)->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        return $path;
    }
}
