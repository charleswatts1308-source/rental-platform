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

<div class="col-md-8">
    <label for="city" class="form-label">City / town</label>
    <input id="city" name="city" type="text" maxlength="100"
           class="form-control @error('city') is-invalid @enderror"
           value="{{ old('city', $property?->city) }}" required>
</div>

<div class="col-md-4">
    <label for="postcode" class="form-label">Postcode</label>
    <input id="postcode" name="postcode" type="text" maxlength="20"
           class="form-control @error('postcode') is-invalid @enderror"
           value="{{ old('postcode', $property?->postcode) }}" required>
</div>
