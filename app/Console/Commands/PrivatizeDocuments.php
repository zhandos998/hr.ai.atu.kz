<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PrivatizeDocuments extends Command
{
    protected $signature = 'documents:privatize';

    protected $description = 'Move legacy candidate and personnel documents into private storage';

    public function handle(): int
    {
        $source = Storage::disk('public');
        $destination = Storage::disk('documents');
        $count = 0;

        foreach (['applications', 'resumes', 'personnel-requests'] as $directory) {
            foreach ($source->allFiles($directory) as $path) {
                if (! $destination->exists($path)) {
                    $stream = $source->readStream($path);
                    try {
                        $destination->put($path, $stream);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }

                if (hash_file('sha256', $source->path($path)) !== hash_file('sha256', $destination->path($path))) {
                    throw new RuntimeException("Document copy differs: {$path}. Source preserved.");
                }

                if (! $source->delete($path)) {
                    throw new RuntimeException("Could not remove public document: {$path}. Private copy verified.");
                }
                $count++;
            }
        }

        $this->info("Moved {$count} documents. Existing database paths are preserved.");

        return self::SUCCESS;
    }
}
