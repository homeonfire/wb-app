<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teamsKey = 'store_id'; // Наше имя ключа

        // Добавляем store_id в таблицу ролей
        Schema::table('roles', function (Blueprint $table) use ($teamsKey) {
            if (!Schema::hasColumn('roles', $teamsKey)) {
                $table->unsignedBigInteger($teamsKey)->nullable()->after('id');
                $table->index($teamsKey);
            }
        });

        // Добавляем store_id в таблицу связей (кто какую роль имеет)
        Schema::table('model_has_roles', function (Blueprint $table) use ($teamsKey) {
            if (!Schema::hasColumn('model_has_roles', $teamsKey)) {
                $table->unsignedBigInteger($teamsKey)->nullable()->after('role_id');
                $table->index($teamsKey);
            }
        });
        
        // То же самое для прав (permissions), если понадобится
        Schema::table('model_has_permissions', function (Blueprint $table) use ($teamsKey) {
            if (!Schema::hasColumn('model_has_permissions', $teamsKey)) {
                $table->unsignedBigInteger($teamsKey)->nullable()->after('permission_id');
                $table->index($teamsKey);
            }
        });
    }

    public function down(): void
    {
        // Логика отката (удаление колонок)
        $teamsKey = 'store_id';
        Schema::table('roles', function (Blueprint $table) use ($teamsKey) {
            $table->dropColumn($teamsKey);
        });
        Schema::table('model_has_roles', function (Blueprint $table) use ($teamsKey) {
            $table->dropColumn($teamsKey);
        });
        Schema::table('model_has_permissions', function (Blueprint $table) use ($teamsKey) {
            $table->dropColumn($teamsKey);
        });
    }
};