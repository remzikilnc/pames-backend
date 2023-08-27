<?php

namespace App\Services;

use App\Events\UserCreated;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Traits\AuthorizedRelationLoader;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;

class UserService
{
    use AuthorizedRelationLoader;

    protected $user;
    protected $role;
    protected $permission;

    public function __construct(User $user, Role $role, Permission $permission)
    {
        $this->user = $user;
        $this->role = $role;
        $this->permission = $permission;
    }

    public function index(array $params)
    {
        $paginatedUsers = app(PaginateUsers::class)->execute($params);
        return UserResource::collection($paginatedUsers->items());
    }

    /**
     * @throws Exception
     */
    public function create(array $params)
    {
        $user = $this->user->create($params);
        $params = collect($params);

        if ($params->has('roles')) {
            if (!$this->syncRoles($user, $params->get('roles'))) {
                $this->assignDefaultRole($user);
            }
        } else {
            $this->assignDefaultRole($user);
        }

        event(new UserCreated($user));

        return new UserResource($user->fresh());
    }

    public function show(User $user, string $requestedRelations): UserResource
    {
        $loadableRelations = $this->filterLoadableRelations($requestedRelations, User::class);

        $user->load($loadableRelations);

        return new UserResource($user);
    }

    public function update(User $user, array $params): UserResource
    {
        $filteredParams = collect($params)->except('email_verified_at')->toArray(); //fill without email_verified_at
        $user->fill($filteredParams);
        $params = collect($params);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            // $user->sendEmailVerificationNotification();
        }

        if ($user->isDirty('password')) {
            $user->password = Hash::make($params->get('password'));
        }

        if ($params->has('email_verified_at')) { //fill email_verified_at if authenticated user can be
            if (Auth::user()->can('updateEmailVerifiedAt', Auth::user())) {
                $user->email_verified_at = $params['email_verified_at'];
            }
        }

        if ($params->has('roles')) {
            $rolesSaved = $this->syncRoles($user, $params->get('roles'));
        }

        $user->save();

        return new UserResource(
            isset($rolesSaved) && $rolesSaved ? $user->fresh()->load('roles') : $user->fresh()
        );
    }

    protected function syncRoles(User $user, array $roles): bool|User
    {
        $validRoles = $this->filterValidRoles($roles);

        if (empty($validRoles)) {
            return false;
        }

        $allowedRoles = [];

        foreach ($validRoles as $role) {
            if (Auth::user()->can('assignRole', [$user, $role])) {
                $allowedRoles[] = $role;
            } else {
                Log::warning("User with ID: " . Auth::id() . " tried to assign the role: {$role} without permission.");
                return false;
            }
        }

        $user->syncRoles($allowedRoles);

        return true;
    }

    protected function filterValidRoles(array $roles): array
    {
        $validRoles = [];

        foreach ($roles as $roleName) {
            try {
                $role = $this->role->findByName($roleName, 'api');
                $validRoles[] = $role->name;
            } catch (RoleDoesNotExist $e) {
                continue;
            }
        }

        return $validRoles;
    }

    private function assignDefaultRole(User $user): void
    {
        $defaultRole = $this->role->getDefaultRoleForApi();

        if ($defaultRole) {
            $user->assignRole($defaultRole);
        }
    }
}
