<?php

namespace App\Observers\Memory;

use App\Models\AIRecommendation;
use App\Services\Memory\MemoryLoopRecorder;
use Throwable;

class AiRecommendationLoopObserver
{
    public function __construct(private readonly MemoryLoopRecorder $recorder) {}

    public function created(AIRecommendation $recommendation): void
    {
        try {
            $this->recorder->recommendationRaised($recommendation);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
