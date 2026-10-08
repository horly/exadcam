<?php

use App\Models\User;
use App\Services\ServerLogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->logDirectory = sys_get_temp_dir().'/exadcam-log-test-'.Str::uuid();
    mkdir($this->logDirectory);
    config(['server_logs.directory' => $this->logDirectory, 'server_logs.laravel_directory' => $this->logDirectory, 'app.timezone' => 'UTC']);
    $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($this->superadmin);
    $this->snapshot = function (string $content, array $overrides = []) {
        file_put_contents($this->logDirectory.'/gps.json', json_encode(['available' => true,
            'captured_at' => now()->toIso8601String(), 'content' => $content, 'truncated' => false, ...$overrides]));
    };
});

afterEach(function () {
    File::deleteDirectory($this->logDirectory);
});

it('exposes the read-only menu and logs only to active superadmins', function () {
    ($this->snapshot)('device_authenticated');
    $this->get('/')->assertOk()->assertSee('data-nav="server-logs"', false)->assertSee('id="server-logs-module"', false)
        ->assertDontSee('console-ticket')->assertDontSee('server-console');
    $this->getJson('/server-logs/content')->assertOk()->assertJsonPath('content', 'device_authenticated');
    foreach (['admin', 'user'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->getJson('/server-logs/content')->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee('data-nav="server-logs"', false)->assertDontSee('id="server-logs-module"', false);
    }
    $this->superadmin->update(['disabled_at' => now()]);
    $this->actingAs($this->superadmin)->getJson('/server-logs/content')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('validates source line count and filters without allowing file or command selection', function () {
    foreach ([['source' => '../../.env'], ['source' => 'gps;id'], ['lines' => 100000], ['lines' => -1],
        ['level' => 'shell'], ['search' => str_repeat('a', 101)], ['source' => ['gps']]] as $invalid) {
        $this->getJson('/server-logs/content?'.http_build_query($invalid))->assertUnprocessable();
    }
    $this->postJson('/server-logs/content', ['command' => 'whoami'])->assertStatus(405);
});

it('returns the newest requested lines and filters within the available window', function () {
    ($this->snapshot)(implode("\n", array_map(fn ($i) => ($i % 2 ? 'gps_error' : 'device_authenticated').' device=2 row='.$i, range(1, 400))));
    $response = $this->getJson('/server-logs/content?lines=100')->assertOk()->assertJsonPath('lines', 100)->assertJsonPath('stale', false);
    expect(explode("\n", $response->json('content'))[0])->toBe('gps_error device=2 row=301');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->getJson('/server-logs/content?lines=100&level=errors&search=row%3D39')->assertOk()->assertJsonPath('lines', 6)
        ->assertJsonMissing(['content' => 'device_authenticated']);
    $this->getJson('/server-logs/content?search=absent')->assertOk()->assertJsonPath('lines', 0)->assertJsonPath('content', '');
});

it('reports missing corrupt stale and failed journal snapshots honestly', function () {
    $this->getJson('/server-logs/content')->assertOk()->assertJsonPath('available', false);
    file_put_contents($this->logDirectory.'/gps.json', '{invalid');
    $this->getJson('/server-logs/content')->assertOk()->assertJsonPath('available', false);
    ($this->snapshot)('old event', ['captured_at' => now()->subMinute()->toIso8601String()]);
    $this->getJson('/server-logs/content')->assertOk()->assertJsonPath('available', true)->assertJsonPath('stale', true)->assertJsonPath('content', 'old event');
    ($this->snapshot)('', ['available' => false]);
    $this->getJson('/server-logs/content')->assertOk()->assertJsonPath('available', false);
    file_put_contents($this->logDirectory.'/gps.json', str_repeat('x', 1048577));
    $this->getJson('/server-logs/content')->assertOk()->assertJsonPath('available', false);
});

it('redacts credentials in both journal and Laravel content', function () {
    $content = "Authorization: Bearer sample-access-123\n".'{"auth_token":"sample-device-456","password":"secret words 789","device_id":2}'
        ."\nAPI_KEY=sample-key-123\nhttps://user:sample-pass-123@example.test/path\n-----BEGIN PRIVATE KEY-----\nprivate-material\n-----END PRIVATE KEY-----";
    ($this->snapshot)($content);
    file_put_contents($this->logDirectory.'/laravel.log', $content);
    foreach (['gps', 'laravel'] as $source) {
        $response = $this->getJson('/server-logs/content?source='.$source)->assertOk();
        foreach (['sample-access-123', 'sample-device-456', 'secret words 789', 'sample-key-123', 'sample-pass-123', 'private-material'] as $secret) {
            expect($response->json('content'))->not->toContain($secret);
        }
        expect($response->json('content'))->toContain('[REDACTED]')->toContain('"device_id":2');
    }
});

it('reads the latest Laravel log with a bounded tail and Kinshasa timestamps', function () {
    $this->getJson('/server-logs/content?source=laravel')->assertOk()->assertJsonPath('available', true)->assertJsonPath('lines', 0);
    file_put_contents($this->logDirectory.'/laravel.log', str_repeat('x', 600000)."\n[2026-09-28 10:00:00] production.ERROR: sample failure\n");
    $response = $this->getJson('/server-logs/content?source=laravel')->assertOk()->assertJsonPath('lines', 1)->assertJsonPath('truncated', true);
    expect($response->json('content'))->toContain('2026-09-28T11:00:00+01:00')->toContain('sample failure');
});

it('does not permit snapshots for arbitrary sources through the reader', function () {
    expect(fn () => app(ServerLogReader::class)->read('../laravel', 300, 'all', ''))->toThrow(InvalidArgumentException::class);
});
