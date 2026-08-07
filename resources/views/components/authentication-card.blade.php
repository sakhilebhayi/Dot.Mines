<div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-10 sm:pt-0 px-5 overflow-hidden bg-[var(--ink)]">
    {{-- Same hero photo as welcome.blade.php (excavators at a mine, Dominik Vanyi), matching
    the ecosystem-wide convention of the auth card mirroring the welcome page's own hero
    treatment. This platform's welcome hero uses a dark-ink wash throughout, so the scrim here
    stays dark-ink rather than the light-paper vignette used on light-themed sibling platforms. --}}
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1523848309072-c199db53f137?q=80&w=2400&auto=format&fit=crop');"></div>
    <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 65% at 50% 35%, var(--ink) 0%, rgba(33,26,20,0.94) 45%, rgba(33,26,20,0.75) 72%, rgba(33,26,20,0.45) 100%);"></div>

    <div class="relative z-10">
        {{ $logo }}
    </div>

    <div class="relative z-10 w-full sm:max-w-md mt-8 px-6 sm:px-8 py-8 bg-[var(--ink-soft)] border border-[var(--line)] shadow-2xl overflow-hidden sm:rounded-xl">
        {{ $slot }}
    </div>
</div>
