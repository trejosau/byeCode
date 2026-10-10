<?php

namespace App\Domain\Demo;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DemoPolicy
{
    public static function scope(Request $request): string
    {
        $request->session()->forget('demo_origin');

        return 'all';
    }

    /** @template T of \Illuminate\Database\Eloquent\Model
     * @param  Builder<T>|\Illuminate\Database\Query\Builder  $query
     */
    public static function filter($query, Request $request, string $column = 'demo_run_id'): void
    {
        match (self::scope($request)) {
            'demo' => $query->whereNotNull($column),
            'real' => $query->whereNull($column),
            default => null,
        };
    }

    public static function activeRunId(): ?string
    {
        if (! app()->environment('testing') || ! config('demo.enabled') || ! config('demo.active_key')) {
            return null;
        }

        return DB::table('demo_runs')->where('key', config('demo.active_key'))->where('status', 'ready')->value('id');
    }

    public static function recipientRun(string $email): ?string
    {
        return User::where('email', mb_strtolower(trim($email)))->value('demo_run_id');
    }

    public static function requireLoadingEnabled(): void
    {
        if (! app()->environment('testing') || ! config('demo.enabled')) {
            throw new \RuntimeException('El historial sintético solo se permite en pruebas automatizadas. Los clientes se registran en la aplicación.');
        }
    }
}
