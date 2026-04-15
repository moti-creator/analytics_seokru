<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('reports', function (Blueprint $t) {
            $t->string('type')->default('weekly')->after('connection_id');
            $t->string('title')->nullable()->after('type');
        });
    }
    public function down(): void {
        Schema::table('reports', fn(Blueprint $t) => $t->dropColumn(['type','title']));
    }
};
