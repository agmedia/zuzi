<?php

namespace App\Models\Roles;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ability extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'abilities';

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
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function roles()
    {
        return $this->hasManyThrough('App\Models\Roles\Role', 'App\Models\Roles\Permission', 'ability_id', 'id', 'id', 'entity_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function getFullList()
    {
        $abilities = self::with('roles')->where('name', '!=', '*')->get();

        for ($i = 0; $i < $abilities->count(); $i++) {
            foreach ($abilities[$i]->roles as $role) {
                $abilities[$i]->{$role->name} = true;
            }
        }

        return $abilities;
    }


    /**
     * @param string|array $ability
     *
     * @return bool
     */
    public static function authorize($abilities): bool
    {
        if (auth()->user()->isNotAn('admin', 'zavod') && ! auth()->user()->institucija) {
            abort(303);
        }

        if (is_array($abilities)) {
            $pass = false;

            foreach ($abilities as $item) {
                if (\Bouncer::can($item)) {
                    $pass = true;
                }
            }

            if ( ! $pass) {
                abort(401);
            }

        } else {
            if ( ! \Bouncer::can($abilities)) {
                abort(401);
            }
        }

        return true;
    }

}
