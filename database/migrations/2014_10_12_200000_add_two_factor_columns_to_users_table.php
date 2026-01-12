<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Fortify;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // FactuCare legacy no usa 2FA y no queremos modificar la tabla `users`.
        return;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hacemos nada.
    }
};
