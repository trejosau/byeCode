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
        $scope = $request->query('origin', $request->session()->get('demo_origin', 'all'));

        return in_array($scope, ['all', 'real', 'demo'], true) ? $scope : 'all';
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
        if (! app()->environment(['local', 'testing']) || ! config('demo.enabled') || ! config('demo.active_key')) {
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
        if (! app()->environment(['local', 'testing']) || ! config('demo.enabled')) {
            throw new \RuntimeException('La carga requiere entorno local/testing y BYECODE_DEMO_ENABLED=true.');
        }
    }
}
