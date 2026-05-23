<?php

namespace App\Services\Permission;

use App\Models\Menu;
use App\Models\User;

class MenuTreeBuilder
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Build menu tree for the navigation sidebar:
     * - Only menus the user can view.
     * - Parents only included if at least one child is visible (or the parent itself has a route+view).
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(User $user): array
    {
        $allMenus = Menu::query()
            ->active()
            ->orderBy('order')
            ->get();

        $visibleCodes = [];
        foreach ($allMenus as $menu) {
            if ($menu->parent_id !== null && $this->canView($user, $menu->code)) {
                $visibleCodes[$menu->id] = true;
            }
        }

        $tree = [];
        foreach ($allMenus->whereNull('parent_id') as $parent) {
            $children = [];
            foreach ($allMenus->where('parent_id', $parent->id) as $child) {
                if (isset($visibleCodes[$child->id])) {
                    $children[] = $this->serialize($child);
                }
            }
            if ($children === [] && ! $this->canView($user, $parent->code)) {
                continue;
            }
            $node = $this->serialize($parent);
            $node['children'] = $children;
            $tree[] = $node;
        }

        return $tree;
    }

    private function canView(User $user, string $code): bool
    {
        return $this->resolver->resolve($user, 'view', $code);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'code' => $menu->code,
            'label' => $menu->label,
            'icon' => $menu->icon,
            'route' => $menu->route,
            'order' => $menu->order,
        ];
    }
}
