<?php

use Illuminate\Support\Facades\Route;

test('production deployment is gated and uses atomic releases', function () {
    $workflow = file_get_contents(base_path('.github/workflows/tests.yml'));
    $releaseScript = file_get_contents(base_path('.github/deploy/remote-release.sh'));
    $ssrInstaller = file_get_contents(base_path('.github/deploy/install-web-ssr.sh'));
    $ssrService = file_get_contents(base_path('.github/deploy/prodeals-ssr.service'));
    $serverBootstrap = file_get_contents(base_path('.github/deploy/bootstrap-server.sh'));
    $buildEnvironment = file_get_contents(base_path('.env.example'));
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($workflow)
        ->toContain('needs: ci')
        ->toContain("vars.PRODUCTION_DEPLOY_ENABLED == 'true'")
        ->toContain('environment:')
        ->toContain('group: prodeals-production')
        ->toContain('curl --fail')
        ->toContain('https://prodeals.lk/mcp/marketplace')
        ->toContain('"method":"initialize"')
        ->toContain('VITE_TINYMCE_API_KEY: ${{ secrets.TINYMCE_API_KEY }}')
        ->toContain('npm run build:ssr')
        ->toContain('test -f bootstrap/ssr/app.js')
        ->toContain('npm prune --omit=dev')
        ->toContain('node_modules/@inertiajs/react')
        ->toContain('web_release_id')
        ->toContain('worker_release_id')
        ->toContain('rollback-to')
        ->toContain('inertia:check-ssr')
        ->toContain('sudo systemctl restart prodeals-ssr')
        ->toContain('sudo supervisorctl status prodeals-worker')
        ->toContain('Online Shopping &amp; Auctions in Sri Lanka')
        ->and($buildEnvironment)
        ->toContain('VITE_TINYMCE_API_KEY=')
        ->and($composer['require'])
        ->toHaveKey('laravel/mcp')
        ->and($composer['require-dev'])
        ->not->toHaveKey('laravel/mcp')
        ->and($releaseScript)
        ->toContain('mv -Tf "$next_link" "$current_link"')
        ->toContain('rollback_release')
        ->toContain('rollback_to_release')
        ->toContain('current_release')
        ->toContain('index = 5');

    expect($ssrInstaller)
        ->toContain('node_22.x')
        ->toContain('systemctl enable prodeals-ssr.service')
        ->toContain('/etc/sudoers.d/prodeals-deploy')
        ->toContain('/usr/bin/systemctl restart prodeals-ssr')
        ->and($ssrService)
        ->toContain('User=deploy')
        ->toContain('WorkingDirectory=/var/www/prodeals/current')
        ->toContain('ExecStart=/usr/bin/php8.4 artisan inertia:start-ssr --runtime=/usr/bin/node')
        ->toContain('MemoryMax=512M')
        ->toContain('Restart=always');

    expect($serverBootstrap)
        ->toContain('/usr/bin/supervisorctl status prodeals-worker');
});

test('production service configuration keeps queue timeout below retry interval', function () {
    $environment = file_get_contents(base_path('.github/deploy/production.env.example'));
    $supervisor = file_get_contents(base_path('.github/deploy/supervisor-prodeals.conf'));

    expect($environment)
        ->toContain('DB_QUEUE_RETRY_AFTER=90')
        ->toContain('MYSQL_ATTR_SSL_CA=/etc/ssl/certs/aws-rds-global-bundle.pem')
        ->toContain('MEDIA_DISK=r2')
        ->toContain('R2_PUBLIC_URL=https://media.prodeals.lk')
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
