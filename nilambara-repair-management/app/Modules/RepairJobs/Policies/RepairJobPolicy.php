<?php

namespace App\Modules\RepairJobs\Policies;

use App\Models\User;
use App\Modules\RepairJobs\Models\RepairJob;

class RepairJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('jobs.view');
    }

    public function view(User $user, RepairJob $job): bool
    {
        if (! $user->can('jobs.view')) {
            return false;
        }

        return true;
    }

    public function inspect(User $user, RepairJob $job): bool
    {
        return $user->can('jobs.inspect') && $this->view($user, $job);
    }

    public function update(User $user, RepairJob $job): bool
    {
        return $user->can('jobs.update') && $this->view($user, $job);
    }

    public function assign(User $user, RepairJob $job): bool
    {
        return $user->can('jobs.assign') && $this->view($user, $job);
    }

    public function repair(User $user, RepairJob $job): bool
    {
        return $user->can('jobs.repair') && $this->view($user, $job);
    }

    public function updateStatus(User $user, RepairJob $job): bool
    {
        return $user->can('jobs.update-status') && $this->view($user, $job);
    }

    public function complete(User $user, RepairJob $job): bool
    {
        return $user->can('jobs.complete') && $this->view($user, $job);
    }

    public function cancel(User $user, RepairJob $job): bool
    {
        return $user->can('jobs.cancel') && $this->view($user, $job);
    }

    public function delete(User $user, RepairJob $job): bool
    {
        return $user->can('jobs.delete') && $this->view($user, $job);
    }
}
