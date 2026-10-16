<?php

namespace App\Observers\Memory;

use App\Models\AiRecommendationAction;
use App\Services\Memory\MemoryLoopRecorder;
use Throwable;

class AiRecommendationActionLoopObserver
{
    public function __construct(private readonly MemoryLoopRecorder $recorder) {}

    public function created(AiRecommendationAction $action): void
    {
        try {
            $this->recorder->recommendationActioned($action);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
