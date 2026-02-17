<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return $user->hasAnyRole(['admin', 'moderator']);
});

Broadcast::channel('moderator.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
