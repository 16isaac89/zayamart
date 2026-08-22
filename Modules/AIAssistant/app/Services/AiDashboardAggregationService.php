<?php

namespace Modules\AIAssistant\app\Services;

use App\Models\Order;
use App\Models\WhatsAppNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIConversation;
use Modules\AIAssistant\app\Models\AiUsageRecord;

/**
 * Every number here is a real database aggregation over actual rows — no
 * seeded/fake data (brief §43), no loading full tables into PHP (brief
 * §39). Platform cost and vendor-owned usage are computed and returned
 * separately everywhere — never summed together (brief §28/§31).
 */
class AiDashboardAggregationService
{
    public function vendorSummary(int $sellerId, Carbon $from, Carbon $to): array
    {
        $conversations = AIConversation::where('seller_id', $sellerId)->whereBetween('created_at', [$from, $to]);
        $conversationCount = (clone $conversations)->count();

        $confirmedConversations = (clone $conversations)->whereNotNull('confirmed_order_group_id');
        $aiOrderCount = (clone $confirmedConversations)->count();

        $averageOrderValue = Order::where('seller_id', $sellerId)
            ->whereIn('order_group_id', (clone $confirmedConversations)->pluck('confirmed_order_group_id'))
            ->avg('order_amount');

        $usage = AiUsageRecord::where('seller_id', $sellerId)->whereBetween('created_at', [$from, $to]);

        return [
            'conversations' => $conversationCount,
            'active_conversations' => (clone $conversations)->whereIn('support_status', [AIConversation::SUPPORT_ACTIVE, AIConversation::SUPPORT_HUMAN_ACTIVE])->count(),
            'ai_orders' => $aiOrderCount,
            'conversion_rate' => $conversationCount > 0 ? round(($aiOrderCount / $conversationCount) * 100, 1) : 0.0,
            'average_order_value' => round((float)$averageOrderValue, 2),
            'total_tokens' => (int)((clone $usage)->sum('input_tokens') + (clone $usage)->sum('output_tokens')),
            'estimated_cost_platform_billed' => round((float)(clone $usage)->where('billing_mode', '!=', 'vendor_owned')->sum('estimated_cost'), 4),
            'estimated_cost_vendor_owned' => round((float)(clone $usage)->where('billing_mode', 'vendor_owned')->sum('estimated_cost'), 4),
            'human_handoffs' => (clone $conversations)->whereNotNull('human_requested_at')->count(),
            'whatsapp_sent' => WhatsAppNotification::where('seller_id', $sellerId)->whereBetween('created_at', [$from, $to])->where('status', WhatsAppNotification::STATUS_SENT)->count(),
            'whatsapp_failed' => WhatsAppNotification::where('seller_id', $sellerId)->whereBetween('created_at', [$from, $to])->where('status', WhatsAppNotification::STATUS_FAILED)->count(),
        ];
    }

    /**
     * @return array{date: string, count: int}[]
     */
    public function conversationsPerDay(int $sellerId, Carbon $from, Carbon $to): array
    {
        return AIConversation::where('seller_id', $sellerId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => (int)$row->count])
            ->toArray();
    }

    public function handlingModeSplit(int $sellerId, Carbon $from, Carbon $to): array
    {
        $rows = AIConversation::where('seller_id', $sellerId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('support_status, COUNT(*) as count')
            ->groupBy('support_status')
            ->pluck('count', 'support_status');

        return [
            'ai' => (int)($rows[AIConversation::SUPPORT_ACTIVE] ?? 0),
            'human' => (int)($rows[AIConversation::SUPPORT_HUMAN_ACTIVE] ?? 0) + (int)($rows[AIConversation::SUPPORT_HUMAN_REQUESTED] ?? 0),
        ];
    }

    public function platformSummary(Carbon $from, Carbon $to): array
    {
        $conversations = AIConversation::whereBetween('created_at', [$from, $to]);
        $conversationCount = (clone $conversations)->count();
        $aiOrderCount = (clone $conversations)->whereNotNull('confirmed_order_group_id')->count();

        $usage = AiUsageRecord::whereBetween('created_at', [$from, $to]);

        return [
            'total_vendors' => AIAgent::distinct('seller_id')->count('seller_id'),
            'active_ai_assistants' => AIAgent::where('status', true)->count(),
            'ai_conversations' => $conversationCount,
            'ai_orders' => $aiOrderCount,
            'conversion_rate' => $conversationCount > 0 ? round(($aiOrderCount / $conversationCount) * 100, 1) : 0.0,
            'total_ai_usage_tokens' => (int)((clone $usage)->sum('input_tokens') + (clone $usage)->sum('output_tokens')),
            // Platform cost is ONLY platform_default/platform_managed usage
            // — vendor_owned usage is tracked but never attributed to the
            // platform's bill (brief §28/§31).
            'platform_ai_cost' => round((float)(clone $usage)->where('billing_mode', '!=', 'vendor_owned')->sum('estimated_cost'), 4),
            'vendor_owned_usage_tokens' => (int)(clone $usage)->where('billing_mode', 'vendor_owned')->get(['input_tokens', 'output_tokens'])->sum(fn ($r) => $r->input_tokens + $r->output_tokens),
            'whatsapp_notifications' => WhatsAppNotification::whereBetween('created_at', [$from, $to])->count(),
            'whatsapp_failed' => WhatsAppNotification::whereBetween('created_at', [$from, $to])->where('status', WhatsAppNotification::STATUS_FAILED)->count(),
            'failed_ai_jobs' => $this->failedJobsCount(['ProcessKnowledgeDocumentJob', 'RecordAIUsageJob']),
            'failed_whatsapp_jobs' => $this->failedJobsCount(['WhatsAppOrderNotificationListener']),
        ];
    }

    public function topVendorsByAiOrders(Carbon $from, Carbon $to, int $limit = 5): array
    {
        return AIConversation::whereBetween('created_at', [$from, $to])
            ->whereNotNull('confirmed_order_group_id')
            ->join('sellers', 'sellers.id', '=', 'ai_conversations.seller_id')
            ->selectRaw('ai_conversations.seller_id, sellers.f_name, sellers.l_name, COUNT(*) as ai_orders')
            ->groupBy('ai_conversations.seller_id', 'sellers.f_name', 'sellers.l_name')
            ->orderByDesc('ai_orders')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['seller_id' => $row->seller_id, 'name' => trim($row->f_name . ' ' . $row->l_name), 'ai_orders' => (int)$row->ai_orders])
            ->toArray();
    }

    private function failedJobsCount(array $payloadContains): int
    {
        if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            return 0;
        }

        $query = DB::table('failed_jobs');
        $query->where(function ($q) use ($payloadContains) {
            foreach ($payloadContains as $needle) {
                $q->orWhere('payload', 'like', "%{$needle}%");
            }
        });

        return $query->count();
    }
}
