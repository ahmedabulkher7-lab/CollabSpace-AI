<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('projects.{projectId}', function (User $user, int $projectId) {
    $project = Project::find($projectId);

    return $project && $project->hasAccess($user);
});
