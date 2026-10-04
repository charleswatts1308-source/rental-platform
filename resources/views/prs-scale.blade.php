@extends('layouts.app')

@section('title', 'Background: The Scale of the Private Rented Sector')

@section('content')

{{-- Built 30 Sep 2026. EVERY figure on this page carries a named source and a
     date, and the one derived number shows its arithmetic. Nothing here is an
     estimate of ours except where it says so. If a figure cannot be sourced it
     does not go on the page — that is the standard this page is held to. --}}

<h1 class="mb-4">Background: The Scale of the Private Rented Sector</h1>


{{-- Jurisdiction stated once, at the top, with the REASON — a reader in
     Glasgow or Cardiff should learn in one line that these figures are not
     about them, rather than working it out from the source notes. The reason
     given is the data one (the surveys are England-only), which is a fact.
     It deliberately does NOT describe where the Renters' Rights Act applies:
     that is extent-versus-application, it could not be settled from the
     primary sources on 30 Sep 2026, and a wrong sentence there would tell
     someone the law protects them when it may not. --}}
<p>The figures on this page are for <strong>England</strong>. The official surveys behind them —
the English Housing Survey and the English Private Landlord Survey — cover England only. Housing
is devolved, so Scotland, Wales and Northern Ireland collect and publish their own. One in five
households rents privately.</p>

<hr class="my-4">

<h2 class="h4 mb-3">Households</h2>

<table class="table table-striped">
    <tbody>
        <tr>
            <td>Households renting privately, <strong>England</strong></td>
            <td><strong>4.7 million</strong> — 19% of all households</td>
        </tr>
        <tr>
            <td>Average household size, private renters (England)</td>
            <td><strong>2.3 people</strong></td>
        </tr>
    </tbody>
</table>
<p class="text-muted"><small>English Housing Survey 2024-25, Ministry of Housing, Communities and
Local Government, published 14 May 2026.</small></p>

<hr class="my-4">

<h2 class="h4 mb-3">Properties</h2>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Property type</th>
            <th>Share of private rented homes</th>
        </tr>
    </thead>
    <tbody>
        {{-- Same 0–100 scale as the Landlords table, NOT scaled to the largest
             value. Scaling to the max would make 34% fill the cell and
             exaggerate every gap; on a page whose whole virtue is being
             checkable, the shading has to mean what it appears to mean. The
             cost is that 3% is only a sliver, which is the truth. --}}
        <tr><td>Terraced house</td><td style="background: linear-gradient(to right, rgba(39,128,227,.20) 34%, transparent 34%);">34%</td></tr>
        <tr><td>Purpose-built flat</td><td style="background: linear-gradient(to right, rgba(39,128,227,.20) 29%, transparent 29%);">29%</td></tr>
        <tr><td>Semi-detached house</td><td style="background: linear-gradient(to right, rgba(39,128,227,.20) 16%, transparent 16%);">16%</td></tr>
        <tr><td>Converted flat</td><td style="background: linear-gradient(to right, rgba(39,128,227,.20) 12%, transparent 12%);">12%</td></tr>
        <tr><td>Detached house</td><td style="background: linear-gradient(to right, rgba(39,128,227,.20) 5%, transparent 5%);">5%</td></tr>
        <tr><td>Bungalow</td><td style="background: linear-gradient(to right, rgba(39,128,227,.20) 3%, transparent 3%);">3%</td></tr>
    </tbody>
</table>
<p class="text-muted"><small>England, 2023-24. English Housing Survey, rented sectors report.</small></p>

<hr class="my-4">

<h2 class="h4 mb-3">Landlords</h2>

{{-- No chart here, deliberately. Two bars were built on 30 Sep 2026 and
     removed the same hour: the table already shows the crossover if the rows
     are read down the two columns (45 to 21, then 17 to 49), and the chart
     repeated the same three facts less precisely. It also produced a labelling
     ambiguity that needed fixing — "of every 100 tenancies: 1 property" reads
     as if tenancies were being sorted by how many properties they are. Two
     representations of one fact is two things to keep right.

     A summarising sentence was tried here too and dropped on the same day:
     "Most landlords own a single property. Most tenancies are not with them."
     The second half needed the reader to look back up to see what "them"
     referred to. The table is read down its two columns and needs no gloss. --}}

<table class="table table-striped">
    <thead>
        <tr>
            <th>Properties owned</th>
            <th>Share of landlords</th>
            <th>Share of tenancies</th>
        </tr>
    </thead>
    <tbody>
        {{-- Proportional shading in the cell itself: the bar IS the cell
             background, sized to the value, so the row reads as both a number
             and a magnitude with nothing to look up. Scaled 0–100 so the two
             columns are directly comparable down the table. --}}
        <tr>
            <td>1 property</td>
            <td style="background: linear-gradient(to right, rgba(39,128,227,.20) 45%, transparent 45%);">45%</td>
            <td style="background: linear-gradient(to right, rgba(39,128,227,.20) 21%, transparent 21%);">21%</td>
        </tr>
        <tr>
            <td>2 to 4 properties</td>
            <td style="background: linear-gradient(to right, rgba(39,128,227,.20) 38%, transparent 38%);">38%</td>
            <td style="background: linear-gradient(to right, rgba(39,128,227,.20) 30%, transparent 30%);">30%</td>
        </tr>
        <tr>
            <td>5 or more properties</td>
            <td style="background: linear-gradient(to right, rgba(39,128,227,.20) 17%, transparent 17%);">17%</td>
            <td style="background: linear-gradient(to right, rgba(39,128,227,.20) 49%, transparent 49%);">49%</td>
        </tr>
    </tbody>
</table>
<p class="text-muted"><small>England. English Private Landlord Survey 2024, published 5 December 2024.
The same survey found 56% of landlords describe the property as a long-term investment towards
their pension.</small></p>

<hr class="my-4">

<h2 class="h4 mb-3">Rents</h2>

<table class="table table-striped">
    <tbody>
        <tr><td>Average rent, London</td><td><strong>£393 a week</strong></td></tr>
        <tr><td>Average rent, rest of England</td><td><strong>£207 a week</strong></td></tr>
        <tr><td>Average rent, England</td><td><strong>£1,416 a month</strong></td></tr>
    </tbody>
</table>
<p class="text-muted"><small>London and rest of England: English Housing Survey 2024-25. England
monthly average: Office for National Statistics (ONS), October 2025.</small></p>

<hr class="my-4">

<h2 class="h4 mb-3">Value</h2>

<p>No official figure is published for the total rent paid. This one is ours, and here is the
arithmetic:</p>

<table class="table">
    <tbody>
        <tr><td>4.7 million households</td><td class="text-end">English Housing Survey 2024-25</td></tr>
        <tr><td>&times; £1,416 a month</td><td class="text-end">ONS, October 2025</td></tr>
        <tr><td>&times; 12 months</td><td class="text-end"></td></tr>
        <tr class="table-active"><td><strong>&asymp; £80 billion a year</strong></td><td class="text-end">our calculation</td></tr>
    </tbody>
</table>

<p class="text-muted"><small>An order of magnitude, not a precise total: it applies an average to
every household. Treat it as "tens of billions", which is the only claim the inputs support.</small></p>

<hr class="my-4">

<h2 class="h4 mb-3">References</h2>

<ul class="small">
    <li><a href="https://www.gov.uk/government/statistics/chapters-for-english-housing-survey-2024-to-2025-headline-findings-on-demographics-and-household-resilience/chapter-1-profile-of-households-and-dwellings">English Housing Survey 2024-25 — Profile of households and dwellings</a> (published 14 May 2026)</li>
    <li><a href="https://www.gov.uk/government/statistics/english-housing-survey-2023-to-2024-rented-sectors/english-housing-survey-2023-24-rented-sectors">English Housing Survey 2023-24 — Rented sectors</a></li>
    <li><a href="https://www.gov.uk/government/statistics/english-private-landlord-survey-2024-main-report/english-private-landlord-survey-2024-main-report">English Private Landlord Survey 2024 — Main report</a> (published 5 December 2024)</li>
    <li><a href="https://www.ons.gov.uk/economy/inflationandpriceindices/bulletins/privaterentandhousepricesuk/november2025">ONS — Private rent and house prices, UK</a> (published 19 November 2025; England figure for October 2025)</li>
</ul>

@endsection
