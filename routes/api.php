<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\taskController;
use Illuminate\Support\Facades\Route;


Route::post('register', [AuthController::class,'register']);
Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])->name('verification.resend');
Route::post('login', [AuthController::class,'login']);
Route::post("password/email", [ForgotPasswordController::class, "sendResetLinkEmail"]);
Route::post('password/reset', [ResetPasswordController::class, 'reset']);
Route::post('/resend-verification', [AuthController::class, 'resendVerificationEmail'])
    ->middleware('auth:sanctum') // Asegúrate de que el usuario esté autenticado
    ->name('verification.resend');

Route::group(['middleware' => ["auth:sanctum", 'verified']],
function(){
    Route::get("profile",[AuthController::class,"profile"]);

    Route::get("logout",[AuthController::class,"logout"]);

    Route::get('getUserByEmail', [ProjectsController::class, 'getUserByEmail']);

    Route::get("getAllMyProjects",[ProjectsController::class,"getAllMyProjects"]);

    Route::get("getProject/{id}",[ProjectsController::class,"getProject"]);
    
    Route::get("getTasksByProject/{id}",[TaskController::class,"getTasksByProject"]);

    Route::get('project/{id}/members', [ProjectsController::class, 'getProjectMembers']);

    Route::get('tasks/{taskId}/assignedUsers', [TaskController::class, 'getAssignedUsers']);

    Route::get('projects/{projectId}/tasks/{taskId}', [TaskController::class, 'getTask']);
    
    Route::post('createProject', [ProjectsController::class,'createProject']);

    Route::post('addMembersToProject/{projectId}', [ProjectsController::class,'addMembersToProject']);

    Route::post('/tasks/{taskId}/assignUser', [TaskController::class, 'assignUserToTask']);

    Route::post('createTask/{id}', [TaskController::class,'createTask']);

    Route::put('updateProject/{id}', [ProjectsController::class,'updateProject']);

    Route::put('updateTask/{id}', [TaskController::class,'updateTask']);

    Route::delete('deleteProject/{id}', [ProjectsController::class,'deleteProject']);

    Route::delete('/project/{projectId}/member/{userId}', [ProjectsController::class, 'removeMember']);

    Route::delete('deleteTask/{id}', [TaskController::class,'deleteTask']);

    Route::delete('/tasks/{taskId}/unassign', [TaskController::class, 'unassignUserFromTask']);
    
});





// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

