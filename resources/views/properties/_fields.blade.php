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

<div class="col-md-4">
    <label for="postcode" class="form-label">Postcode</label>
    <input id="postcode" name="postcode" type="text" maxlength="20"
           class="form-control @error('postcode') is-invalid @enderror"
           value="{{ old('postcode', $property?->postcode) }}" required>
    {{-- Space is RESERVED, not conditional. Showing a hint used to push
         the Register button down a line at the exact moment the user was
         clicking it, so the first click landed where the button had just
         been and a second was needed. --}}
    <div id="postcode-hint" class="form-text" style="min-height:1.5rem"></div>
</div>

<div class="col-md-8">
    <label for="city" class="form-label">City / town</label>
    <input id="city" name="city" type="text" maxlength="100"
           class="form-control @error('city') is-invalid @enderror"
           value="{{ old('city', $property?->city) }}" required>
    {{-- #51: filled from the postcode when empty, and questioned — never
         overruled — when it disagrees. See the script below. --}}
    <div id="city-hint" class="form-text" style="min-height:1.5rem">Enter the postcode first and we will fill this in where we can.</div>
</div>

{{--
    #51 — postcode existence + town reconciliation.

    Three rules this script obeys, and they are the whole design:

    1. It NEVER blocks. Every failure path ends in silence. If the
       lookup is slow, down, throttled or disabled, the form behaves
       exactly as it did before #51 and the server still validates the
       shape. A tenant with a broken boiler is not made to wait on an
       address-tidying service.

    2. It fills the town ONLY when the field is empty. A typed value is
       the tenant's, and they live there.

    3. When the town disagrees it ASKS, and the tenant's answer wins.
       The lookup returns the LOCAL AUTHORITY, not the Royal Mail post
       town — RG1 5RD is Reading as a district and may perfectly well be
       given as Henley by someone who lives there. An equality check
       would reject correct addresses, which is why there isn't one.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var postcode = document.getElementById('postcode');
    var city = document.getElementById('city');
    var cityHint = document.getElementById('city-hint');
    var postcodeHint = document.getElementById('postcode-hint');

    if (!postcode || !city) {
        return;
    }

    var lookupUrl = @json(route('postcode.lookup'));
    var lastLookedUp = null;

    // Neither of these adds or removes d-none any more: display:none
    // collapses the reserved line, which reintroduces exactly the
    // button-moves-under-the-cursor problem the reserved space exists to
    // prevent. The space is always there; only the words change.
    function hide(el) {
        el.textContent = '';
    }

    function show(el, text) {
        el.textContent = text;
    }

    function offerTown(district) {
        hide(cityHint);

        var typed = city.value.trim();

        // Empty field: fill it. Nothing is being overruled.
        if (typed === '') {
            city.value = district;
            show(cityHint, 'Filled in from the postcode. Change it if your address says otherwise.');
            return;
        }

        // Same answer, allowing for casing and spacing. Say nothing.
        if (typed.toLowerCase() === district.toLowerCase()) {
            return;
        }

        // Disagreement: ASK. The lookup gives the local authority, which
        // is often not the post town, so this is a question and not a
        // correction.
        // Space already reserved; just write into it.
        cityHint.textContent = '';

        var question = document.createElement('span');
        question.textContent = 'That postcode is in ' + district + '. Is "' + typed + '" right? ';
        cityHint.appendChild(question);

        var accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'btn btn-link btn-sm p-0 align-baseline';
        accept.textContent = 'Use ' + district;
        accept.addEventListener('click', function () {
            city.value = district;
            show(cityHint, 'Changed to ' + district + '.');
        });
        cityHint.appendChild(accept);
    }

    function check() {
        var value = postcode.value.trim();

        if (value === '' || value === lastLookedUp) {
            return;
        }

        lastLookedUp = value;

        fetch(lookupUrl + '?postcode=' + encodeURIComponent(value), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (!data) {
                    return; // Rule 1: silence.
                }

                if (data.status === 'not_found') {
                    show(postcodeHint, 'We could not find that postcode. Please check it.');
                    hide(cityHint);
                    return;
                }

                hide(postcodeHint);

                if (data.status === 'exists' && data.district) {
                    offerTown(data.district);
                }
            })
            .catch(function () {
                // Rule 1 again: an outage is silent, never alarming.
            });
    }

    postcode.addEventListener('blur', check);
});
</script>
