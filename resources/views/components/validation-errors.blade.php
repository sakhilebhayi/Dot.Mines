@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'p-4 rounded-lg bg-red-500/10 border border-red-500/30']) }}>
        <div class="font-medium text-sm text-red-300">{{ __('Whoops! Something went wrong.') }}</div>

        <ul class="mt-2 list-disc list-inside text-sm text-red-300/90">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
