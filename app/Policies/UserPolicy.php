<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isActive() && ($actor->isSuperadmin() || $actor->isAdmin());
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, User $user): Response
    {
        if ($this->viewAny($actor) && ($actor->isSuperadmin()
            || ($actor->fleet_id !== null && $actor->fleet_id === $user->fleet_id && $user->isSimpleUser()))) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function update(User $actor, User $user): Response
    {
        if ($user->isSuperadmin()) {
            return Response::deny();
        }

        return $this->view($actor, $user);
    }

    public function delete(User $actor, User $user): Response
    {
        return $this->update($actor, $user);
    }
}
