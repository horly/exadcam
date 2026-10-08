<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/exadcam-monitoring-test-'.Str::uuid();
    mkdir($this->directory);
    $this->snapshotFile = $this->directory.'/metrics.json';
    config(['server_monitoring.snapshot' => $this->snapshotFile]);
    $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($this->superadmin);
    $this->snapshot = function (array $overrides = []) {
        file_put_contents($this->snapshotFile, json_encode(array_replace_recursive([
            'schema' => 1, 'captured_at' => (int) (microtime(true) * 1000),
            'cpu' => ['usage' => 4.5, 'cores' => 8],
            'memory' => ['total' => 1000, 'used' => 400, 'percent' => 40],
            'disk' => ['total' => 10000, 'used' => 100, 'percent' => 1],
            'load' => ['one' => 0.09, 'five' => 0.15, 'fifteen' => 0.2],
            'network' => ['rx_rate' => 1000, 'tx_rate' => 2000, 'interfaces' => [['name' => 'eth0', 'rx' => 10000, 'tx' => 20000]]],
            'system' => ['hostname' => 'test-server', 'os' => 'Linux', 'uptime' => 123],
            'history' => [['time' => 1000, 'cpu' => null, 'memory' => 40, 'rx' => null, 'tx' => null, 'load' => 0.09]],
        ], $overrides)));
    };
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('restricts the menu and metrics to active superadmins', function () {
    ($this->snapshot)();
    $page = $this->get('/')->assertOk()->assertSee('data-nav="server-monitoring"', false)->assertSee('id="server-monitoring-module"', false);
    expect(substr_count($page->getContent(), 'src="'.asset('vendor/apexcharts/apexcharts.js')))->toBe(1);
    $this->getJson('/server-monitoring/metrics')->assertOk()->assertJsonPath('cpu.usage', 4.5);
    foreach (['admin', 'user'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->getJson('/server-monitoring/metrics')->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee('data-nav="server-monitoring"', false)->assertDontSee('id="server-monitoring-module"', false);
    }
    $this->superadmin->update(['disabled_at' => now()]);
    $this->actingAs($this->superadmin)->getJson('/server-monitoring/metrics')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('does not expose metrics to guests or allow mutation', function () {
    $this->postJson('/server-monitoring/metrics')->assertStatus(405);
    Auth::logout();
    $this->getJson('/server-monitoring/metrics')->assertUnauthorized();
});

it('returns real measurements unchanged with no cache and ignores arbitrary paths', function () {
    ($this->snapshot)(['secret' => 'not-in-contract', 'system' => ['password' => 'do-not-expose']]);
    $response = $this->getJson('/server-monitoring/metrics?file=../../.env&command=id')->assertOk()
        ->assertJsonPath('available', true)->assertJsonPath('stale', false)->assertJsonPath('load.one', 0.09)
        ->assertJsonPath('network.rx_rate', 1000)->assertJsonPath('system.hostname', 'test-server')
        ->assertJsonPath('system.environment', 'testing')->assertJsonPath('history.0.cpu', null)
        ->assertJsonMissingPath('secret')->assertJsonMissingPath('system.password');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('marks missing malformed oversized and outdated data honestly', function () {
    $this->getJson('/server-monitoring/metrics')->assertOk()->assertJsonPath('available', false);
    foreach (['{invalid', '{}', str_repeat('x', 524289)] as $bad) {
        file_put_contents($this->snapshotFile, $bad);
        $this->getJson('/server-monitoring/metrics')->assertOk()->assertJsonPath('available', false)->assertJsonCount(0, 'history');
    }
    ($this->snapshot)(['captured_at' => (int) ((microtime(true) - 60) * 1000)]);
    $this->getJson('/server-monitoring/metrics')->assertOk()->assertJsonPath('available', true)->assertJsonPath('stale', true)->assertJsonPath('cpu.usage', 4.5);
    ($this->snapshot)(['captured_at' => (int) ((microtime(true) + 60) * 1000)]);
    $this->getJson('/server-monitoring/metrics')->assertOk()->assertJsonPath('stale', true);
});

it('bounds chart history and strips unknown fields', function () {
    $rows = array_map(fn ($i) => ['time' => $i * 5000, 'cpu' => 10, 'debug' => 'not-exposed'], range(1, 300));
    ($this->snapshot)(['history' => $rows]);
    $this->getJson('/server-monitoring/metrics')->assertOk()->assertJsonCount(180, 'history')
        ->assertJsonPath('history.0.time', 605000)->assertJsonPath('history.179.time', 1500000)->assertJsonMissingPath('history.0.debug');
});
