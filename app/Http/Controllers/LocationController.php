<?php

namespace App\Http\Controllers;

use App\Services\AreaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);
        $request->session()->put('relic.geo', $data);
        if ($user = $request->user()) {
            $user->forceFill($data)->save();
        }

        return response()->json(['ok' => true]);
    }

    public function storeArea(Request $request)
    {
        $data = $request->validate([
            'area' => ['nullable', 'string', Rule::in(array_merge([''], AreaService::names()))],
            'redirect' => 'nullable|string|max:2000',
        ]);
        AreaService::remember($request, $data['area'] ?? null);

        $target = $data['redirect'] ?? route('home');
        if (! is_string($target) || ! str_starts_with($target, $request->root())) {
            $target = route('home');
        }

        $parts = parse_url($target) ?: [];
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        if (AreaService::isValid($data['area'] ?? null)) {
            $query['city'] = $data['area'];
        } else {
            unset($query['city']);
        }

        $path = ($parts['path'] ?? '/').($query !== [] ? '?'.http_build_query($query) : '');
        $rebuilt = ($parts['scheme'] ?? $request->getScheme()).'://'.($parts['host'] ?? $request->getHost());
        if (! empty($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        return redirect()->to($rebuilt.$path);
    }
}
