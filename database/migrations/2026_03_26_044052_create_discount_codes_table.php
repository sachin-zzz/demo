<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->unsignedTinyInteger('discount_percentage');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
