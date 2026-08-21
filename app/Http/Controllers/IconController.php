<?php

namespace App\Http\Controllers;

use App\Icon;
use App\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IconController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('allowed');
    }

    /**
     * List all icons (JSON, used by the icon picker).
     */
    public function index(): JsonResponse
    {
        $icons = Icon::orderBy('name')->get()->map(function (Icon $icon) {
            return [
                'id' => $icon->id,
                'name' => $icon->name,
                'path' => $icon->path,
                'url' => $icon->url(),
            ];
        });

        return response()->json($icons);
    }

    /**
     * Upload a new named icon to the library.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|image',
            'name' => 'nullable|string|max:255',
        ]);

        Icon::storeUpload($request->file('file'), $validated['name'] ?? null);

        return redirect()
            ->route('settings.icons')
            ->with('success', __('app.alert.success.icon_created'));
    }

    /**
     * Library management page.
     */
    public function manage(): \Illuminate\Contracts\View\View
    {
        $data['icons'] = Icon::orderBy('name')->get();
        $data['usage'] = Item::withoutGlobalScopes()
            ->withTrashed()
            ->select('icon')
            ->get()
            ->countBy('icon')
            ->toArray();

        return view('icons.list', $data);
    }

    /**
     * Rename an icon (database only, the file path is untouched).
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $icon = Icon::findOrFail($id);
        $icon->name = $validated['name'];
        $icon->save();

        return redirect()
            ->route('settings.icons')
            ->with('success', __('app.alert.success.icon_updated'));
    }

    /**
     * Delete an unused icon. Refused when items still reference it.
     */
    public function destroy(int $id): RedirectResponse
    {
        $icon = Icon::findOrFail($id);

        $usage = $icon->usageCount();
        if ($usage > 0) {
            return redirect()
                ->route('settings.icons')
                ->withErrors(['icon' => __('app.icons.in_use', ['count' => $usage])]);
        }

        Storage::disk('public')->delete($icon->path);
        $icon->delete();

        return redirect()
            ->route('settings.icons')
            ->with('success', __('app.alert.success.icon_deleted'));
    }

    /**
     * Replace the file behind an icon; linked items follow automatically.
     */
    public function replace(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'file' => 'required|image',
        ]);

        $icon = Icon::findOrFail($id);
        $icon->replaceWith($request->file('file'));

        return redirect()
            ->route('settings.icons')
            ->with('success', __('app.alert.success.icon_replaced'));
    }
}
