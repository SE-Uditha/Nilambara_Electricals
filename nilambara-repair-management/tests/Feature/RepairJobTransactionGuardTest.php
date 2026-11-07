<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\RepairJobs\Services\RepairJobService;
use App\Support\RepairJobTransactionGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class RepairJobTransactionGuardTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    public function test_nontransactional_repair_jobs_are_rejected_and_logged(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $query = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn('isolated');
        $connection->shouldReceive('getName')->once()->andReturn('test');
        $connection->shouldReceive('table')->once()->with('information_schema.TABLES')->andReturn($query);
        $query->shouldReceive('where')->once()->with('TABLE_SCHEMA', 'isolated')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('TABLE_NAME', 'repair_jobs')->andReturnSelf();
        $query->shouldReceive('value')->once()->with('ENGINE')->andReturn('MyISAM');
        Log::shouldReceive('error')->once();

        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage(RepairJobTransactionGuard::MESSAGE);
        app(RepairJobTransactionGuard::class)->ensureSafe($connection);
    }

    public function test_innodb_repair_jobs_are_allowed(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $query = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn('isolated');
        $connection->shouldReceive('table')->once()->with('information_schema.TABLES')->andReturn($query);
        $query->shouldReceive('where')->once()->with('TABLE_SCHEMA', 'isolated')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('TABLE_NAME', 'repair_jobs')->andReturnSelf();
        $query->shouldReceive('value')->once()->with('ENGINE')->andReturn('InnoDB');
        Log::shouldReceive('error')->never();

        app(RepairJobTransactionGuard::class)->ensureSafe($connection);
        $this->assertTrue(true);
    }

    public function test_job_card_reprints_reuse_card_and_missing_job_returns_404(): void
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $job = $this->job();

        $this->actingAs($actor)->get(route('job-cards.print', $job))->assertOk()->assertSee('size: A5 portrait');
        $this->get(route('job-cards.duplicate', $job))->assertOk();
        $this->get(route('job-cards.print', 99999999))->assertNotFound();
        $this->assertDatabaseCount('job_cards', 1);
        $this->assertDatabaseHas('job_cards', ['repair_job_id' => $job->id, 'paper_size' => 'A5']);
        $this->assertDatabaseCount('document_print_histories', 2);
    }

    public function test_status_update_rolls_back_when_history_cannot_be_saved(): void
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $job = $this->job();
        $event = 'eloquent.creating: '.\App\Modules\JobTracking\Models\JobStatusHistory::class;
        Event::listen($event, static function (): void {
            throw new RuntimeException('Simulated history failure');
        });

        try {
            app(RepairJobService::class)->updateStatus($job, ['status' => JobStatus::UnderRepair->value], $actor);
            $this->fail('The simulated history failure should be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated history failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertSame(JobStatus::Received, $job->fresh()->status);
        $this->assertDatabaseCount('job_status_histories', 0);
    }

    public function test_blocked_job_card_shows_safe_error_without_writes(): void
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $job = $this->job();
        $guard = Mockery::mock(RepairJobTransactionGuard::class);
        $guard->shouldReceive('ensureSafe')->once()->andThrow(new ServiceUnavailableHttpException(null, RepairJobTransactionGuard::MESSAGE));
        $this->app->instance(RepairJobTransactionGuard::class, $guard);

        $this->actingAs($actor)->get(route('job-cards.print', $job))
            ->assertStatus(503)
            ->assertSee(RepairJobTransactionGuard::MESSAGE);
        $this->assertDatabaseCount('job_cards', 0);
        $this->assertDatabaseCount('document_print_histories', 0);
    }
}
