@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    <h1 class="text-2xl font-semibold text-[var(--stone)] mb-6">Generate Report — Select Scope</h1>

    <form method="GET" action="{{ route('reports.generate') }}" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm text-[var(--sand)]">Mine Area</label>
                <select name="mine_area_id" id="mine_area_select" class="w-full mt-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-md p-2 text-[var(--stone)]">
                    <option value="">-- Select mine area (optional) --</option>
                    @foreach($mineAreas ?? [] as $ma)
                        <option value="{{ $ma->id }}">{{ $ma->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm text-[var(--sand)]">Geofence</label>
                <select name="geofence_id" id="geofence_select" class="w-full mt-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-md p-2 text-[var(--stone)]">
                    <option value="">-- Select geofence (optional) --</option>
                    @foreach($geofences ?? [] as $g)
                        <option value="{{ $g->id }}" data-mine-area-id="{{ $g->mine_area_id }}">{{ $g->name }} @if($g->mineArea) ({{ $g->mineArea->name }}) @endif</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm text-[var(--sand)]">Machine</label>
                <input list="machines_list" name="machine_id" id="machine_input" class="w-full mt-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-md p-2 text-[var(--stone)]" placeholder="Type machine name to search" />
                <datalist id="machines_list">
                    @foreach($machines ?? [] as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                </datalist>
                <p class="text-xs text-[var(--sand)] mt-1">You may type or pick a machine. Leave empty to report on area/geofence.</p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <button type="submit" class="bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] px-4 py-2 rounded font-display font-semibold">Generate Report</button>
            <a href="{{ route('reports') }}" class="text-sm text-[var(--sand)] hover:underline">Back to Reports</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
    const mineSelect = document.getElementById('mine_area_select')
    const geofenceSelect = document.getElementById('geofence_select')

    function filterGeofences(){
        const selected = mineSelect.value
        Array.from(geofenceSelect.options).forEach(opt => {
            if(!opt.dataset || !opt.dataset.mineAreaId) return
            if(selected === '' ){
                opt.style.display = ''
            } else {
                opt.style.display = (opt.dataset.mineAreaId === selected) ? '' : 'none'
            }
        })
        // if currently selected option was hidden, reset
        if(geofenceSelect.selectedOptions.length && geofenceSelect.selectedOptions[0].style.display === 'none'){
            geofenceSelect.value = ''
        }
    }

    mineSelect.addEventListener('change', filterGeofences)
    filterGeofences()
})
</script>
@endpush

@endsection
