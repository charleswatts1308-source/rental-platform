{{--
    Postcode + town, with the #51 lookup. ONE copy, used by the property
    form and by the landlord's postal address — the create-case dropdown
    (4 Sep) and the six password fields (#64) are both reminders of what
    happens when the same thing is written out twice and only one copy
    gets fixed.

    Expects:
      $cityLabel        label for the town field
      $cityValue        current value
      $postcodeValue    current value
      $required         bool — whether both are mandatory
      $notFoundMessage  what to say when the postcode does not exist.
                        Pass null to say nothing.
      $cityHelp         standing text under the town field

    Three rules the script obeys, and they are the whole design:

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

<div class="col-md-4">
    <label for="postcode" class="form-label">Postcode</label>
    <input id="postcode" name="postcode" type="text" maxlength="20"
           class="form-control @error('postcode') is-invalid @enderror"
           value="{{ old('postcode', $postcodeValue) }}" @required($required)>
</div>

{{-- Forces the town onto its own row BENEATH the postcode rather than
     beside it (asked for 15 Sep). Bootstrap's column break, so the two
     keep their own widths instead of both being stretched full-width. --}}
<div class="w-100"></div>

<div class="col-md-8">
    <label for="city" class="form-label">{{ $cityLabel }}</label>
    <input id="city" name="city" type="text" maxlength="100"
           class="form-control @error('city') is-invalid @enderror"
           value="{{ old('city', $cityValue) }}" @required($required)>
    {{-- ONE message line for the pair, and its space is RESERVED rather
         than conditional.

         Reserved because revealing a hint used to push the submit button
         down a line at the exact moment the user was clicking it, so the
         first click landed where the button had just been and a second
         was needed (#67).

         One line, not two, because the postcode used to carry its own
         reserved line directly above this field — which, once the town
         moved beneath the postcode rather than beside it, showed as a
         band of empty space in the middle of the form. The two messages
         are mutually exclusive anyway: a postcode either exists, in
         which case there may be something to say about the town, or it
         does not, in which case there is nothing to say about the town
         at all. --}}
    <div id="field-hint" class="form-text" style="min-height:1.5rem">{{ $cityHelp }}</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var postcode = document.getElementById('postcode');
    var city = document.getElementById('city');
    var hint = document.getElementById('field-hint');

    if (!postcode || !city || !hint) {
        return;
    }

    var lookupUrl = @json(route('postcode.lookup'));
    var notFoundMessage = @json($notFoundMessage);
    var lastLookedUp = null;

    // Never adds or removes d-none: display:none collapses the reserved
    // line, which reintroduces exactly the button-moves-under-the-cursor
    // problem (#67) the reserved space exists to prevent. The space is
    // always there; only the words change.
    function say(text) {
        hint.textContent = text || '';
    }

    function offerTown(district) {
        var typed = city.value.trim();

        // Empty field: fill it. Nothing is being overruled.
        if (typed === '') {
            city.value = district;
            say('Filled in from the postcode. Change it if the address says otherwise.');
            return;
        }

        // Same answer, allowing for casing and spacing. Say nothing.
        if (typed.toLowerCase() === district.toLowerCase()) {
            say('');
            return;
        }

        // Disagreement: ASK. The lookup gives the local authority, which
        // is often not the post town, so this is a question and not a
        // correction.
        say('');

        var question = document.createElement('span');
        question.textContent = 'That postcode is in ' + district + '. Is "' + typed + '" right? ';
        hint.appendChild(question);

        var accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'btn btn-link btn-sm p-0 align-baseline';
        accept.textContent = 'Use ' + district;
        accept.addEventListener('click', function () {
            city.value = district;
            say('Changed to ' + district + '.');
        });
        hint.appendChild(accept);
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
                    // Silent where a miss is not evidence of a mistake —
                    // a landlord or managing agent may sit at a non-UK
                    // address, and postcodes.io only knows UK ones.
                    say(notFoundMessage);
                    return;
                }

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
