<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseMessage extends Model
{
    /** @use HasFactory<\Database\Factories\CaseMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'case_id',
        'direction',
        'sender_role',
        'stage_at_send',
        'template_key',
        'subject',
        'body_raw',
        'body_sanitised',
        'tenant_statement',
        'from_address_raw',
        'to_address_raw',
        'spf_pass',
        'dkim_pass',
        'mailgun_message_id',
        'quarantine_reason',
        'sent_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'sender_role' => SenderRole::class,
            'stage_at_send' => 'integer',
            'spf_pass' => 'boolean',
            'dkim_pass' => 'boolean',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(RepairCase::class, 'case_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
