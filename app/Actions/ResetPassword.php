<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/** Fortify password reset action (min 8, R-094). */
class ResetPassword implements ResetsUserPasswords
{
    public function reset(User $user, array $input): void
    {
        validator($input, ['password' => ['required', 'string', 'min:8', 'confirmed']])->validate();
        $user->forceFill(['password' => Hash::make($input['password']), 'must_reset_password' => false])->save();
    }
}
