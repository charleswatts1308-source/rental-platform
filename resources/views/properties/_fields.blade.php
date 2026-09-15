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
    'cityHelp' => 'Enter the postcode first and we will fill this in where we can.',
])
