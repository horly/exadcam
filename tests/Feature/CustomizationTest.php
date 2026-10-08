<?php

use App\Models\ApplicationSetting;
use App\Models\Fleet;
use App\Models\User;
use App\Services\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->fleet = Fleet::create(['name' => 'Fleet A', 'code' => 'A']);
    $this->otherFleet = Fleet::create(['name' => 'Fleet B', 'code' => 'B']);
    $this->admin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->fleet->id]);
    $this->otherAdmin = User::factory()->create(['role' => 'admin', 'fleet_id' => $this->otherFleet->id]);
    $this->member = User::factory()->create(['role' => 'user', 'fleet_id' => $this->fleet->id]);
    $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->globalData = array_diff_key(BrandingService::DEFAULTS, array_flip(['logo_path', 'internal_logo_path', 'favicon_path']));
});

it('renders global controls only for the superadmin and fleet identity controls for its admin', function () {
    $this->actingAs($this->superadmin)->get('/')->assertOk()->assertSee('data-nav="customization"', false)
        ->assertSee('id="custom-app_name"', false)->assertSee('id="custom-primary_color"', false)->assertSee('id="custom-favicon"', false);
    $this->actingAs($this->admin)->get('/')->assertOk()->assertSee('data-nav="customization"', false)->assertSee('id="custom-logo"', false)->assertSee('id="custom-fleet_name"', false)->assertSee('id="customization-restore"', false)
        ->assertDontSee('id="custom-app_name"', false)->assertDontSee('id="custom-primary_color"', false)->assertDontSee('id="custom-favicon"', false)
        ->assertSee('EXADCAM')->assertSee('VIDÉO &amp; GPS', false)->assertDontSee('Fleet B');
    $this->actingAs($this->member)->get('/')->assertOk()->assertDontSee('data-nav="customization"', false)->assertDontSee('id="customization-form"', false);
});

it('saves global identity colors map and support without changing fleet branding', function () {
    $values = [...$this->globalData, 'app_name' => 'Example platform', 'short_name' => 'Example', 'primary_color' => '#735599',
        'button_color' => '#ffffff', 'support_email' => 'help@example.test', 'support_phone' => '+243 123 456',
        'website_url' => 'https://example.test', 'map_type' => 'hybrid'];
    $this->actingAs($this->superadmin)->postJson('/customization/global', $values)->assertOk();
    expect(ApplicationSetting::find(1)->values)->toMatchArray($values);
    expect($this->fleet->fresh()->logo_path)->toBeNull();
    $this->get('/')->assertOk()->assertSee('Example platform')->assertSee('--brand-primary: #735599;', false)
        ->assertSee('--brand-button-text: #102033;', false)->assertSee('help@example.test')->assertSee('https://example.test')
        ->assertSee('"mapType":"hybrid"', false);
    Auth::logout();
    $this->get('/login')->assertOk()->assertSee('Example platform')->assertSee('help@example.test')->assertSee('--brand-primary: #735599;', false);
});

it('isolates a fleet logo from other fleets and applies it to its regular members', function () {
    $this->actingAs($this->admin)->post('/customization/fleet', ['logo' => UploadedFile::fake()->image('fleet.png', 240, 100)], ['Accept' => 'application/json'])->assertOk();
    $path = $this->fleet->fresh()->logo_path;
    Storage::disk('local')->assertExists($path);
    expect($path)->toStartWith('branding/fleets/'.$this->fleet->id.'/');
    $url = app(BrandingService::class)->context($this->admin->fresh())['sidebar_logo'];
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('/')->assertOk()->assertSee($url, false)->assertSee('<span class="sidebar-product-name">EXADCAM<small>', false);
    $this->actingAs($this->member)->get('/')->assertOk()->assertSee($url, false)->assertDontSee('data-nav="customization"', false);
    $this->get($url)->assertOk();
    $this->actingAs($this->otherAdmin)->get($url)->assertNotFound();
    $this->get('/')->assertOk()->assertDontSee($url, false);
    expect($this->otherFleet->fresh()->logo_path)->toBeNull();
    expect(ApplicationSetting::find(1)->values)->toBe([]);
    Auth::logout();
    $this->get($url)->assertRedirect(route('login'));
});

it('rejects cross-fleet ids global settings normal users and inactive fleets', function () {
    $this->actingAs($this->admin)->postJson('/customization/global', $this->globalData)->assertForbidden();
    foreach ([['fleet_id' => $this->otherFleet->id], ['primary_color' => '#123456'], ['logo_path' => 'arbitrary'], ['app_name' => 'Bad']] as $extra) {
        $this->postJson('/customization/fleet', ['remove_logo' => true, ...$extra])->assertUnprocessable();
    }
    $this->actingAs($this->member)->postJson('/customization/fleet', ['remove_logo' => true])->assertForbidden();
    $this->actingAs($this->superadmin)->postJson('/customization/fleet', ['remove_logo' => true])->assertForbidden();
    $this->fleet->update(['status' => 'inactive']);
    $this->actingAs($this->admin->fresh())->postJson('/customization/fleet', ['remove_logo' => true])->assertForbidden();
    $this->get('/')->assertOk()->assertDontSee('data-nav="customization"', false);
});

it('replaces and removes fleet logos without deleting global assets', function () {
    $this->actingAs($this->superadmin)->post('/customization/global', [...$this->globalData, 'internal_logo' => UploadedFile::fake()->image('global.png', 240, 100)], ['Accept' => 'application/json'])->assertOk();
    $globalPath = ApplicationSetting::find(1)->values['internal_logo_path'];
    $this->actingAs($this->admin)->post('/customization/fleet', ['logo' => UploadedFile::fake()->image('first.jpg', 240, 100)], ['Accept' => 'application/json'])->assertOk();
    $first = $this->fleet->fresh()->logo_path;
    $this->post('/customization/fleet', ['logo' => UploadedFile::fake()->image('second.png', 180, 80)], ['Accept' => 'application/json'])->assertOk();
    $second = $this->fleet->fresh()->logo_path;
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);
    $this->postJson('/customization/fleet', ['remove_logo' => true])->assertOk();
    Storage::disk('local')->assertMissing($second);
    Storage::disk('local')->assertExists($globalPath);
    $context = app(BrandingService::class)->context($this->admin->fresh());
    expect($context['fleet_logo'])->toBeNull();
    expect($context['sidebar_logo'])->toBe($context['internal_logo']);
});

it('normalizes public logos and favicon and restores their defaults', function () {
    $this->actingAs($this->superadmin)->post('/customization/global', [...$this->globalData,
        'logo' => UploadedFile::fake()->image('logo.jpg', 240, 100), 'favicon' => UploadedFile::fake()->image('icon.png', 64, 64)], ['Accept' => 'application/json'])->assertOk();
    $values = ApplicationSetting::find(1)->values;
    $context = app(BrandingService::class)->context();
    expect(Storage::disk('local')->get($values['logo_path']))->toStartWith("\x89PNG\r\n\x1a\n");
    Auth::logout();
    $this->get($context['logo'])->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get('/login')->assertOk()->assertSee($context['favicon'], false)->assertSee($context['logo'], false);
    $this->actingAs($this->superadmin)->postJson('/customization/global', [...$this->globalData, 'remove_logo' => true, 'remove_favicon' => true])->assertOk();
    Storage::disk('local')->assertMissing($values['logo_path']);
    Storage::disk('local')->assertMissing($values['favicon_path']);
    $this->get($context['logo'])->assertNotFound();
    $this->get($context['favicon'])->assertNotFound();
});

it('rejects scripts invalid colors oversized images and unsupported map values', function () {
    $this->actingAs($this->superadmin);
    foreach ([['primary_color' => 'red;display:none'], ['website_url' => 'javascript:alert(1)'], ['map_type' => 'arbitrary'], ['short_name' => str_repeat('x', 25)]] as $bad) {
        $this->postJson('/customization/global', [...$this->globalData, ...$bad])->assertUnprocessable();
    }
    foreach ([UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        UploadedFile::fake()->createWithContent('fake.png', '<?php echo "bad";'),
        UploadedFile::fake()->image('large.png', 2500, 100),
        UploadedFile::fake()->image('too-heavy.png', 240, 100)->size(2049)] as $bad) {
        $this->post('/customization/global', [...$this->globalData, 'logo' => $bad], ['Accept' => 'application/json'])->assertUnprocessable();
    }
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('revokes logo access on reassignment and inactive accounts', function () {
    $this->actingAs($this->admin)->post('/customization/fleet', ['logo' => UploadedFile::fake()->image('logo.png', 100, 50)], ['Accept' => 'application/json'])->assertOk();
    $url = app(BrandingService::class)->context($this->admin->fresh())['sidebar_logo'];
    $this->member->update(['fleet_id' => $this->otherFleet->id]);
    $this->actingAs($this->member->fresh())->get($url)->assertNotFound();
    $this->admin->update(['disabled_at' => now()]);
    $this->actingAs($this->admin->fresh())->postJson('/customization/fleet', ['remove_logo' => true])->assertRedirect(route('login'));
});

it('renames only the assigned fleet while preserving its logo and all other fields', function () {
    $this->actingAs($this->admin)->post('/customization/fleet', ['logo' => UploadedFile::fake()->image('fleet.png', 240, 100)], ['Accept' => 'application/json'])->assertOk();
    $before = $this->fleet->fresh()->getAttributes();
    $otherBefore = $this->otherFleet->fresh()->getAttributes();
    $this->postJson('/customization/fleet', ['fleet_name' => '  Nouvelle flotte  '])->assertOk();
    $after = $this->fleet->fresh()->getAttributes();
    expect($after['name'])->toBe('Nouvelle flotte');
    expect(array_diff_key($after, array_flip(['name', 'updated_at'])))->toBe(array_diff_key($before, array_flip(['name', 'updated_at'])));
    expect($this->otherFleet->fresh()->getAttributes())->toBe($otherBefore);
    expect(ApplicationSetting::find(1)->values)->toBe([]);
    Storage::disk('local')->assertExists($before['logo_path']);
    $this->get('/')->assertOk()->assertSee('Nouvelle flotte')->assertSee('<span class="sidebar-product-name">EXADCAM<small>', false);
    $this->postJson('/customization/fleet', ['fleet_name' => 'Nouvelle flotte', 'remove_logo' => true])->assertOk();
    expect($this->fleet->fresh()->name)->toBe('Nouvelle flotte');
    expect($this->fleet->fresh()->logo_path)->toBeNull();
    Storage::disk('local')->assertMissing($before['logo_path']);
});

it('rejects invalid fleet names and attempts to change fleet ownership or protected fields', function () {
    $this->actingAs($this->admin);
    foreach (['', '   ', null, str_repeat('x', 256), ['not a name']] as $invalid) {
        $this->postJson('/customization/fleet', ['fleet_name' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('fleet_name');
    }
    foreach ([['fleet_id' => $this->otherFleet->id], ['code' => 'OTHER'], ['status' => 'inactive'], ['subscription_id' => 99]] as $extra) {
        $this->postJson('/customization/fleet', ['fleet_name' => 'Changed', ...$extra])->assertUnprocessable();
    }
    expect($this->fleet->fresh()->name)->toBe('Fleet A');
    expect($this->otherFleet->fresh()->name)->toBe('Fleet B');
    $this->actingAs($this->member)->postJson('/customization/fleet', ['fleet_name' => 'Denied'])->assertForbidden();
    $this->fleet->update(['status' => 'inactive']);
    $this->actingAs($this->admin->fresh())->postJson('/customization/fleet', ['fleet_name' => 'Denied'])->assertForbidden();
    expect($this->fleet->fresh()->name)->toBe('Fleet A');
});
