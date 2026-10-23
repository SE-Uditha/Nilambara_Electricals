<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failed_logins_are_throttled_and_audited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'nobody', 'password' => 'wrong-password']);
        }

        $this->post('/login', ['username' => 'nobody', 'password' => 'wrong-password'])->assertStatus(429);

        $this->assertTrue(AuditLog::where('action', 'auth.login.failed')->exists());
        $this->assertTrue(AuditLog::where('action', 'auth.login.throttled')->exists());
    }

    public function test_guests_cannot_reach_protected_pages(): void
    {
        foreach (['/customers', '/repair-jobs', '/payments', '/invoices', '/approval-requests', '/admin/backups'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }
}
