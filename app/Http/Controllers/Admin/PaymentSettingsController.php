<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * One-time setup for the shop's bank QR — a static image (the same one your
 * bank/JazzCash/Easypaisa app already shows for the account), stored as a
 * data URI in `settings` rather than on disk. See BankQrPaymentController
 * for where it's actually shown at checkout.
 */
class PaymentSettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('admin/payment/index', [
            'bank' => [
                'qr_image' => Setting::get('bank_qr_image'),
                'account_title' => Setting::get('bank_account_title'),
                'bank_name' => Setting::get('bank_name'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'account_title' => 'required|string|max:120',
            'bank_name' => 'required|string|max:120',
            'qr_image' => 'nullable|image|max:2048',
        ]);

        $hadImage = Setting::get('bank_qr_image') !== null;

        Setting::put('bank_account_title', $data['account_title'], auth()->id());
        Setting::put('bank_name', $data['bank_name'], auth()->id());

        if ($request->hasFile('qr_image')) {
            $file = $request->file('qr_image');
            $dataUri = 'data:'.$file->getMimeType().';base64,'.base64_encode($file->get());
            Setting::put('bank_qr_image', $dataUri, auth()->id());
        }

        return back()->with('success', $hadImage || ! $request->hasFile('qr_image')
            ? 'Payment settings updated.'
            : 'Bank QR uploaded and payment settings saved.');
    }
}
