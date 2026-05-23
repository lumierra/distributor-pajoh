<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerPhotoService
{
    public const MAX_PHOTOS_PER_CUSTOMER = 10;

    public const DISK = 'public';

    public function upload(
        Customer $customer,
        UploadedFile $file,
        string $type,
        User $by,
        ?string $caption = null,
    ): CustomerPhoto {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.$ext;
        $path = sprintf('customers/%d/photos/%s/%s', $customer->id, $type, $filename);

        Storage::disk(self::DISK)->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        return CustomerPhoto::create([
            'customer_id' => $customer->id,
            'type' => $type,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'caption' => $caption,
            'uploaded_by' => $by->id,
            'sort_order' => ($customer->photos()->max('sort_order') ?? 0) + 1,
        ]);
    }

    public function delete(CustomerPhoto $photo): void
    {
        Storage::disk(self::DISK)->delete($photo->file_path);
        $photo->delete();
    }
}
