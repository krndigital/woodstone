<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_security_policy_with_nonce(): void
    {
        $response = $this->get(route('login'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $csp);
        $response->assertHeader('Permissions-Policy');
    }

    public function test_health_check_includes_database(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_backup_refuses_an_in_memory_database(): void
    {
        config(['database.connections.memtest' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $this->artisan('woodstone:backup', ['--connection' => 'memtest'])->expectsOutputToContain('not found')->assertFailed();
    }

    public function test_backup_copies_sqlite_and_keeps_the_newest(): void
    {
        $db = storage_path('framework/testing/backup-test.sqlite');
        File::ensureDirectoryExists(dirname($db));
        File::put($db, 'sqlite-data');
        config(['database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $db]]);
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/woodstone-20000101-000000.sqlite', 'old');

        $this->artisan('woodstone:backup', ['--keep' => 1, '--connection' => 'backup_test'])->assertSuccessful();

        $files = collect(File::files($dir))->map(fn ($f) => $f->getFilename())->filter(fn ($n) => str_starts_with($n, 'woodstone-'))->values();
        $this->assertCount(1, $files);
        $this->assertNotSame('woodstone-20000101-000000.sqlite', $files[0]);
        File::deleteDirectory($dir);
        File::delete($db);
    }
}
