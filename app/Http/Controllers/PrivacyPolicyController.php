<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

class PrivacyPolicyController extends Controller
{
    public function show(Request $request): View
    {
        $policyFile = (string) Jetstream::localizedMarkdownPath('policy.md');

        return view('policy', [
            'policy' => Str::markdown(
                (string) file_get_contents($policyFile),
                ['html_input' => 'strip', 'allow_unsafe_links' => false]
            ),
        ]);
    }
}
