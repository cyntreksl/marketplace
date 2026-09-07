<?php

use Illuminate\Support\Facades\Route;

test('production deployment is gated and uses atomic releases', function () {
    $workflow = file_get_contents(base_path('.github/workflows/tests.yml'));
    $deploymentScript = file_get_contents(base_path('.github/deploy/deploy-production.sh'));
    $releaseScript = file_get_contents(base_path('.github/deploy/remote-release.sh'));
    $ssrInstaller = file_get_contents(base_path('.github/deploy/install-web-ssr.sh'));
    $ssrService = file_get_contents(base_path('.github/deploy/prodeals-ssr.service'));
    $serverBootstrap = file_get_contents(base_path('.github/deploy/bootstrap-server.sh'));
    $viteConfiguration = file_get_contents(base_path('vite.config.ts'));
    $buildEnvironment = file_get_contents(base_path('.env.example'));
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($workflow)
        ->toContain('needs: [ci, package]')
        ->toContain("vars.PRODUCTION_DEPLOY_ENABLED == 'true'")
        ->toContain('environment:')
        ->toContain('group: prodeals-production')
        ->toContain('- parallel:')
        ->toContain('cache: npm')
        ->toContain('npm ci')
        ->toContain('bash .github/deploy/deploy-production.sh artifact/prodeals-release.tar.gz')
        ->toContain('VITE_TINYMCE_API_KEY: ${{ secrets.TINYMCE_API_KEY }}')
        ->toContain('npm run build:ssr')
        ->toContain('test -f bootstrap/ssr/app.js')
        ->toContain('npm prune --omit=dev')
        ->toContain('node_modules/@inertiajs/react')
        ->and($composer['scripts']['test:coverage'])
        ->toContain('@php artisan test --compact --parallel --coverage --min=80')
        ->and($deploymentScript)
        ->toContain('ControlMaster=auto')
        ->toContain("run_stage 'Upload and prepare releases' run_on_hosts prepare_release")
        ->toContain("run_stage 'Activate releases' run_on_hosts activate_release")
        ->toContain("run_stage 'Clean old releases' run_on_hosts cleanup_release")
        ->toContain('web_release_id')
        ->toContain('worker_release_id')
        ->toContain('rollback-to')
        ->toContain("run_stage 'Run database and media migrations' run_migrations")
        ->toContain('inertia:check-ssr')
        ->toContain('127.0.0.1:13714')
        ->toContain('sudo systemctl restart prodeals-ssr')
        ->toContain('sudo supervisorctl status prodeals-worker')
        ->toContain('Online Shopping &amp; Auctions in Sri Lanka')
        ->toContain('https://prodeals.lk/mcp/marketplace')
        ->toContain('"method":"initialize"')
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

    expect($viteConfiguration)
        ->toContain("host: '127.0.0.1'");
});

test('production service configuration keeps queue timeout below retry interval', function () {
    $environment = file_get_contents(base_path('.github/deploy/production.env.example'));
    $supervisor = file_get_contents(base_path('.github/deploy/supervisor-prodeals.conf'));
    $metaWorkflow = file_get_contents(base_path('.github/workflows/configure-meta-conversions.yml'));
    $metaConfiguration = file_get_contents(base_path('.github/deploy/sync-meta-conversions.sh'));

    expect($environment)
        ->toContain('DB_QUEUE_RETRY_AFTER=90')
        ->toContain('MYSQL_ATTR_SSL_CA=/etc/ssl/certs/aws-rds-global-bundle.pem')
        ->toContain('MEDIA_DISK=r2')
        ->toContain('R2_PUBLIC_URL=https://media.prodeals.lk')
        ->toContain('META_CONVERSIONS_ENABLED=false')
        ->toContain('META_CONVERSIONS_API_VERSION=v25.0')
        ->and($supervisor)
        ->toContain('--timeout=75')
        ->toContain('user=deploy')
        ->and($metaWorkflow)
        ->toContain('secrets.META_CONVERSIONS_ACCESS_TOKEN')
        ->toContain('vars.META_CONVERSIONS_ENABLED')
        ->toContain('vars.META_CONVERSIONS_PIXEL_ID')
        ->toContain('for host in "$WEB_HOST" "$WORKER_HOST"')
        ->toContain('meta:conversions:test --test-event-code=-')
        ->toContain('sudo systemctl restart php8.4-fpm')
        ->toContain('sudo supervisorctl restart prodeals-worker')
        ->not->toContain('echo "$META_CONVERSIONS_ACCESS_TOKEN"')
        ->and($metaConfiguration)
        ->toContain('META_CONVERSIONS_ACCESS_TOKEN=/d')
        ->toContain('mktemp "${shared_dir}/.env.meta.XXXXXX"')
        ->toContain('mv -f -- "$temporary_file" "$environment_file"')
        ->toContain('rollback_configuration')
        ->toContain('php8.4 artisan config:cache');
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
