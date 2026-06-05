<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplianceViolationDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $violation;

    public $teamId;

    public function __construct($violation, $teamId)
    {
        $this->violation = $violation;
        $this->teamId = $teamId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("team.{$this->teamId}.compliance");
    }

    public function broadcastAs(): mixed
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
