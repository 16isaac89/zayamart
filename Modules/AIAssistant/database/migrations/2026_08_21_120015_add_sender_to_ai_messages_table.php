<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            // 'role' stays the provider-wire-format value (user/assistant/
            // tool) needed to replay history to an LLM. 'sender_type' is the
            // *display* participant (brief §8/§10) — customer/ai/human/
            // system — independent of role, since both AI and human replies
            // are role=assistant for LLM-history purposes but must render
            // differently to the vendor and customer.
            $table->string('sender_type')->default('ai')->after('role');
            $table->bigInteger('sender_id')->nullable()->after('sender_type'); // seller_id when sender_type=human
            $table->string('sender_name')->nullable()->after('sender_id'); // display-name snapshot at send time
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn(['sender_type', 'sender_id', 'sender_name']);
        });
    }
};
