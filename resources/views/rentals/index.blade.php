@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold">My Rentals</h2>
                    <a href="{{ route('rentals.create') }}"
                       class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Add New Rental
                    </a>
                </div>

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                @if($rentals->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Address</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">City</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Postcode</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Landlord</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($rentals as $rental)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $rental->rental_line1 }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $rental->rental_city }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $rental->rental_post_code }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $rental->landlord_contact_name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $rental->date_created?->format('M j, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500">No rentals found. <a href="{{ route('rentals.create') }}" class="text-blue-500 hover:underline">Add your first rental</a>.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
