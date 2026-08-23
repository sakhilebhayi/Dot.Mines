{{-- Generated API reference. Every endpoint below is read from the route
     table at render time (App\Services\OpenApiGenerator), so this page cannot
     fall behind the code. Rendered server-side: no CDN, no viewer bundle. --}}
<div class="prose prose-invert max-w-none">
    <h1>API Reference</h1>
    <p class="lead">Every endpoint this installation exposes, generated from the application itself.</p>

    <div class="not-prose card bg-base-200 mb-6">
        <div class="card-body">
            <h3 class="card-title text-base">Machine-readable spec</h3>
            <p class="text-sm opacity-80">Import this OpenAPI 3.0 document into Postman, Insomnia, or a client generator.</p>
            <div class="mockup-code mt-2">
                <pre data-prefix=""><code>{{ url('/api/openapi.json') }}</code></pre>
            </div>
            <div class="card-actions justify-end">
                <a href="{{ url('/api/openapi.json') }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">Open the spec</a>
            </div>
        </div>
    </div>

    <p>
        Send your token as <code>Authorization: Bearer &lt;token&gt;</code>. The <strong>Permission</strong> column
        is the token permission each call requires &mdash; it is read from the same middleware that enforces it, so
        it is always accurate. Requests are scoped to your current team; you never pass a team id.
    </p>

    @foreach($this->apiReference as $tag => $group)
        <h2 class="capitalize">{{ str_replace('-', ' ', $tag) }}</h2>
        @if($group['description'])
            <p>{{ $group['description'] }}</p>
        @endif

        <div class="not-prose overflow-x-auto mb-8">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>What it does</th>
                        <th>Permission</th>
                        <th>Parameters</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['operations'] as $op)
                        <tr class="align-top">
                            <td class="whitespace-nowrap">
                                <span @class([
                                    'badge badge-sm font-mono',
                                    'badge-info' => $op['method'] === 'GET',
                                    'badge-success' => $op['method'] === 'POST',
                                    'badge-warning' => in_array($op['method'], ['PUT', 'PATCH'], true),
                                    'badge-error' => $op['method'] === 'DELETE',
                                ])>{{ $op['method'] }}</span>
                                <code class="text-xs">{{ $op['path'] }}</code>
                            </td>
                            <td class="text-sm">{{ $op['summary'] }}</td>
                            <td class="whitespace-nowrap">
                                @if($op['permission'])
                                    <code class="text-xs">{{ $op['permission'] }}</code>
                                @else
                                    <span class="opacity-50 text-xs">&mdash;</span>
                                @endif
                            </td>
                            <td class="text-xs">
                                @if(count($op['path_params']))
                                    <div><span class="opacity-60">path:</span> {{ implode(', ', $op['path_params']) }}</div>
                                @endif
                                @if(count($op['query_params']))
                                    <div><span class="opacity-60">query:</span> {{ implode(', ', $op['query_params']) }}</div>
                                @endif
                                @if(count($op['body_params']))
                                    <div><span class="opacity-60">body:</span> {{ implode(', ', $op['body_params']) }}</div>
                                @endif
                                @if(! count($op['path_params']) && ! count($op['query_params']) && ! count($op['body_params']))
                                    <span class="opacity-50">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
