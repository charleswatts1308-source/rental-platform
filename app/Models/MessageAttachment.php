<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    /** @use HasFactory<\Database\Factories\MessageAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'case_message_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'direction',
        'scan_status',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'scan_status' => ScanStatus::class,
            'size_bytes' => 'integer',
        ];
    }

    public function caseMessage(): BelongsTo
    {
        return $this->belongsTo(CaseMessage::class);
    }
}
