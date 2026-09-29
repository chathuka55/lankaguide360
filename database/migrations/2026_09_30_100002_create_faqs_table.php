<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frequently asked questions: shown on the Contact page and used by the chatbot
     * as retrieval context and offline fallback (SRS 6.5).
     */
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('question', 255);
            $table->text('answer');
            $table->string('keywords', 255)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->fullText(['question', 'answer', 'keywords']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
