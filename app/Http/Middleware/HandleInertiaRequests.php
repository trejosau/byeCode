<?php

namespace App\Http\Middleware;

use App\Domain\Demo\DemoPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $request->session()->forget('demo_origin');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'demo' => fn () => ['available' => DB::table('demo_runs')->where('status', 'ready')->exists(),
                'active' => DemoPolicy::activeRunId() !== null, 'account' => (bool) $request->user()?->demo_run_id,
                'scope' => DemoPolicy::scope($request)],
            'success' => fn () => $request->session()->get('success'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
