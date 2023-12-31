<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function authorize($ability, $arguments = [])
    {
        if (Auth::check()) {
            [$ability, $arguments] = $this->parseAbilityAndArguments($ability, $arguments);
            return app(Gate::class)->authorize($ability, $arguments);
        } else {
            $guest = new User();
            $guest->forceFill(['id' => -1]);
            $guestRole = app(Role::class)->getGuestRole();
            if ($guestRole) {
                $guest->setRelation('roles', collect([$guestRole]));
            }
            return $this->authorizeForUser($guest, $ability, $arguments);
        }
    }
}
