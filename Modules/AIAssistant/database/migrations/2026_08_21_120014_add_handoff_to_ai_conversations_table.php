<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            // Live human-handoff state for THIS conversation (brief §9/§36).
            // Deliberately distinct from the existing 'mode' column
            // (shopping/checkout — an orthogonal, AI-checkout-flow concept)
            // and 'status' (active/awaiting_confirmation/confirmed — the
            // checkout state machine). Conflating either with handoff state
            // would make both harder to reason about.
            $table->string('support_status')->default('active')->after('status');
            // Single-agent-per-vendor in this release (no vendor staff/
            // sub-account system exists in the base app — see architecture
            // doc Part III assessment) — always the conversation's own
            // seller_id when set, but kept as an explicit column so
            // multi-agent support has somewhere to go later without a
            // schema change.
            $table->bigInteger('human_agent_seller_id')->nullable()->after('support_status');
            $table->timestamp('human_requested_at')->nullable()->after('human_agent_seller_id');
            $table->timestamp('human_taken_over_at')->nullable()->after('human_requested_at');
            $table->timestamp('human_returned_at')->nullable()->after('human_taken_over_at');

            $table->index(['seller_id', 'support_status']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'support_status', 'human_agent_seller_id',
                'human_requested_at', 'human_taken_over_at', 'human_returned_at',
            ]);
        });
    }
};
