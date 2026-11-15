<x-app-layout>
    <div class="mines-search">
        <h1 class="mines-search__title">Search</h1>
        <p class="mines-search__lede">
            @if ($typed)
                Results for &ldquo;{{ $q }}&rdquo; in your team's machines, alerts, maintenance, crew and zones, as far as your role lets you see them.
            @else
                Search machines, alerts, maintenance, crew and zones. Type at least {{ \App\Support\Search\MinesSearch::MIN_LENGTH }} characters in the search above.
            @endif
        </p>

        @if (! $typed)
            <section class="mines-search__group" aria-labelledby="sr-goto">
                <h2 class="mines-search__head" id="sr-goto">Go to</h2>
                <div class="mines-search__chips">
                    @foreach ($pages as $page)
                        <a class="mines-search__chip" href="{{ $page->url }}">{{ $page->title }}</a>
                    @endforeach
                </div>
            </section>
        @elseif ($groups === [])
            <p class="mines-search__none" role="status">Nothing matches &ldquo;{{ $q }}&rdquo;. Try fewer words, a machine name, a serial number or an employee number.</p>
        @else
            @foreach ($groups as $label => $hits)
                <section class="mines-search__group" aria-labelledby="sr-{{ \Illuminate\Support\Str::slug($label) }}">
                    <h2 class="mines-search__head" id="sr-{{ \Illuminate\Support\Str::slug($label) }}">{{ $label }} <span class="mines-search__count">{{ count($hits) }}{{ count($hits) >= 25 ? '+' : '' }}</span></h2>
                    <ul class="mines-search__list">
                        @foreach ($hits as $hit)
                            <li>
                                <a class="mines-search__row" href="{{ $hit->url }}">
                                    @if ($hit->ref)<span class="mines-search__ref">{{ $hit->ref }}</span>@endif
                                    <span class="mines-search__rowtitle">{{ \App\Support\Search\Highlight::mark($hit->title, $words) }}</span>
                                    @if ($hit->sub)<span class="mines-search__sub">{{ $hit->sub }}</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>
</x-app-layout>
