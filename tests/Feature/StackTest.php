<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('serves the Inertia entry point without exposing integration secrets', function () {
    config()->set('integrations.supabase.secret_key', 'server-only-supabase-secret');
    config()->set('integrations.openai.api_key', 'server-only-openai-key');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('journey/catalog'))
        ->assertDontSee('server-only-supabase-secret')
        ->assertDontSee('server-only-openai-key');
});

it('runs tests with an isolated database', function () {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(config('queue.default'))->toBe('database');
});
