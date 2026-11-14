{{-- Skeleton for the operations board: hairline rows at the real row height,
     static, no shimmer and no spinner, so nothing jumps when the board arrives. --}}
<div class="dm-stack" aria-busy="true" aria-label="Loading the fleet board">
    <div class="dm-skel">
        @foreach (range(1, 6) as $row)
            <div class="dm-skel__row"><i></i><i></i><i></i></div>
        @endforeach
    </div>
</div>
