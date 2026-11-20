<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Revoke authenticated sessions when a credential changes.
 *
 * A password reset/change must end assurance obtained with the old credential.
 * The web group also runs AuthenticateSession, but that middleware only runs on
 * the next request; deleting the rows here closes the window immediately.
 */
class SessionRevoker
{
    public static function allFor(User $user): void
    {
        self::deleteSessions($user, null);
    }

    public static function othersFor(User $user, ?string $currentSessionId): void
    {
        self::deleteSessions($user, $currentSessionId);
    }

    private static function deleteSessions(User $user, ?string $keepSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier());

        if ($keepSessionId !== null && $keepSessionId !== '') {
            $query->where('id', '!=', $keepSessionId);
        }

        $query->delete();
    }
}
