{{--
    The photo limits, stated once. #68 was the form enforcing a total it
    never mentioned; putting the sentence in two places is how that comes
    back. Both the create form and the reply form include this.

    Expects:
      $ceiling        how many files are allowed
      $perFileLabel   per-file size, for display
      $totalBytes     0 when there is no total limit
      $totalLabel     the total, for display

    The inline-@if trap is deliberately avoided here: Blade does not
    recognise @endif butted straight onto a word, which silently broke
    every render of the create form on 15 Sep. A ternary has nothing to
    leave unclosed.
--}}
JPG, PNG or PDF.
Up to <strong>{{ $ceiling }}</strong> {{ $ceiling === 1 ? 'file' : 'files' }},
each under <strong>{{ $perFileLabel }}</strong>{!! $totalBytes > 0 ? ', and <strong>'.e($totalLabel).'</strong> for all of them together' : '' !!}.
