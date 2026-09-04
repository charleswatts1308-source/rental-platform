<?php

namespace App\Models;

use App\Enums\ContactSource;
use App\Enums\LandlordContactRole;
use Carbon\CarbonInterface;
use Database\Factories\PropertyLandlordContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model A — one landlord contact per property at a time, versioned.
 *
 * `superseded_at` carries the meaning ("this version stopped being the
 * one in force at time T"); `is_current` carries the ENFORCEMENT, via
 * UNIQUE(property_id, is_current). The two must move together, which is
 * why nothing outside supersede() should ever write either of them.
 *
 * Routing always resolves the CURRENT row via the property. This row is
 * never the source of truth for a letter that has already gone out —
 * case_messages.to_address_raw froze that at send time.
 */
class PropertyLandlordContact extends Model
{
    /** @use HasFactory<PropertyLandlordContactFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id',
        'email',
        'name',
        'role',
        'organisation_name',
        'address_line1',
        'address_line2',
        'city',
        'postcode',
        'created_by_user_id',
        'effective_from',
        'superseded_at',
        'superseded_by_user_id',
        'is_current',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'role' => LandlordContactRole::class,
            'source' => ContactSource::class,
            'effective_from' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by_user_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }

    /**
     * Retire this version. Sets both the semantic column and the
     * enforcement column in one write, so the unique key immediately
     * frees the (property_id, is_current) slot for the replacement.
     *
     * Time is passed in, never taken from the clock here — the same code
     * path serves real, pretend and test invocations.
     */
    public function supersede(CarbonInterface $at, ?int $byUserId = null): void
    {
        $this->update([
            'superseded_at' => $at,
            'superseded_by_user_id' => $byUserId,
            'is_current' => null,
        ]);
    }

    /**
     * The landlord's own postal address as display lines. Store-and-
     * display only — this never feeds a letter template.
     *
     * @return array<int, string>
     */
    public function postalAddressLines(): array
    {
        return array_values(array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->postcode,
        ], fn (?string $part) => filled($part)));
    }

    public function hasPostalAddress(): bool
    {
        return $this->postalAddressLines() !== [];
    }
}
