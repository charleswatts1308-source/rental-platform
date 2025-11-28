<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use App\Models\FileAttachment;

class VerifyFileEncryption extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:verify-encryption';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that files in storage are encrypted';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verifying file encryption status...');
        $this->newLine();

        $files = FileAttachment::all();

        if ($files->count() === 0) {
            $this->warn('No files found in database.');
            return 0;
        }

        $encrypted = 0;
        $unencrypted = 0;
        $missing = 0;

        $headers = ['ID', 'Filename', 'Status', 'Details'];
        $rows = [];

        foreach ($files as $file) {
            if (!Storage::exists($file->blob_url)) {
                $rows[] = [
                    $file->id,
                    $file->file_name,
                    'MISSING',
                    'File not found in storage'
                ];
                $missing++;
                continue;
            }

            $contents = Storage::get($file->blob_url);

            // Try to decrypt - if it works, file is encrypted
            try {
                Crypt::decryptString($contents);
                $rows[] = [
                    $file->id,
                    $file->file_name,
                    'ENCRYPTED',
                    'AES-256 encrypted'
                ];
                $encrypted++;
            } catch (\Exception $e) {
                $rows[] = [
                    $file->id,
                    $file->file_name,
                    'UNENCRYPTED',
                    'Plain text - needs encryption'
                ];
                $unencrypted++;
            }
        }

        $this->table($headers, $rows);
        $this->newLine();

        // Summary
        $this->info('=== SUMMARY ===');
        $this->info("Total files: {$files->count()}");
        $this->line("Encrypted: {$encrypted}");

        if ($unencrypted > 0) {
            $this->warn("Unencrypted: {$unencrypted}");
            $this->warn("Run 'php artisan files:encrypt-existing' to encrypt them");
        } else {
            $this->info("Unencrypted: {$unencrypted}");
        }

        if ($missing > 0) {
            $this->error("Missing: {$missing}");
        } else {
            $this->info("Missing: {$missing}");
        }

        $this->newLine();

        if ($encrypted === $files->count()) {
            $this->info('All files are encrypted and secure!');
        }

        return 0;
    }
}
