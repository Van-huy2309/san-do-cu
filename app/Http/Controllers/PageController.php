<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function show(string $page)
    {
        $allowed = ['about', 'how-it-works', 'authenticity', 'pricing', 'help', 'terms', 'privacy'];
        abort_unless(in_array($page, $allowed, true), 404);

        return view('pages.' . str_replace('-', '_', $page));
    }
}
