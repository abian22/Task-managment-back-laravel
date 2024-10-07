<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $table = 'task';

    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'description',
        'complete',
        'start_task_date',
        'end_task_date'
    ];

    protected $dates = [
        'start_task_date',
        'end_task_date',
    ];

    public function project()
    {
        return $this->belongsTo(Projects::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'task_user');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
    