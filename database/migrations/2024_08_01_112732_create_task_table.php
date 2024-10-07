<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('task')) {
            Schema::create('task', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('project')->onDelete('cascade');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Ajustado
                $table->string('title');
                $table->text('description');
                $table->boolean('complete')->default(false);
                $table->date('start_task_date')->nullable();
                $table->date('end_task_date')->nullable();
                $table->timestamps(); // Si usas timestamps
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task');
    }
};
