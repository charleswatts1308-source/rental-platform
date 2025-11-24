<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    use HasFactory;

    protected $primaryKey = 'rental_id';

    protected $fillable = [
    'user_id',
    'date_created',
    'serv_req1_ll_status',
    'serv_req2_ll_pu',
    'rental_line1',
    'rental_line2',
    'rental_city',
    'rental_post_code',
    'agent_contact_name',
    'agent_contact_email',
    'agent_company_name',
    'agent_company_email',
    'agent_line1',
    'agent_line2',
    'agent_city',
    'agent_post_code',
    'landlord_contact_name',
    'landlord_contact_email',
    'landlord_company_name',
    'landlord_company_email',
    'landlord_line1',
    'landlord_line2',
    'landlord_city',
    'landlord_post_code',
    'lease_type',
    'lease_expiry_date',
    'no_of_tenants',
    'rental_type'    ];

    protected $casts = [
        'serv_req1_ll_status' => 'boolean',
        'serv_req2_ll_pu' => 'boolean',
        'date_created' => 'datetime',
        'lease_expiry_date' => 'date',
    ];

    // Relationship to file attachments
    // public function fileAttachments()
    // {
    //     return $this->hasMany(FileAttachment::class, 'rental_id', 'rental_id');
    // }
}
