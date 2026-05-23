<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UploadPhotoRequest;
use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Services\Customer\CustomerPhotoService;
use Illuminate\Http\RedirectResponse;

class CustomerPhotoController extends Controller
{
    public function __construct(private readonly CustomerPhotoService $service) {}

    public function store(UploadPhotoRequest $request, Customer $customer): RedirectResponse
    {
        $this->service->upload(
            $customer,
            $request->file('file'),
            $request->validated('type'),
            $request->user(),
            $request->validated('caption'),
        );

        return back()->with('flash.success', 'Foto outlet diunggah.');
    }

    public function destroy(CustomerPhoto $photo): RedirectResponse
    {
        $this->authorize('delete', $photo);

        $this->service->delete($photo);

        return back()->with('flash.success', 'Foto outlet dihapus.');
    }
}
