<?php

namespace App\Console\Commands;

use App\Icon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportIcons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'icons:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import existing icon files from storage into the icon library';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $disk = Storage::disk('public');
        $files = $disk->files('icons');
        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if (Icon::where('path', $file)->exists()) {
                $skipped++;

                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($extension, Icon::ALLOWED_EXTENSIONS, true)) {
                $skipped++;

                continue;
            }

            Icon::create([
                'name' => basename($file, '.'.$extension),
                'path' => $file,
                'extension' => $extension,
                'size' => $disk->size($file),
            ]);
            $imported++;
        }

        $this->info("Imported {$imported} icon(s), skipped {$skipped}.");
    }
}
