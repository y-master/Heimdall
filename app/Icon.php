<?php

namespace App;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Icon extends Model
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'svg', 'webp', 'ico'];

    protected $fillable = [
        'name',
        'path',
        'extension',
        'size',
    ];

    /**
     * Public URL of the icon file.
     */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    /**
     * Number of items (including trashed ones) using this icon.
     */
    public function usageCount(): int
    {
        return Item::withoutGlobalScopes()
            ->withTrashed()
            ->where('icon', $this->path)
            ->count();
    }

    /**
     * Store an uploaded image as a named library icon.
     *
     * @throws ValidationException
     */
    public static function storeUpload(UploadedFile $file, ?string $name = null): self
    {
        $contents = self::validatedContents($file);
        $extension = strtolower($file->getClientOriginalExtension());

        $name = trim((string) $name) !== '' ? trim((string) $name) : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = str_slug($name);
        if ($slug === '') {
            $slug = 'icon';
        }

        $path = self::uniquePath($slug, $extension);
        Storage::disk('public')->put($path, $contents);

        return self::create([
            'name' => $name,
            'path' => $path,
            'extension' => $extension,
            'size' => strlen($contents),
        ]);
    }

    /**
     * Replace the underlying file of an icon. Items referencing the icon
     * keep working: same extension overwrites the file in place, a new
     * extension rewrites the path and cascades the change to items.
     *
     * @throws ValidationException
     */
    public function replaceWith(UploadedFile $file): void
    {
        $contents = self::validatedContents($file);
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === $this->extension) {
            Storage::disk('public')->put($this->path, $contents);
        } else {
            $oldPath = $this->path;
            $newPath = self::uniquePath(pathinfo($oldPath, PATHINFO_FILENAME), $extension);

            Storage::disk('public')->put($newPath, $contents);

            Item::withoutGlobalScopes()
                ->withTrashed()
                ->where('icon', $oldPath)
                ->update(['icon' => $newPath]);

            Storage::disk('public')->delete($oldPath);

            $this->path = $newPath;
        }

        $this->extension = $extension;
        $this->size = strlen($contents);
        $this->save();
    }

    /**
     * First available icons/<slug>.<ext> style path.
     */
    public static function uniquePath(string $slug, string $extension): string
    {
        $base = 'icons/'.$slug;
        $path = $base.'.'.$extension;
        $i = 1;
        while (Storage::disk('public')->exists($path) || self::where('path', $path)->exists()) {
            $path = $base.'-'.($i++).'.'.$extension;
        }

        return $path;
    }

    /**
     * Validate an uploaded image and return its (sanitized for SVG) contents.
     *
     * @throws ValidationException
     */
    protected static function validatedContents(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => __('app.icons.invalid_image')]);
        }

        $contents = file_get_contents($file->getRealPath());

        if ($extension === 'svg') {
            $sanitizer = new Sanitizer();
            $sanitized = $sanitizer->sanitize($contents);

            if ($sanitized === null || strpos($sanitized, '<script>') !== false) {
                throw ValidationException::withMessages(['file' => __('app.icons.malicious_svg')]);
            }

            $contents = $sanitized;
        }

        if (! isImage($contents, $extension)) {
            throw ValidationException::withMessages(['file' => __('app.icons.invalid_image')]);
        }

        return $contents;
    }
}
