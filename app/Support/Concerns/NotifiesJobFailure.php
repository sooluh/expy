<?php

namespace App\Support\Concerns;

use App\Models\User;
use Filament\Notifications\Notification;

trait NotifiesJobFailure
{
    protected function notifyFailure(?int $userId, string $title, string $message): void
    {
        if (! $userId) {
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($message)
            ->danger()
            ->sendToDatabase($user);
    }
}
