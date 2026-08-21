<?php

namespace Tests\Feature;

use App\Icon;
use App\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IconTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_icons_as_json(): void
    {
        $this->seed();
        Storage::fake('public');
        Storage::disk('public')->put('icons/netflix.png', 'content');

        Icon::create([
            'name' => 'Netflix',
            'path' => 'icons/netflix.png',
            'extension' => 'png',
            'size' => 7,
        ]);

        $response = $this->get('/icons');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Netflix',
            'path' => 'icons/netflix.png',
            'url' => asset('storage/icons/netflix.png'),
        ]);
    }

    public function test_item_upload_with_name_creates_library_icon(): void
    {
        $this->seed();
        Storage::fake('public');

        $response = $this->post('/items', [
            'title' => 'Netflix',
            'url' => 'https://netflix.com',
            'appid' => 'null',
            'tags' => [0],
            'file' => TestingFile::fake()->image('logo.png'),
            'icon_name' => 'Netflix',
        ]);

        $response->assertRedirect('/');

        $icon = Icon::where('name', 'Netflix')->first();
        $this->assertNotNull($icon);
        $this->assertSame('icons/netflix.png', $icon->path);
        Storage::disk('public')->assertExists($icon->path);

        $item = Item::withoutGlobalScopes()->where('title', 'Netflix')->first();
        $this->assertNotNull($item);
        $this->assertSame($icon->path, $item->icon);
    }

    public function test_upload_name_collision_gets_suffix(): void
    {
        $this->seed();
        Storage::fake('public');

        Icon::storeUpload(TestingFile::fake()->image('a.png'), 'Netflix');
        $second = Icon::storeUpload(TestingFile::fake()->image('b.png'), 'Netflix');

        $this->assertSame('icons/netflix-1.png', $second->path);
    }

    public function test_renames_icon_without_touching_the_file(): void
    {
        $this->seed();
        Storage::fake('public');

        $icon = Icon::storeUpload(TestingFile::fake()->image('a.png'), 'Old name');

        $response = $this->post('/icons/'.$icon->id, [
            '_method' => 'PATCH',
            'name' => 'New name',
        ]);

        $response->assertRedirect(route('settings.icons'));
        $this->assertDatabaseHas('icons', ['id' => $icon->id, 'name' => 'New name']);
        $this->assertSame('icons/old-name.png', $icon->fresh()->path);
    }

    public function test_destroy_refused_when_icon_in_use(): void
    {
        $this->seed();
        Storage::fake('public');

        $icon = Icon::storeUpload(TestingFile::fake()->image('x.png'), 'Used');
        Item::factory()->create(['icon' => $icon->path]);

        $response = $this->post('/icons/'.$icon->id, ['_method' => 'DELETE']);

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('icons', ['id' => $icon->id]);
        Storage::disk('public')->assertExists($icon->path);
    }

    public function test_destroy_removes_unused_icon(): void
    {
        $this->seed();
        Storage::fake('public');

        $icon = Icon::storeUpload(TestingFile::fake()->image('x.png'), 'Unused');

        $response = $this->post('/icons/'.$icon->id, ['_method' => 'DELETE']);

        $response->assertRedirect(route('settings.icons'));
        $this->assertDatabaseMissing('icons', ['id' => $icon->id]);
        Storage::disk('public')->assertMissing($icon->path);
    }

    public function test_replace_same_extension_keeps_path_and_items(): void
    {
        $this->seed();
        Storage::fake('public');

        $icon = Icon::storeUpload(TestingFile::fake()->image('x.png'), 'Replaced');
        $oldPath = $icon->path;
        $item = Item::factory()->create(['icon' => $oldPath]);

        $response = $this->post('/icons/'.$icon->id.'/replace', [
            'file' => TestingFile::fake()->image('y.png'),
        ]);

        $response->assertRedirect(route('settings.icons'));

        $icon->refresh();
        $this->assertSame($oldPath, $icon->path);
        $this->assertSame($oldPath, $item->refresh()->icon);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_replace_different_extension_updates_items(): void
    {
        $this->seed();
        Storage::fake('public');

        $icon = Icon::storeUpload(TestingFile::fake()->image('x.png'), 'Converted');
        $oldPath = $icon->path;
        $item = Item::factory()->create(['icon' => $oldPath]);

        $response = $this->post('/icons/'.$icon->id.'/replace', [
            'file' => TestingFile::fake()->image('y.jpg'),
        ]);

        $response->assertRedirect(route('settings.icons'));

        $icon->refresh();
        $this->assertSame('jpg', $icon->extension);
        $this->assertNotSame($oldPath, $icon->path);
        $this->assertSame($icon->path, $item->refresh()->icon);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($icon->path);
    }
}
