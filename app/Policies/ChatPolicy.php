<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Models\User;

final class ChatPolicy
{
    public function view(User $user, ChatModel $chat): bool
    {
        return $this->hasAccess($user, $chat);
    }

    public function update(User $user, ChatModel $chat): bool
    {
        return $this->hasAccess($user, $chat);
    }

    private function hasAccess(User $user, ChatModel $chat): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $sourceIds = $user->sources()->pluck('id')->all();
        if ($sourceIds === [] || ! in_array($chat->source_id, $sourceIds, true)) {
            return false;
        }

        $deptIds = $user->departments()->pluck('departments.id')->all();
        if ($deptIds !== [] && ! in_array($chat->department_id, $deptIds, true)) {
            return false;
        }

        return true;
    }
}
