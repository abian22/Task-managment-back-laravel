<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Models\Projects;


class ProjectsController extends Controller
{
    public function createProject(Request $request)
    {
        // Validación de la solicitud
        $validator = Validator::make($request->all(), [
            "title" => "required|string|max:255",
            "description" => "required|string",
            "members" => "nullable|array",
            "members.*.user_id" => "nullable|integer|exists:users,id",
            "members.*.rol" => "nullable|string|in:admin,project manager,member",
            "start_project_date" => "required|date",
            "end_project_date" => "required|date",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "message" => "Error validating data",
                "errors" => $validator->errors(),
                "status" => 400
            ], 400);
        }

        $membersArray = $request->input('members', []);

        // Inicializa `members` como una lista de objetos
        $members = [];
        foreach ($membersArray as $member) {
            if (!empty($member['user_id']) && isset($member['rol'])) {
                $userId = $member['user_id'];
                if (User::find($userId)) {
                    $members[] = [
                        'user_id' => $userId,
                        'rol' => $member['rol']
                    ];
                } else {
                    return response()->json([
                        "message" => "Invalid user ID: $userId",
                        "status" => 400
                    ], 400);
                }
            }
        }

        $user = $request->user()->id;
        if ($user) {
            // Construir un nuevo array sin el usuario autenticado
            $updatedMembers = [];
            foreach ($members as $member) {
                if ($member['user_id'] !== $user) {
                    $updatedMembers[] = $member;
                }
            }

            // Agregar el usuario autenticado con rol 'admin'
            $updatedMembers[] = [
                'user_id' => $user,
                'rol' => 'admin'
            ];

            $members = $updatedMembers;
        }

        // Crear el proyecto
        $project = Projects::create([
            "title" => $request->title,
            "description" => $request->description,
            "members" => $members,
            "start_project_date" => $request->start_project_date,
            "end_project_date" => $request->end_project_date,
        ]);

        if (!$project) {
            return response()->json([
                "message" => "Error creating project",
                "status" => 500
            ], 500);
        }

        return response()->json([
            "project" => $project,
            "status" => 201
        ], 201);
    }

    public function updateProject(Request $request, $id)
    {
        $user = $request->user()->id;
        $project = Projects::find($id);

        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Verificar si el usuario es el creador del proyecto
        if ($project->created_by !== $user) {
            // Verificar el rol del usuario en los miembros del proyecto
            $members = collect($project->members); // Si 'members' es un campo JSON
            $member = $members->firstWhere('user_id', $user);

            if ($member) {
                $rol = $member['rol'];

                // Comprueba el rol de quien va a modificar el proyecto
                if ($rol !== 'admin' && $rol !== "project manager") {
                    return response()->json([
                        "message" => "Unauthorized",
                        "status" => 403
                    ], 403);
                }
            } else {
                // Lanza un error si no se encuentra al usuario en el proyecto
                return response()->json([
                    "message" => "The user is not in this project",
                    "status" => 403
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            "title" => "string|max:255",
            "description" => "string",
            "members" => "nullable|array",
            "members.*.user_id" => "integer|exists:users,id",
            "members.*.rol" => "string|in:project manager,member",
            "start_project_date" => "date",
            "end_project_date" => "date",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "message" => "Error validating data",
                "errors" => $validator->errors(),
                "status" => 400
            ], 400);
        }

        $project->update([
            "title" => $request->input('title', $project->title),
            "description" => $request->input('description', $project->description),
            "members" => $request->input('members', $project->members),
            "start_project_date" => $request->input('start_project_date', $project->start_project_date),
            "end_project_date" => $request->input('end_project_date', $project->end_project_date),
        ]);

        return response()->json([
            "project" => $project,
            "status" => 201
        ], 201);
    }


    public function deleteProject(Request $request, $id)
    {
        $user = $request->user()->id;
        $project = Projects::find($id);

        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Verificar si el usuario es miembro del proyecto y obtener su rol
        $userRole = null;
        foreach ($project->members as $member) {
            if ($member['user_id'] == $user) {
                $userRole = $member['rol'];
                break;
            }
        }

        // Si no se encuentra al usuario en el proyecto, lanza un error
        if (!$userRole) {
            return response()->json([
                "message" => "The user is not in this project",
                "status" => 403
            ], 403);
        }

        // Verificar el rol del usuario
        if ($userRole !== 'admin') {
            return response()->json([
                "message" => "Unauthorized",
                "status" => 403
            ], 403);
        }

        // Eliminar el proyecto
        $project->delete();

        return response()->json([
            "message" => "Project deleted successfully",
            "status" => 200
        ], 200);
    }

    public function getProject(Request $request, $id)
    {

        $user = $request->user()->id;
        $project = Projects::find($id);

        // Verifica si el proyecto existe
        if (!$project) {
            return response()->json([
                "message" => "Project not found",
                "status" => 404
            ], 404);
        }

        // Verifica si el usuario se encuentra en el proyecto
        if (!array_key_exists($user, $project->members)) {
            return response()->json([
                "message" => "User is not in this project",
                "status" => 404
            ], 404);
        }

        return response()->json([
            "project" => $project,
            "status" => 201
        ], 201);
    }

    public function getAllMyProjects(Request $request)
    {
        $userId = $request->user()->id;
        $projects = Projects::all();

        // Verifica si se obtienen proyectos
        if ($projects->isEmpty()) {
            return response()->json([
                "message" => "No projects found",
                "status" => 404
            ], 404);
        }

        // Filtra los proyectos en los que el usuario es miembro
        $userProjects = $projects->filter(function ($project) use ($userId) {
            // Verifica si members es un string JSON o un array
            $members = $project->members;

            // Decodifica el campo JSON members si es un string
            if (is_string($members)) {
                $members = json_decode($members, true);
            }

            // Asegúrate de que $members sea un array
            if (!is_array($members)) {
                return false;
            }

            // Verifica si el usuario está en la lista de miembros
            foreach ($members as $member) {
                if (isset($member['user_id']) && $member['user_id'] == $userId) {
                    return true;
                }
            }

            return false;
        });

        // Verifica si el usuario tiene proyectos
        if ($userProjects->isEmpty()) {
            return response()->json([
                "message" => "No projects found for this user",
                "status" => 404
            ], 404);
        }

        return response()->json([
            "projects" => $userProjects,
            "status" => 200
        ], 200);
    }

    public function getUserByEmail(Request $request)
    {
        $email = $request->query('email'); // Obtén el email desde la consulta (query parameter)

        // Busca al usuario por email
        $user = User::where('email', $email)->first();

        // Verifica si el usuario existe
        if (!$user) {
            return response()->json([
                "message" => "User not found",
                "status" => 404
            ], 404);
        }

        // Retorna la información del usuario
        return response()->json([
            "user_id" => $user->id,
            "status" => 200
        ], 200);
    }

    public function getProjectMembers($id)
    {
        // Encuentra el proyecto con la relación 'members'
        $project = Projects::with('members')->findOrFail($id);
        
        // Obtén los miembros del proyecto
        $members = $project->members;

        // Asegúrate de que $members sea una colección
        $membersCollection = collect($members);

        // Mapear los detalles del usuario
        $membersWithDetails = $membersCollection->map(function ($member) {
            // Obtener detalles del usuario
            $userDetails = User::find($member['user_id']); // Suponiendo que $member es un array

            if (!$userDetails) {
                // Manejar el caso donde el usuario no existe
                return [
                    'user_id' => $member['user_id'],
                    'name' => 'Unknown',
                    'rol' => $member['rol'],
                ];
            }
            
            return [
                'user_id' => $userDetails->id,
                'name' => $userDetails->name,
                'rol' => $member['rol'],
            ];
        });

        // Devolver la respuesta en formato JSON
        return response()->json($membersWithDetails);
    }
    

    public function addMembersToProject(Request $request, $projectId)
{
    // Validación de la solicitud
    $validator = Validator::make($request->all(), [
        "members" => "required|array",
        "members.*.user_id" => "required|integer|exists:users,id",
        "members.*.rol" => "required|string|in:admin,project manager,member",
    ]);

    if ($validator->fails()) {
        return response()->json([
            "message" => "Error validating data",
            "errors" => $validator->errors(),
            "status" => 400
        ], 400);
    }

    // Obtener el usuario autenticado
    $user = $request->user();

    // Verificar si el usuario tiene el rol adecuado
    $project = Projects::find($projectId);
    if (!$project) {
        return response()->json([
            "message" => "Project not found",
            "status" => 404
        ], 404);
    }

    $isAuthorized = false;

    // Verificar si el usuario tiene el rol de 'admin' o 'project manager' dentro del proyecto
    foreach ($project->members as $member) {
        if ($member['user_id'] === $user->id && in_array($member['rol'], ['admin', 'project manager'])) {
            $isAuthorized = true;
            break;
        }
    }

    if (!$isAuthorized) {
        return response()->json([
            "message" => "Unauthorized. Only admins or project managers can add members.",
            "status" => 403
        ], 403);
    }

    // Añadir los nuevos miembros al proyecto
    $newMembers = [];
    foreach ($request->members as $member) {
        $newMembers[] = [
            'user_id' => $member['user_id'],
            'rol' => $member['rol']
        ];
    }

    // Combina los nuevos miembros con los existentes
    $updatedMembers = array_merge($project->members, $newMembers);
    $project->members = $updatedMembers;

    // Guardar los cambios en el proyecto
    if ($project->save()) {
        return response()->json([
            "message" => "Members added successfully",
            "project" => $project,
            "status" => 200
        ], 200);
    } else {
        return response()->json([
            "message" => "Error adding members to project",
            "status" => 500
        ], 500);
    }
}

public function removeMember(Request $request, $projectId, $userId)
{
    $project = Projects::find($projectId);
    if (!$project) {
        return response()->json(['message' => 'Project not found'], 404);
    }

    // Verifica que el usuario sea el propietario del proyecto o admin
    // Aquí deberías agregar la lógica de autorización si es necesario

    // Elimina el miembro del array de miembros
    $project->members = array_filter($project->members, function ($member) use ($userId) {
        return $member['user_id'] != $userId;
    });

    $project->save();

    return response()->json(['message' => 'Member removed successfully'], 200);
}

}

