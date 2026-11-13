<x-app-layout>
    {{-- Landing first and never lazy: logo, welcome, state and the one
         primary action are in the first response. The board follows and
         keeps its lazy load. --}}
    @livewire('dashboard-landing')
    @livewire('dashboard')
</x-app-layout>
