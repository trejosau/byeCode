<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\Onboarding;
use App\Domain\Evaluation\BudgetLedger;
use App\Models\Customer;
use App\Models\PredictionRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountController
{
    public function home(Request $request): RedirectResponse
    {
        return redirect(match ($request->user()->role) {
            'admin' => '/admin', 'commerce' => '/workspace', default => $request->session()->has('selection') ? '/request' : '/my/requests'
        });
    }

    public function password(Request $request): Response
    {
        return Inertia::render('journey/initial-password', ['expired' => $request->user()->temporary_password_expires_at?->isPast() ?? false]);
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $user = $request->user();
        abort_unless($user->must_change_password && ! $user->temporary_password_expires_at?->isPast(), 403, 'Solicita recuperación de contraseña.');
        abort_if(Hash::check($data['password'], $user->password), 422, 'Elige una contraseña diferente.');
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false, 'temporary_password_expires_at' => null])->save();
        $request->session()->regenerate();
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect('/dashboard');
    }

    public function admin(): Response
    {
        return Inertia::render('journey/admin', ['team' => User::where('role', 'commerce')->get(['id', 'name', 'email', 'created_at']),
            'summary' => ['customers' => DB::table('customers')->count(), 'requests' => DB::table('purchase_requests')->count(),
                'quotes' => DB::table('quote_versions')->count(), 'open' => DB::table('commercial_cases')->join('data_sets', 'data_sets.id', '=', 'commercial_cases.data_set_id')->where('data_sets.mode', 'live')->where('commercial_cases.status', 'open')->count()],
            'mail' => DB::table('mail_deliveries')->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->get(),
            'runs' => DB::table('profile_runs')->select('id', 'customer_id', 'status', 'error', 'cutoff', 'updated_at')->latest('id')->limit(30)->get(),
            'customers' => Customer::orderBy('company')->limit(200)->get(['id', 'company', 'contact_name']),
            'outcomes' => DB::table('sale_records')->select('kind')->selectRaw('COUNT(*) as total, SUM(amount_cents) as amount_cents')->groupBy('kind')->get(),
            'prediction' => PredictionRun::latest('id')->first(['id', 'status', 'report', 'created_at']),
            'budget' => app(BudgetLedger::class)->summary()]);
    }

    public function team(Request $request, Onboarding $onboarding): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:180', 'email' => 'required|email|max:180']);
        $onboarding->create($data['name'], $data['email'], 'commerce');

        return back()->with('success', 'Cuenta creada. Acceso inicial en la cola de correo.');
    }
}
