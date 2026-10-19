<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            // Drives the sidebar logo (AppLogo) — reads the same Admin →
            // Settings → Shop Profile value already used on invoices, not
            // the static .env app name, so renaming the shop there updates
            // the branding everywhere without a code change.
            'shop_name' => Setting::get('shop_name', config('app.name', 'Zar & Noor')),
            // Same idea for the uploaded shop logo (stored as a data URI —
            // see SystemSettingsController) — was already reaching POS/invoice
            // prints but never the sidebar badge itself. Null when no logo
            // has been uploaded, so AppLogo falls back to the default icon.
            'shop_logo' => Setting::get('shop_logo'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                // Single source of truth is User::canConfirmBankQr() (also
                // what the 'bank-qr-confirm' route middleware checks) — the
                // frontend never re-derives the admin-or-permitted-manager
                // logic itself, just reads the answer.
                'can_confirm_bank_qr' => $request->user()?->canConfirmBankQr() ?? false,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Item Entry's "Save & Print" stays on the same page instead of
                // navigating away — this hands back the just-saved item (with its
                // real, server-assigned item_code) so the page can print it
                // in place. See items/create.tsx.
                'createdItem' => fn () => $request->session()->get('createdItem'),
            ],
            // On/off switches from Admin → Settings — lets the sidebar and POS
            // hide a disabled module's UI. Real enforcement is server-side
            // (EnsureFeatureEnabled / POSSaleService), this is only for display.
            'features' => [
                'buyback_enabled' => Setting::bool('buyback_enabled', true),
                'bank_qr_enabled' => Setting::bool('bank_qr_enabled', true),
                'exchange_enabled' => Setting::bool('exchange_enabled', true),
                'zakat_enabled' => Setting::bool('zakat_enabled', true),
            ],
        ]);
    }
}
