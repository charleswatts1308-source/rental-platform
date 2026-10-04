<div class="col-md-12">
    <label for="address_line1" class="form-label">Address line 1</label>
    <input id="address_line1" name="address_line1" type="text" maxlength="255"
           class="form-control @error('address_line1') is-invalid @enderror"
           value="{{ old('address_line1', $property?->address_line1) }}" required>
</div>

<div class="col-md-12">
    <label for="address_line2" class="form-label">Address line 2 (optional)</label>
    <input id="address_line2" name="address_line2" type="text" maxlength="255"
           class="form-control @error('address_line2') is-invalid @enderror"
           value="{{ old('address_line2', $property?->address_line2) }}">
</div>

@include('partials.postcode-city', [
    'cityLabel' => 'City / town',
    'cityValue' => $property?->city,
    'postcodeValue' => $property?->postcode,
    'required' => true,
    // The property is where the TENANT lives, so a postcode that does not
    // exist is a typo and worth saying so.
    'notFoundMessage' => 'We could not find that postcode. Please check it.',
    'cityHelp' => 'Type the postcode above, then click in this box and we will fill it in for you.',
])


{{-- Information only, for statistics - this drives no letter and no
     obligation (ruled 4 Oct 2026). The ORDER is the English PRS
     distribution published on the Background page, commonest first, so
     most tenants find themselves in the first two or three options. --}}
<div class="col-md-6">
    <label for="property_type" class="form-label">Type of property</label>
    <select id="property_type" name="property_type"
            class="form-select @error('property_type') is-invalid @enderror" required>
        <option value="">&mdash; please choose &mdash;</option>
        @foreach(\App\Enums\PropertyType::selectable() as $type)
            <option value="{{ $type->value }}"
                @selected(old('property_type', $property?->property_type?->value) === $type->value)>
                {{ $type->label() }}
            </option>
        @endforeach
    </select>
    @error('property_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- The parenthesis is the question. It asks whether an agreement
     EXISTS, not whether the tenant can lay hands on it - those are
     different facts and both matter. "I don't know" is offered because
     a lodger or an informal arrangement genuinely may not, and a forced
     yes/no would record that guess as a fact. --}}
<div class="col-md-6">
    <label for="has_lease_agreement" class="form-label">
        Do you have a lease agreement, even if you can't find it?
    </label>
    <select id="has_lease_agreement" name="has_lease_agreement"
            class="form-select @error('has_lease_agreement') is-invalid @enderror" required>
        <option value="">&mdash; please choose &mdash;</option>
        @foreach(\App\Enums\LeaseAgreementAnswer::selectable() as $answer)
            <option value="{{ $answer->value }}"
                @selected(old('has_lease_agreement', $property?->has_lease_agreement?->value) === $answer->value)>
                {{ $answer->label() }}
            </option>
        @endforeach
    </select>
    @error('has_lease_agreement')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>


{{-- OPTIONAL, and it has to READ optional. Ruled 4 Oct 2026: optional in
     validation is not enough - this form is the one thing between a
     tenant and raising a case, and anything that looks like required
     paperwork here is how somebody gives up before they start. So: muted,
     no asterisk, no red, and the label says "if you have it to hand".

     It appears on BOTH create and edit because a tenant registering a
     property usually has the lease open already - that is where the
     landlord's email address came from.

     The reassurance is not decoration. The document is private, and a
     tenant in dispute needs to know it will not be forwarded. --}}
<div class="col-md-12">
    <label for="lease_documents" class="form-label text-muted">
        Upload lease agreement &mdash; optional
    </label>
    <input id="lease_documents" name="lease_documents[]" type="file" multiple
           accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf"
           class="form-control form-control-sm @error('lease_documents.*') is-invalid @enderror">
    <div class="form-text">
        If you have it to hand, add a photograph or PDF of each page.
        It stays on your record and is <strong>never</strong> sent to your landlord.
        You can add pages later, or not at all.
    </div>
    @error('lease_documents.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
