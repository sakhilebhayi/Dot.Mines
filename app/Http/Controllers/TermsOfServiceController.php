<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

class TermsOfServiceController extends Controller
{
    public function show(Request $request): View
    {
        $termsFile = (string) Jetstream::localizedMarkdownPath('terms.md');

        return view('terms', [
            'terms' => Str::markdown(
                (string) file_get_contents($termsFile),
                ['html_input' => 'strip', 'allow_unsafe_links' => false]
            ),
        ]);
    }
}
