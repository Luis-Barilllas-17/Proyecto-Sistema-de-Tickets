<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['all', 'unread', 'alerts'])],
        ]);
        $scope = $filters['scope'] ?? 'all';
        $query = $request->user()->notifications()->latest()
            ->when($scope === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($scope === 'alerts', fn ($query) => $query->whereIn('data->severity', ['warning', 'critical']));

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'scope' => $scope,
            'counts' => [
                'all' => $request->user()->notifications()->count(),
                'unread' => $request->user()->unreadNotifications()->count(),
                'alerts' => $request->user()->notifications()->whereIn('data->severity', ['warning', 'critical'])->count(),
            ],
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();
        $url = data_get($item->data, 'url');

        return $url && str_starts_with($url, url('/')) ? redirect()->to($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Todas las notificaciones fueron marcadas como leídas.');
    }
}
