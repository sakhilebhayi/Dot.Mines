<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Jetstream\Jetstream;

class PrivacyPolicyController extends Controller
{
    public function show(Request $request): View
    {
        $policyFile = Jetstream::localizedMarkdownPath('policy.md');

        return view('policy', [
            'policy' => Str::markdown(
                file_get_contents($policyFile),
                ['html_input' => 'strip', 'allow_unsafe_links' => false]
            ),
        ]);
    }
}
