<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    if (! $user->hasAnyRole(['admin', 'moderator'])) {
        return false;
    }

    $chat = \App\Infrastructure\Persistence\Eloquent\ChatModel::find($chatId);
    if ($chat === null) {
        return false;
    }

    return $user->can('view', $chat);
});

Broadcast::channel('moderator.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('source-inbox.{sourceId}', function ($user, int $sourceId) {
    if (! $user->hasAnyRole(['admin', 'moderator'])) {
        return false;
    }

    if ($user->hasRole('admin')) {
        return true;
    }

    return $user->sources()->where('sources.id', $sourceId)->exists();
});
