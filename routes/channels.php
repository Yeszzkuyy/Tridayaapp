<?php

use Illuminate\Support\Facades\Broadcast;

// Kanal privat notifikasi per user: hanya pemiliknya yang boleh mendengar.
// Route auth: /broadcasting/auth (channel `notifications.{userId}`).
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
