<?php

namespace App\Support;

use App\Models\User;

class SuperAdmin
{
    public static function email(): ?string
    {
        $email = config('app.super_admin_email');

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }

    public static function check(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $adminEmail = self::email();

        if (! $adminEmail) {
            return false;
        }

        return strtolower($user->email) === $adminEmail;
    }
}
