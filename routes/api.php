<?php

use App\Http\Controllers\Webhooks\AfricasTalkingWebhookController;
use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/africastalking/dlr', [AfricasTalkingWebhookController::class, 'deliveryReport'])
    ->name('webhooks.africastalking.dlr');

Route::post('/webhooks/africastalking/inbound', [AfricasTalkingWebhookController::class, 'inbound'])
    ->name('webhooks.africastalking.inbound');

// Header search (authenticated). These endpoints are called by the SPA shell
// with the session cookie, so they need the web middleware group (cookies +
// session) for the session-based auth guard to resolve the user. Without it the
// `api` group has no session and every request 401s — silently breaking search.
Route::middleware(['web', 'auth'])->get('/search/customers', function (Request $request) {
    $q = trim($request->get('q', ''));
    if (strlen($q) < 2) {
        return response()->json(['data' => []]);
    }
    $customers = Customer::query()
        ->where(function ($query) use ($q) {
            $query->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('account_number', 'like', "%{$q}%");
        })
        ->limit(8)
        ->get(['id', 'first_name', 'last_name', 'phone', 'email', 'account_number']);

    return response()->json(['data' => $customers]);
});

// In-app notifications (derived from system state)
Route::middleware(['web', 'auth'])->get('/notifications', function () {
    $notifications = collect();

    // Overdue customers notification
    $overdueCount = Customer::where('payment_status', 'overdue')->count();
    if ($overdueCount > 0) {
        $notifications->push([
            'id'    => 'overdue-customers',
            'title' => "{$overdueCount} customer" . ($overdueCount > 1 ? 's' : '') . ' overdue',
            'body'  => 'These customers have overdue payments. Consider sending a reminder.',
            'url'   => url('/customers?paymentStatusFilter=overdue'),
            'time'  => 'Now',
            'read'  => false,
        ]);
    }

    // Sending campaigns notification
    $sendingCount = Campaign::where('status', 'sending')->count();
    if ($sendingCount > 0) {
        $notifications->push([
            'id'    => 'sending-campaigns',
            'title' => "{$sendingCount} campaign" . ($sendingCount > 1 ? 's' : '') . ' in progress',
            'body'  => 'Your campaigns are currently sending to recipients.',
            'url'   => url('/campaigns?activeTab=all'),
            'time'  => 'Now',
            'read'  => false,
        ]);
    }

    return response()->json([
        'notifications' => $notifications->values(),
        'unread_count'  => $notifications->where('read', false)->count(),
    ]);
});
