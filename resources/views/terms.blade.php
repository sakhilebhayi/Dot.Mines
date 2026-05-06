<x-guest-layout>
    <div class="min-h-screen bg-slate-900">
        <div class="min-h-screen flex flex-col items-center px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <x-authentication-card-logo />
            </div>

            <div class="w-full max-w-4xl rounded-2xl border border-slate-700 bg-slate-800/95 p-6 shadow-xl backdrop-blur sm:p-8">
                <div class="prose prose-invert max-w-none prose-headings:text-slate-100 prose-p:text-slate-300 prose-li:text-slate-300 prose-strong:text-white prose-a:text-blue-300 hover:prose-a:text-blue-200">
                {!! $terms !!}
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
