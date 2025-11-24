<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RentalController extends Controller
{
    public function index()
    {
        $rentals = Rental::where('user_id', Auth::id())->get();
        return view('rentals.index', compact('rentals'));
    }

    public function create()
    {
        return view('rentals.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'rental_line1' => 'required|string|max:100',
            'rental_city' => 'required|string|max:100',
            'rental_post_code' => 'required|string|max:100',
            'rental_line2' => 'nullable|string|max:100',
            'agent_contact_name' => 'nullable|string|max:100',
            'agent_contact_email' => 'nullable|email|max:100',
            'agent_company_name' => 'nullable|string|max:100',
            'agent_company_email' => 'nullable|email|max:100',
            'agent_line1' => 'nullable|string|max:100',
            'agent_line2' => 'nullable|string|max:100',
            'agent_city' => 'nullable|string|max:100',
            'agent_post_code' => 'nullable|string|max:100',
            'landlord_contact_name' => 'nullable|string|max:100',
            'landlord_contact_email' => 'nullable|email|max:100',
            'landlord_company_name' => 'nullable|string|max:100',
            'landlord_company_email' => 'nullable|email|max:100',
            'landlord_line1' => 'nullable|string|max:100',
            'landlord_line2' => 'nullable|string|max:100',
            'landlord_city' => 'nullable|string|max:100',
            'landlord_post_code' => 'nullable|string|max:100',
            'lease_type' => 'nullable|string|max:100',
            'lease_expiry_date' => 'nullable|date',
            'no_of_tenants' => 'nullable|string|max:100',
            'rental_type' => 'nullable|string|max:100',
        ]);

        $rental = Rental::create([
            'user_id' => Auth::id(),
            'date_created' => now(),

            // Checkboxes - convert to boolean
            'serv_req1_ll_status' => $request->has('serv_req1_ll_status') ? 1 : 0,
            'serv_req2_ll_pu' => $request->has('serv_req2_ll_pu') ? 1 : 0,

            // Rental address
            'rental_line1' => $request->rental_line1,
            'rental_line2' => $request->rental_line2,
            'rental_city' => $request->rental_city,
            'rental_post_code' => $request->rental_post_code,

            // Agent information
            'agent_contact_name' => $request->agent_contact_name,
            'agent_contact_email' => $request->agent_contact_email,
            'agent_company_name' => $request->agent_company_name,
            'agent_company_email' => $request->agent_company_email,
            'agent_line1' => $request->agent_line1,
            'agent_line2' => $request->agent_line2,
            'agent_city' => $request->agent_city,
            'agent_post_code' => $request->agent_post_code,

            // Landlord information
            'landlord_contact_name' => $request->landlord_contact_name,
            'landlord_contact_email' => $request->landlord_contact_email,
            'landlord_company_name' => $request->landlord_company_name,
            'landlord_company_email' => $request->landlord_company_email,
            'landlord_line1' => $request->landlord_line1,
            'landlord_line2' => $request->landlord_line2,
            'landlord_city' => $request->landlord_city,
            'landlord_post_code' => $request->landlord_post_code,

            // Lease information
            'lease_type' => $request->lease_type,
            'lease_expiry_date' => $request->lease_expiry_date,
            'no_of_tenants' => $request->no_of_tenants,
            'rental_type' => $request->rental_type,
        ]);

        return redirect()->route('rentals.show', $rental)->with('success', 'Rental created successfully!');
    }

    public function show(Rental $rental)
    {
        // Ensure user can only view their own rentals
        if ($rental->user_id != Auth::id()) {
            abort(403);
        }

        return view('rentals.show', compact('rental'));
    }

    public function edit(Rental $rental)
    {
        // Ensure user can only edit their own rentals
        if ($rental->user_id != Auth::id()) {
            abort(403);
        }

        return view('rentals.edit', compact('rental'));
    }

    public function update(Request $request, Rental $rental)
    {
        // Ensure user can only update their own rentals
        if ($rental->user_id != Auth::id()) {
            abort(403);
        }

        $request->validate([
            'rental_line1' => 'required|string|max:100',
            'rental_city' => 'required|string|max:100',
            'rental_post_code' => 'required|string|max:100',
            'rental_line2' => 'nullable|string|max:100',
            'agent_contact_name' => 'nullable|string|max:100',
            'agent_contact_email' => 'nullable|email|max:100',
            'agent_company_name' => 'nullable|string|max:100',
            'agent_company_email' => 'nullable|email|max:100',
            'agent_line1' => 'nullable|string|max:100',
            'agent_line2' => 'nullable|string|max:100',
            'agent_city' => 'nullable|string|max:100',
            'agent_post_code' => 'nullable|string|max:100',
            'landlord_contact_name' => 'nullable|string|max:100',
            'landlord_contact_email' => 'nullable|email|max:100',
            'landlord_company_name' => 'nullable|string|max:100',
            'landlord_company_email' => 'nullable|email|max:100',
            'landlord_line1' => 'nullable|string|max:100',
            'landlord_line2' => 'nullable|string|max:100',
            'landlord_city' => 'nullable|string|max:100',
            'landlord_post_code' => 'nullable|string|max:100',
            'lease_type' => 'nullable|string|max:100',
            'lease_expiry_date' => 'nullable|date',
            'no_of_tenants' => 'nullable|string|max:100',
            'rental_type' => 'nullable|string|max:100',
        ]);

        $rental->update([
            // Don't update user_id or date_created on edit

            // Checkboxes - convert to boolean
            'serv_req1_ll_status' => $request->has('serv_req1_ll_status') ? 1 : 0,
            'serv_req2_ll_pu' => $request->has('serv_req2_ll_pu') ? 1 : 0,

            // Rental address
            'rental_line1' => $request->rental_line1,
            'rental_line2' => $request->rental_line2,
            'rental_city' => $request->rental_city,
            'rental_post_code' => $request->rental_post_code,

            // Agent information
            'agent_contact_name' => $request->agent_contact_name,
            'agent_contact_email' => $request->agent_contact_email,
            'agent_company_name' => $request->agent_company_name,
            'agent_company_email' => $request->agent_company_email,
            'agent_line1' => $request->agent_line1,
            'agent_line2' => $request->agent_line2,
            'agent_city' => $request->agent_city,
            'agent_post_code' => $request->agent_post_code,

            // Landlord information
            'landlord_contact_name' => $request->landlord_contact_name,
            'landlord_contact_email' => $request->landlord_contact_email,
            'landlord_company_name' => $request->landlord_company_name,
            'landlord_company_email' => $request->landlord_company_email,
            'landlord_line1' => $request->landlord_line1,
            'landlord_line2' => $request->landlord_line2,
            'landlord_city' => $request->landlord_city,
            'landlord_post_code' => $request->landlord_post_code,

            // Lease information
            'lease_type' => $request->lease_type,
            'lease_expiry_date' => $request->lease_expiry_date,
            'no_of_tenants' => $request->no_of_tenants,
            'rental_type' => $request->rental_type,
        ]);

        return redirect()->route('rentals.show', $rental)->with('success', 'Rental updated successfully!');
    }

    public function destroy(Rental $rental)
    {
        // Ensure user can only delete their own rentals
        if ($rental->user_id != Auth::id()) {
            abort(403);
        }

        $rental->delete();

        return redirect()->route('rentals.index')->with('success', 'Rental deleted successfully!');
    }
}
