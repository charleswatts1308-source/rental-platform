<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use App\Models\FileAttachment;

class EncryptExistingFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:encrypt-existing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing unencrypted files in storage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting encryption of existing files...');

        $files = FileAttachment::all();
        $encrypted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            try {
                if (!Storage::exists($file->blob_url)) {
                    $this->warn("File not found: {$file->blob_url}");
                    $skipped++;
                    continue;
                }

                // Read file contents
                $contents = Storage::get($file->blob_url);

                // Try to decrypt - if it works, file is already encrypted
                try {
                    Crypt::decryptString($contents);
                    $this->info("Already encrypted: {$file->file_name}");
                    $skipped++;
                    continue;
                } catch (\Exception $e) {
                    // File is not encrypted, proceed with encryption
                }

                // Encrypt the file contents
                $encryptedContents = Crypt::encryptString($contents);

                // Overwrite with encrypted version
                Storage::put($file->blob_url, $encryptedContents);

                $this->info("Encrypted: {$file->file_name}");
                $encrypted++;

            } catch (\Exception $e) {
                $this->error("Failed to encrypt {$file->file_name}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Encryption complete!");
        $this->info("Encrypted: {$encrypted}");
        $this->info("Skipped (already encrypted or not found): {$skipped}");
        $this->info("Errors: {$errors}");

        return 0;
    }
}
