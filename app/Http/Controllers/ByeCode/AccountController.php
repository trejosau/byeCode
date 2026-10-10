<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\Onboarding;
use App\Domain\Demo\DemoPolicy;
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

    public function admin(Request $request): Response
    {
        $cases = DB::table('commercial_cases');
        $customers = Customer::query();
        DemoPolicy::filter($cases, $request, 'commercial_cases.demo_run_id');
        DemoPolicy::filter($customers, $request);
        $caseIds = (clone $cases)->select('id');
        $customerIds = (clone $customers)->select('id');

        return Inertia::render('journey/admin', ['team' => User::where('role', 'commerce')->get(['id', 'name', 'email', 'created_at']),
            'summary' => ['customers' => (clone $customers)->count(), 'requests' => DB::table('purchase_requests')->whereIn('commercial_case_id', $caseIds)->count(),
                'quotes' => DB::table('quote_versions')->whereIn('commercial_case_id', $caseIds)->count(), 'open' => (clone $cases)->join('data_sets', 'data_sets.id', '=', 'commercial_cases.data_set_id')->where('data_sets.mode', 'live')->where('commercial_cases.status', 'open')->count()],
            'mail' => DB::table('mail_deliveries')->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->get(),
            'runs' => DB::table('profile_runs')->whereIn('customer_id', $customerIds)->select('id', 'customer_id', 'status', 'error', 'cutoff', 'updated_at')->latest('id')->limit(30)->get(),
            'customers' => (clone $customers)->orderBy('company')->limit(200)->get(['id', 'company', 'contact_name']),
            'outcomes' => DB::table('sale_records')->whereIn('commercial_case_id', $caseIds)->select('kind')->selectRaw('COUNT(*) as total, SUM(amount_cents) as amount_cents')->groupBy('kind')->get(),
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
