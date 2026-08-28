<?php

use Illuminate\Support\Facades\Route;

test('production deployment is gated and uses atomic releases', function () {
    $workflow = file_get_contents(base_path('.github/workflows/tests.yml'));
    $releaseScript = file_get_contents(base_path('.github/deploy/remote-release.sh'));

    expect($workflow)
        ->toContain('needs: ci')
        ->toContain("vars.PRODUCTION_DEPLOY_ENABLED == 'true'")
        ->toContain('environment:')
        ->toContain('group: prodeals-production')
        ->toContain('curl --fail')
        ->and($releaseScript)
        ->toContain('mv -Tf "$next_link" "$current_link"')
        ->toContain('rollback_release')
        ->toContain('index = 5');
});

test('production service configuration keeps queue timeout below retry interval', function () {
    $environment = file_get_contents(base_path('.github/deploy/production.env.example'));
    $supervisor = file_get_contents(base_path('.github/deploy/supervisor-prodeals.conf'));

    expect($environment)
        ->toContain('DB_QUEUE_RETRY_AFTER=90')
        ->toContain('MYSQL_ATTR_SSL_CA=/etc/ssl/certs/aws-rds-global-bundle.pem')
        ->toContain('MEDIA_DISK=r2')
        ->and($supervisor)
        ->toContain('--timeout=75')
        ->toContain('user=deploy');
});

test('trusted proxy headers preserve secure urls behind Cloudflare', function () {
    Route::get('/testing/proxy-url', fn (): string => url('/testing/proxy-url'));

    $this->withHeaders([
        'X-Forwarded-Host' => 'prodeals.lk',
        'X-Forwarded-Proto' => 'https',
    ])->get('http://127.0.0.1/testing/proxy-url')
        ->assertOk()
        ->assertSeeText('https://prodeals.lk/testing/proxy-url');
});
