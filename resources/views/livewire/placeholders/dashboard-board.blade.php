{{-- Skeleton for the operations board (Dashboard only; the shared dashboard-family skeleton is untouched): hairline rows at the real row height,
     static, no shimmer and no spinner, so nothing jumps when the board arrives. --}}
<div class="dm-stack" role="status" aria-busy="true" aria-label="Loading the fleet board">
    <div class="dm-skel">
        @foreach (range(1, 6) as $row)
            <div class="dm-skel__row"><i></i><i></i><i></i></div>
        @endforeach
    </div>
</div>
