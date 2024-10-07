<?php

namespace App\Http\Controllers;

use App\Models\Projects;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;



class TaskController extends Controller
{
    public function createTask(Request $request, $projectId)
    {
        $userId = $request->user()->id;
        $project = Projects::find($projectId);

        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Obtener los miembros del proyecto
        $members = $project->members;
        $userIds = array_column($members, 'user_id');

        // Verificar si el usuario que está haciendo la solicitud es miembro del proyecto
        if (!in_array($userId, $userIds)) {
            return response()->json([
                "message" => "User is not associated with this project",
                "status" => 403
            ], 403);
        }

        // Validación de la solicitud
        $validator = Validator::make($request->all(), [
            "title" => "required|string|max:255",
            "description" => "required|string",
            "complete" => "required|boolean",
            "start_task_date" => "nullable|date",
            "end_task_date" => "nullable|date",
            "assigned_users" => "nullable|array",
            "assigned_users.*" => "exists:users,id"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "message" => "Error validating data",
                "errors" => $validator->errors(),
                "status" => 400
            ], 400);
        }

        // Obtener los datos validados
        $validatedData = $validator->validated();

        // Verificar que todos los usuarios asignados estén en el proyecto
        if (isset($validatedData['assigned_users']) && !empty($validatedData['assigned_users'])) {
            $assignedUserIds = $validatedData['assigned_users'];

            // Verificar que cada usuario asignado está en la lista de miembros del proyecto
            foreach ($assignedUserIds as $assignedUserId) {
                if (!in_array($assignedUserId, $userIds)) {
                    return response()->json([
                        "message" => "One or more assigned users are not associated with this project",
                        "status" => 403
                    ], 403);
                }
            }
        }

        // Añadir project_id y created_by a los datos validados
        $validatedData['project_id'] = $projectId;
        $validatedData['created_by'] = $userId; // Establecer el creador de la tarea

        // Crear una nueva tarea con los datos validados
        $task = Task::create($validatedData);

        // Asignar usuarios a la tarea, si hay alguno
        if (isset($validatedData['assigned_users']) && !empty($validatedData['assigned_users'])) {
            $task->users()->sync($validatedData['assigned_users']);
        }

        return response()->json([
            "task" => $task,
            "status" => 201
        ], 201);
    }



    public function deleteTask(Request $request, $taskId)
    {
        // Obtén el ID del usuario autenticado
        $userId = $request->user()->id;

        // Encuentra la tarea por ID
        $task = Task::find($taskId);

        // Verifica si la tarea existe
        if (!$task) {
            return response()->json([
                "message" => "Task not found",
                "status" => 404
            ], 404);
        }

        // Obtén el proyecto asociado con la tarea
        $project = Projects::find($task->project_id);

        // Verifica si el proyecto existe
        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Obtén los miembros del proyecto
        $members = $project->members;

        // Verifica si $members es un array
        if (!is_array($members)) {
            return response()->json([
                "message" => "Unexpected data format for members",
                "status" => 500
            ], 500);
        }

        // Extrae los user_id de los miembros usando array_map
        $userIds = array_map(function ($member) {
            return $member['user_id'];
        }, $members);

        // Verifica si el usuario está asociado con el proyecto
        if (!in_array($userId, $userIds)) {
            return response()->json([
                "message" => "User is not associated with this project",
                "status" => 403
            ], 403);
        }

        // Elimina la tarea
        $task->delete();

        return response()->json([
            "message" => "Task deleted successfully",
            "status" => 200
        ], 200);
    }

    public function updateTask(Request $request, $taskId)
    {
        // Obtén el ID del usuario autenticado
        $userId = $request->user()->id;

        // Encuentra la tarea por ID
        $task = Task::find($taskId);

        // Verifica si la tarea existe
        if (!$task) {
            return response()->json([
                "message" => "Task not found",
                "status" => 404
            ], 404);
        }

        // Obtén el proyecto asociado con la tarea
        $project = Projects::find($task->project_id);

        // Verifica si el proyecto existe
        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Obtén los miembros del proyecto
        $members = $project->members;

        // Verifica si $members es un array
        if (!is_array($members)) {
            return response()->json([
                "message" => "Unexpected data format for members",
                "status" => 500
            ], 500);
        }

        // Extrae los user_id de los miembros usando array_map
        $userIds = array_map(function ($member) {
            return $member['user_id'];
        }, $members);

        // Verifica si el usuario está asociado con el proyecto
        if (!in_array($userId, $userIds)) {
            return response()->json([
                "message" => "User is not associated with this project",
                "status" => 403
            ], 403);
        }

        // Validación de la solicitud
        $validator = Validator::make($request->all(), [
            "title" => "nullable|string|max:255",
            "description" => "nullable|string",
            "complete" => "nullable|boolean",
            "start_task_date" => "nullable|date_format:Y-m-d",
            "end_task_date" => "nullable|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "message" => "Error validating data",
                "errors" => $validator->errors(),
                "status" => 400
            ], 400);
        }

        // Obtener los datos validados
        $validatedData = $validator->validated();

        // Actualizar la tarea con los datos validados
        $task->update($validatedData);

        return response()->json([
            "task" => $task,
            "status" => 200
        ], 200);
    }

    public function getTasksByProject(Request $request, $projectId)
    {
        // Obtén el ID del usuario autenticado
        $userId = $request->user()->id;

        // Encuentra el proyecto por ID
        $project = Projects::find($projectId);

        // Verifica si el proyecto existe
        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Obtén los miembros del proyecto
        $members = $project->members;

        // Verifica si $members es un array
        if (!is_array($members)) {
            return response()->json([
                "message" => "Unexpected data format for members",
                "status" => 500
            ], 500);
        }

        // Extrae los user_id de los miembros usando array_map
        $userIds = array_map(function ($member) {
            return $member['user_id'];
        }, $members);

        // Verifica si el usuario está asociado con el proyecto
        if (!in_array($userId, $userIds)) {
            return response()->json([
                "message" => "User is not associated with this project",
                "status" => 403
            ], 403);
        }

        // Recupera todas las tareas asociadas al proyecto
        $tasks = Task::where('project_id', $projectId)->get();

        // Devuelve las tareas en la respuesta
        return response()->json([
            "tasks" => $tasks,
            "status" => 200
        ], 200);
    }

    public function getTask(Request $request, $projectId, $taskId)
    {
        // Obtén el ID del usuario autenticado
        $userId = $request->user()->id;

        // Encuentra el proyecto por ID
        $project = Projects::find($projectId);

        // Verifica si el proyecto existe
        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Obtén los miembros del proyecto
        $members = $project->members;

        // Verifica si $members es un array
        if (!is_array($members)) {
            return response()->json([
                "message" => "Unexpected data format for members",
                "status" => 500
            ], 500);
        }

        // Extrae los user_id de los miembros usando array_map
        $userIds = array_map(function ($member) {
            return $member['user_id'];
        }, $members);

        // Verifica si el usuario está asociado con el proyecto
        if (!in_array($userId, $userIds)) {
            return response()->json([
                "message" => "User is not associated with this project",
                "status" => 403
            ], 403);
        }

        // Encuentra la tarea por ID
        $task = Task::where('id', $taskId)
            ->where('project_id', $projectId)
            ->first();

        // Verifica si la tarea existe
        if (!$task) {
            return response()->json([
                "message" => "Task not found",
                "status" => 404
            ], 404);
        }

        // Devuelve la tarea en la respuesta
        return response()->json([
            "task" => $task,
            "status" => 200
        ], 200);
    }

    public function assignUserToTask(Request $request, $taskId)
    {
        // Obtén el ID del usuario autenticado
        $userId = $request->user()->id;

        // Encuentra la tarea por ID
        $task = Task::find($taskId);

        // Verifica si la tarea existe
        if (!$task) {
            return response()->json([
                "message" => "Task not found",
                "status" => 404
            ], 404);
        }

        // Obtén el proyecto asociado con la tarea
        $project = Projects::find($task->project_id);

        // Verifica si el proyecto existe
        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Obtén los miembros del proyecto
        $members = $project->members;

        // Verifica si $members es un array
        if (!is_array($members)) {
            return response()->json([
                "message" => "Unexpected data format for members",
                "status" => 500
            ], 500);
        }

        // Extrae los user_id de los miembros usando array_map
        $userIds = array_map(function ($member) {
            return $member['user_id'];
        }, $members);

        // Verifica si el usuario está asociado con el proyecto
        if (!in_array($userId, $userIds)) {
            return response()->json([
                "message" => "User is not associated with this project",
                "status" => 403
            ], 403);
        }

        // Validación de la solicitud
        $validator = Validator::make($request->all(), [
            "user_id" => "required|integer|exists:users,id",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "message" => "Error validating data",
                "errors" => $validator->errors(),
                "status" => 400
            ], 400);
        }

        // Obtener los datos validados
        $validatedData = $validator->validated();

        // Verificar si el usuario ya está asignado a la tarea
        $existingAssignment = DB::table('task_user')
            ->where('task_id', $taskId)
            ->where('user_id', $validatedData['user_id'])
            ->first();

        if ($existingAssignment) {
            return response()->json([
                "message" => "User is already assigned to this task",
                "status" => 400
            ], 400);
        }

        // Asignar el usuario a la tarea
        DB::table('task_user')->insert([
            'task_id' => $taskId,
            'user_id' => $validatedData['user_id']
        ]);

        return response()->json([
            "message" => "User assigned to task successfully",
            "status" => 200
        ], 200);
    }

    public function getAssignedUsers($taskId)
    {
        // Encuentra la tarea por ID
        $task = Task::find($taskId);

        // Verifica si la tarea existe
        if (!$task) {
            return response()->json([
                "message" => "Task not found",
                "status" => 404
            ], 404);
        }

        // Obtener los usuarios asignados a la tarea
        $assignedUsers = DB::table('task_user')
            ->where('task_id', $taskId)
            ->join('users', 'task_user.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email') // Puedes seleccionar más campos si es necesario
            ->get();

        return response()->json([
            "assigned_users" => $assignedUsers,
            "status" => 200
        ], 200);
    }


    public function unassignUserFromTask(Request $request, $taskId)
    {
        // Obtén el ID del usuario autenticado
        $userId = $request->user()->id;

        // Encuentra la tarea por ID
        $task = Task::find($taskId);

        // Verifica si la tarea existe
        if (!$task) {
            return response()->json([
                "message" => "Task not found",
                "status" => 404
            ], 404);
        }

        // Obtén el proyecto asociado con la tarea
        $project = Projects::find($task->project_id);

        // Verifica si el proyecto existe
        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Obtén los miembros del proyecto
        $members = $project->members;

        // Verifica si $members es un array
        if (!is_array($members)) {
            return response()->json([
                "message" => "Unexpected data format for members",
                "status" => 500
            ], 500);
        }

        // Extrae los user_id de los miembros usando array_map
        $userIds = array_map(function ($member) {
            return $member['user_id'];
        }, $members);

        // Verifica si el usuario está asociado con el proyecto
        if (!in_array($userId, $userIds)) {
            return response()->json([
                "message" => "User is not associated with this project",
                "status" => 403
            ], 403);
        }

        // Validación de la solicitud
        $validator = Validator::make($request->all(), [
            "user_id" => "required|integer|exists:users,id",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "message" => "Error validating data",
                "errors" => $validator->errors(),
                "status" => 400
            ], 400);
        }

        // Obtener los datos validados
        $validatedData = $validator->validated();

        // Verificar si el usuario está asignado a la tarea
        $existingAssignment = DB::table('task_user')
            ->where('task_id', $taskId)
            ->where('user_id', $validatedData['user_id'])
            ->first();

        if (!$existingAssignment) {
            return response()->json([
                "message" => "User is not assigned to this task",
                "status" => 404
            ], 404);
        }

        // Eliminar la asignación del usuario a la tarea
        DB::table('task_user')
            ->where('task_id', $taskId)
            ->where('user_id', $validatedData['user_id'])
            ->delete();

        return response()->json([
            "message" => "User unassigned from task successfully",
            "status" => 200
        ], 200);
    }
}
