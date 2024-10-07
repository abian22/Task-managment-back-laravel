<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Projects extends Model
{
    use HasFactory;

    protected $table = "projects";

    protected $fillable = [
        "title",
        "description",
        "members",
        'start_project_date',
        'end_project_date'
    ];

    public $timestamps = false;


    protected $casts = [
        'members' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id');    }

    public function task()
    {
        return $this->hasMany(Task::class);
    }

    protected static function booted(): void
    {

        static::creating(function (Projects $project) {
            // Asegurarse de que members sea un array
            $members = $project->members ?? [];

            // Obtener el ID del usuario autenticado, 
            // aunque salga error si funciona el metodo id()
            $authenticatedUserId = auth()->id();
            if ($authenticatedUserId) {
                // Agregar el usuario autenticado con rol 'admin' 
                // si aún no está en el array
                $exists = false;
                foreach ($members as $member) {
                    if ($member['user_id'] == $authenticatedUserId) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $members[] = [
                        'user_id' => $authenticatedUserId,
                        'rol' => 'admin'
                    ];
                }
            }

            $project->members = $members;
        });;
    }
}
