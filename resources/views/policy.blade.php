<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center pt-10 sm:pt-16 px-5 pb-16 bg-[var(--ink)]">
        <div>
            <x-authentication-card-logo />
        </div>

        <div class="w-full sm:max-w-2xl mt-8 p-6 sm:p-10 bg-[var(--ink-soft)] border border-[var(--line)] shadow-2xl overflow-hidden sm:rounded-xl prose prose-invert prose-neutral max-w-none prose-headings:font-display prose-headings:text-[var(--stone)] prose-p:text-[var(--sand)] prose-li:text-[var(--sand)] prose-strong:text-[var(--stone)] prose-a:text-[var(--gold)] hover:prose-a:text-[var(--gold-soft)]">
            {{ $policy }}
        </div>
    </div>
</x-guest-layout>
