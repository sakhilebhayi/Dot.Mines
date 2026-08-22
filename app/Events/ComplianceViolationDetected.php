<?php

namespace App\Events;

use App\Models\ComplianceViolation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplianceViolationDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ComplianceViolation $violation,
        public int $teamId,
    ) {}

    #[\Override]
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("team.{$this->teamId}.compliance");
    }

    public function broadcastAs(): string
    {
        return 'compliance.violation';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'violation_id' => $this->violation->id,
            'violation_type' => $this->violation->violation_type,
            'severity' => $this->violation->severity,
            'description' => $this->violation->description,
            'remediation_deadline' => $this->violation->remediation_deadline,
            'audit_id' => $this->violation->compliance_audit_id,
            'timestamp' => now()->toIso8601String(),
            'action_required' => true,
        ];
    }
}
