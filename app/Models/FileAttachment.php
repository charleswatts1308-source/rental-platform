<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileAttachment extends Model
{
    use HasFactory;

    protected $table = 'file_attachments';
    public $timestamps = false;

    protected $fillable = [
        'rental_id',
        'file_name',
        'blob_url',
        'content_type',
        'file_size',
        'uploaded_date',
    ];

    protected $casts = [
        'uploaded_date' => 'datetime',
    ];

    /**
     * Get the rental that owns the file attachment.
     */
    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the original file name from file_name field.
     */
    public function getOriginalNameAttribute()
    {
        return $this->file_name;
    }
}
