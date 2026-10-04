<?php

namespace App\Models;

use App\Enums\ContactSource;
use App\Enums\LeaseAgreementAnswer;
use App\Enums\PropertyType;
use Carbon\CarbonInterface;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected $fillable = [
        'address_line1',
        'address_line2',
        'city',
        'postcode',
        'property_type',
        'has_lease_agreement',
        'registered_by_user_id',
    ];

    /**
     * Both are NOT NULL with no database default, deliberately: a code
     * path that forgets to ask should fail loudly rather than record an
     * answer nobody gave. See the step-3 migration.
     */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'has_lease_agreement' => LeaseAgreementAnswer::class,
        ];
    }

    /**
     * Documents belonging to this property — today, lease agreements.
     *
     * THEY NEVER LEAVE THE PLATFORM: never attached to a case, never
     * sent to a landlord, never carried on a letter. Held so a human
     * can read the lease to identify the landlord's formal service
     * address. See PropertyDocument and the table migration.
     *
     * @return HasMany<PropertyDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(PropertyDocument::class)
            ->orderByRaw('page_number is null')
            ->orderBy('page_number')
            ->orderBy('id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    /**
     * @return HasMany<RepairCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(RepairCase::class);
    }

    /**
     * Every version of this property's landlord contact, newest first.
     * This is the change history behind snag #24.
     *
     * @return HasMany<PropertyLandlordContact, $this>
     */
    public function landlordContacts(): HasMany
    {
        return $this->hasMany(PropertyLandlordContact::class)
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    /**
     * The version in force. THIS is what every forward-looking read
     * resolves — the recipient of the next letter, the token binding, the
     * quarantine sender-match. Never used to describe a letter already
     * sent; case_messages froze that.
     *
     * @return HasOne<PropertyLandlordContact, $this>
     */
    public function currentLandlordContact(): HasOne
    {
        return $this->hasOne(PropertyLandlordContact::class)
            ->whereNull('superseded_at');
    }

    /**
     * Install a new contact version, retiring whatever was current.
     *
     * The supersede and the insert are one transaction because
     * UNIQUE(property_id, is_current) means the old row MUST release the
     * slot before the new one can take it — a half-applied change would
     * leave the property with no contact at all.
     *
     * Time is an injected parameter, per the standing rule: the same path
     * serves real, pretend and test invocations.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function setLandlordContact(
        array $attributes,
        CarbonInterface $now,
        int $byUserId,
        ContactSource $source = ContactSource::Entered,
    ): PropertyLandlordContact {
        return DB::transaction(function () use ($attributes, $now, $byUserId, $source) {
            $this->currentLandlordContact()->first()?->supersede($now, $byUserId);

            return $this->landlordContacts()->create($attributes + [
                'created_by_user_id' => $byUserId,
                'effective_from' => $now,
                'superseded_at' => null,
                'is_current' => 1,
                'source' => $source,
            ]);
        });
    }
}
