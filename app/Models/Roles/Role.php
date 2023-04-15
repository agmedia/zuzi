<?php

namespace App\Models\Roles;

use App\Models\Back\User\GroupToKorisnik;
use App\Models\Back\User\Mjeritelj;
use App\Models\Back\User\UserToMjeritelj;
use App\Models\Back\User\UserToTijelo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Role extends Model
{

    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];


    /**
     * @param Ability $ability
     *
     * @return bool
     */
    public function hasAbility(Ability $ability)
    {
        $perm = Permission::where('entity_id', $this->id)->where('ability_id', $ability->id)->first();

        if (isset($perm->id)) {
            return true;
        }

        return false;
    }


    /**
     * @return array
     */
    public static function getAllWithPermissions()
    {
        $roles    = self::where('name', '!=', 'admin')->get();
        $response = [];

        foreach ($roles as $role) {
            $response[$role->id]['role']        = $role;
            $response[$role->id]['permissions'] = DB::table('permissions')->where('entity_id', $role->id)->get();
        }

        return $response;
    }


    /**
     * @param $role_id
     *
     * @return mixed
     */
    public static function getAbilities($role_id)
    {
        $permissions = DB::table('permissions')->where('entity_id', $role_id)->get();

        return Ability::whereIn('id', $permissions->pluck('ability_id'))->get();
    }


    /**
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function selectList()
    {
        $query = (new Role())->newQuery();

        // if user is NOT super-admin, remove all super-admin users.
        if ( ! Auth::user()->can('*')) {
            $query->where('name', '!=', 'admin');
        }

        return $query->get();
    }


    /**
     * @param int    $user_id
     * @param string $role
     *
     * @return bool
     */
    public static function change(int $user_id, string $role)
    {
        $user = User::find($user_id);

        if ($user) {
            $user->retract($user->details->role);
            $user->assign($role);

            return true;
        }

        return false;
    }


    /**
     * @param int    $user_id
     * @param string $role
     *
     * @return bool
     */
    public static function checkIfChanged(int $user_id, string $role)
    {
        $user = User::find($user_id);

        if ($user->details->role != $role) {
            return true;
        }

        return false;
    }
}
