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
        Schema::table('articles', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('color');
            $table->string('sub_type')->nullable()->after('brand');
            $table->string('material')->nullable()->after('sub_type');
            $table->string('gender')->nullable()->after('material');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['brand', 'sub_type', 'material', 'gender']);
        });
    }
};
