<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\UpdateMenuRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Menu::class);

        $menus = Menu::query()
            ->orderBy('order')
            ->get(['id', 'parent_id', 'code', 'label', 'icon', 'route', 'order', 'is_active', 'is_system', 'description']);

        return Inertia::render('Menus/Index', [
            'menus' => $menus,
        ]);
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->validated());

        return back()->with('flash.success', "Menu {$menu->code} diperbarui.");
    }
}
