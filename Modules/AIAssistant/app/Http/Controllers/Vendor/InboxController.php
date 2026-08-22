<?php

namespace Modules\AIAssistant\app\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AIAssistant\app\Models\AIConversation;
use Modules\AIAssistant\app\Models\AIMessage;
use Modules\AIAssistant\app\Services\HandoffService;

/**
 * The vendor's conversation inbox — brief §35. One conversation system, not
 * a separate AI/human table (brief §8) — this just filters/updates the same
 * ai_conversations rows the customer-facing chat writes to.
 */
class InboxController extends Controller
{
    public function __construct(private readonly HandoffService $handoffService)
    {
    }

    public function index(Request $request): View
    {
        $sellerId = auth('seller')->id();
        $filter = $request->input('filter', 'all');

        $query = AIConversation::with(['customer', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->where('seller_id', $sellerId)
            ->orderByDesc('updated_at');

        match ($filter) {
            'ai' => $query->where('support_status', AIConversation::SUPPORT_ACTIVE),
            'human' => $query->where('support_status', AIConversation::SUPPORT_HUMAN_ACTIVE),
            'needs_attention' => $query->where('support_status', AIConversation::SUPPORT_HUMAN_REQUESTED),
            default => null,
        };

        $conversations = $query->paginate(20)->withQueryString();

        // "Unread" proxy: the customer sent the last message and no human
        // has replied since — there's no read/unread column, so this is
        // derived rather than stored (brief §36: "do not create unnecessary
        // statuses").
        if ($filter === 'unread') {
            $conversations->setCollection(
                $conversations->getCollection()->filter(
                    fn (AIConversation $c) => $c->messages->first()?->sender_type === AIMessage::SENDER_CUSTOMER
                )->values()
            );
        }

        return view('aiassistant::vendor.inbox.index', compact('conversations', 'filter'));
    }

    public function show(int $conversationId): View
    {
        $conversation = $this->ownedConversation($conversationId);

        return view('aiassistant::vendor.inbox.show', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->where('sender_type', '!=', AIMessage::SENDER_SYSTEM)->get(),
        ]);
    }

    public function takeOver(int $conversationId): RedirectResponse
    {
        $conversation = $this->ownedConversation($conversationId);
        $this->handoffService->takeOver($conversation, auth('seller')->id());

        return back()->with('success', translate('You_are_now_handling_this_conversation'));
    }

    public function returnToAi(int $conversationId): RedirectResponse
    {
        $conversation = $this->ownedConversation($conversationId);
        $this->handoffService->returnToAi($conversation, auth('seller')->id());

        return back()->with('success', translate('Conversation_returned_to_AI'));
    }

    public function reply(Request $request, int $conversationId): RedirectResponse
    {
        $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $conversation = $this->ownedConversation($conversationId);
        $seller = auth('seller')->user();

        AIMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => AIMessage::ROLE_ASSISTANT, // replayed to the LLM as an assistant turn if the conversation later returns to AI
            'content' => $request->input('message'),
            'sender_type' => AIMessage::SENDER_HUMAN,
            'sender_id' => $seller->id,
            'sender_name' => trim($seller->f_name . ' ' . $seller->l_name),
        ]);

        $conversation->touch();

        return back();
    }

    public function poll(int $conversationId): JsonResponse
    {
        $conversation = $this->ownedConversation($conversationId);

        return response()->json([
            'support_status' => $conversation->support_status,
            'messages' => $conversation->messages()
                ->where('sender_type', '!=', AIMessage::SENDER_SYSTEM)
                ->get()
                ->map(fn (AIMessage $m) => [
                    'id' => $m->id,
                    'sender_type' => $m->sender_type,
                    'sender_name' => $m->sender_name,
                    'content' => $m->content,
                    'created_at' => $m->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Never trust a conversation_id supplied by the URL alone — always
     * re-scope to the authenticated seller (brief §17/§34/§42).
     */
    private function ownedConversation(int $conversationId): AIConversation
    {
        return AIConversation::where('id', $conversationId)
            ->where('seller_id', auth('seller')->id())
            ->firstOrFail();
    }
}
