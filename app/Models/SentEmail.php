<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $mailable_class
 * @property string $to_email
 * @property string $subject
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property string|null $error_message
 * @property string|null $provider_message_id
 * @property Carbon|null $delivered_at
 * @property Carbon|null $bounced_at
 * @property string|null $bounce_reason
 */
class SentEmail extends Model
{
    protected $fillable = [
        'mailable_class',
        'to_email',
        'subject',
        'sent_at',
        'failed_at',
        'error_message',
        'provider_message_id',
        'delivered_at',
        'bounced_at',
        'bounce_reason',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
        ];
    }
}
