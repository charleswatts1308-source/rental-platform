<?php

namespace App\Models;

use App\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document belonging to a PROPERTY — today, a lease agreement,
 * possibly several pages.
 *
 * ──────────────────────────────────────────────────────────────────
 * THE INVARIANT. Read this before using the model anywhere.
 *
 *   A PROPERTY DOCUMENT NEVER LEAVES THE PLATFORM.
 *
 *   It is never attached to a case, never attached to a case_message,
 *   never sent to a landlord, never carried on a letter or a mailable,
 *   and never parsed by the application. It is visible to the tenant
 *   who uploaded it and to admin, and to nobody else.
 * ──────────────────────────────────────────────────────────────────
 *
 * Why it is held at all: so a HUMAN can read the lease to identify the
 * landlord's formal SERVICE ADDRESS if that is ever needed. A tenancy
 * agreement normally states it. That is a person consulting a
 * reference — not an input the system consumes.
 *
 * Why that needs saying in a docblock: a lease carries the tenant's
 * name, the rent, and often third parties who never agreed to anything.
 * Attaching one to a letter would look, in isolation, like a small
 * helpful change. It is not. If a future requirement seems to need a
 * document to reach a landlord, that is a ruling for the design doc,
 * not an edit here.
 *
 * Files live on the PRIVATE disk and are served through an authorised
 * controller that checks ownership on every request. No public URL.
 */
class PropertyDocument extends Model
{
    protected $fillable = [
        'property_id',
        'uploaded_by_user_id',
        'kind',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'page_number',
        'scan_status',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'page_number' => 'integer',
            'scan_status' => ScanStatus::class,
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * What to show in a list. "Page 2" when the tenant numbered it,
     * otherwise the name of the file they chose.
     */
    public function displayLabel(): string
    {
        return $this->page_number !== null
            ? 'Page '.$this->page_number
            : $this->original_filename;
    }
}
