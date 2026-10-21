<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class SidebarNavigation
{
    public function sections(User $user, Request $request): array
    {
        $sections = [];
        foreach (config('navigation') as $section) {
            $section['items'] = $this->visibleItems($section['items'], $user, $request);
            if ($section['items'] !== []) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    private function visibleItems(array $items, User $user, Request $request): array
    {
        $visible = [];
        foreach ($items as $item) {
            if ((isset($item['roles']) && ! $user->hasAnyRole($item['roles']))
                || (isset($item['except_roles']) && $user->hasAnyRole($item['except_roles']))
                || (isset($item['permission']) && ! $user->can($item['permission']))) {
                continue;
            }
            if (isset($item['children'])) {
                $item['children'] = $this->visibleItems($item['children'], $user, $request);
                if ($item['children'] === []) {
                    continue;
                }
                $item['active'] = collect($item['children'])->contains('active', true);
                if (isset($item['active_routes'])) {
                    $item['active'] = $item['active'] || $request->routeIs(...$item['active_routes']);
                }
            } else {
                $item['active'] = $this->isActive($item, $request);
            }
            $visible[] = $item;
        }

        return $visible;
    }

    private function isActive(array $item, Request $request): bool
    {
        if (! isset($item['route']) || ! $request->routeIs($item['route'])) {
            return false;
        }
        // Filter shortcuts share the index route; its unfiltered link must not also light up.
        foreach ($item['inactive_when_query'] ?? [] as $key) {
            if ($request->filled($key)) {
                return false;
            }
        }
        foreach ($item['route_parameters'] ?? [] as $key => $value) {
            $actual = $request->route($key) ?? $request->query($key);
            if ((string) $actual !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
