<?php

namespace App\Http\Controllers;

use App\Models\FileAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileAttachmentController extends Controller
{
    /**
     * Download a file attachment.
     */
    public function download($id)
    {
        $file = FileAttachment::findOrFail($id);

        // Ensure user owns the rental this file belongs to
        if ($file->rental->user_id != Auth::id()) {
            abort(403);
        }

        // Check if file exists
        if (!Storage::exists($file->blob_url)) {
            abort(404, 'File not found');
        }

        return Storage::download($file->blob_url, $file->file_name);
    }

    /**
     * Delete a file attachment.
     */
    public function destroy($id)
    {
        $file = FileAttachment::findOrFail($id);

        // Ensure user owns the rental this file belongs to
        if ($file->rental->user_id != Auth::id()) {
            abort(403);
        }

        // Delete file from storage
        if (Storage::exists($file->blob_url)) {
            Storage::delete($file->blob_url);
        }

        // Delete database record
        $file->delete();

        return redirect()->back()->with('success', 'File deleted successfully!');
    }
}
