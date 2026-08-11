<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_info_factura', function (Blueprint $table) {
            if (!Schema::hasColumn('users_info_factura', 'forzar_comentario_pdf')) {
                $table->boolean('forzar_comentario_pdf')->default(false);
            }

            if (!Schema::hasColumn('users_info_factura', 'comentario_forzado_pdf')) {
                $table->text('comentario_forzado_pdf')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users_info_factura', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('users_info_factura', 'forzar_comentario_pdf')) {
                $columns[] = 'forzar_comentario_pdf';
            }

            if (Schema::hasColumn('users_info_factura', 'comentario_forzado_pdf')) {
                $columns[] = 'comentario_forzado_pdf';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
