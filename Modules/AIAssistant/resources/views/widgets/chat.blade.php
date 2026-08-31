@php
    $__aiAgent = \Modules\AIAssistant\app\Models\AIAgent::with('settings')
        ->where('seller_id', $sellerId ?? 0)
        ->where('status', true)
        ->first();
@endphp

@if($__aiAgent && ($sellerId ?? 0) > 0)
    <div id="ai-assistant-widget"
         data-chat-url="{{ route('ai-assistant.chat', ['shop_slug' => $shopSlug]) }}"
         data-messages-url-template="{{ route('ai-assistant.messages', ['shop_slug' => $shopSlug, 'conversationId' => '__ID__']) }}"
         data-request-human-url-template="{{ route('ai-assistant.request-human', ['shop_slug' => $shopSlug, 'conversationId' => '__ID__']) }}"
         data-resume-ai-url-template="{{ route('ai-assistant.resume-ai', ['shop_slug' => $shopSlug, 'conversationId' => '__ID__']) }}">
        <button type="button" id="ai-assistant-toggle" aria-label="{{ translate('Chat_with_us') }}">
            @if($__aiAgent->bot_avatar)
                <img src="{{ getStorageImages(path: $__aiAgent->bot_avatar_full_url, type: 'backend-profile') }}" alt="">
            @else
                <i class="fi fi-rr-comment-alt"></i>
            @endif
        </button>

        <div id="ai-assistant-panel" class="d-none">
            <div id="ai-assistant-header">
                <div>
                    <div class="fw-bold">{{ $__aiAgent->displayName() }}</div>
                    @if($__aiAgent->short_description)
                        <div class="ai-assistant-subtitle">{{ $__aiAgent->short_description }}</div>
                    @endif
                </div>
                <button type="button" id="ai-assistant-close" aria-label="{{ translate('close') }}">&times;</button>
            </div>
            <div id="ai-assistant-messages">
                <div class="ai-assistant-msg ai-assistant-msg--bot">
                    {{ $__aiAgent->greeting ?: translate('Hi_How_can_I_help_you_today') }}
                </div>
            </div>
            <div id="ai-assistant-handoff-bar" class="d-none">
                {{ translate('Want_to_talk_to_a_person_instead') }}
                <button type="button" id="ai-assistant-request-human">{{ translate('Talk_to_a_human') }}</button>
            </div>
            <div id="ai-assistant-waiting-bar" class="d-none">
                {{ translate('Waiting_for_a_team_member') }}
                <button type="button" id="ai-assistant-resume-ai">{{ translate('Continue_with_AI_instead') }}</button>
            </div>
            <form id="ai-assistant-form" autocomplete="off">
                <input type="text" id="ai-assistant-input" maxlength="2000"
                       placeholder="{{ translate('Type_your_message') }}" required>
                <button type="submit" id="ai-assistant-send" aria-label="{{ translate('send') }}">
                    <i class="fi fi-rr-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>

    <style>
        {{-- The theme's WhatsApp button + scroll-to-top (.floating-btn-grp in
             style.css) has been moved to bottom-left so this widget can keep
             the bottom-right spot without covering it. --}}
        #ai-assistant-widget { position: fixed; right: 20px; bottom: 20px; z-index: 1050; font-family: inherit; }
        #ai-assistant-toggle {
            width: 56px; height: 56px; border-radius: 50%; border: none; overflow: hidden; padding: 0;
            background: var(--bs-primary, #2f5d50); color: #fff; font-size: 22px;
            box-shadow: 0 4px 14px rgba(0,0,0,.2); cursor: pointer;
        }
        #ai-assistant-toggle img { width: 100%; height: 100%; object-fit: cover; }
        #ai-assistant-panel {
            position: absolute; right: 0; bottom: 70px; width: 340px; max-width: calc(100vw - 40px);
            height: 480px; max-height: 72vh; background: #fff; border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.25); display: flex; flex-direction: column; overflow: hidden;
        }
        #ai-assistant-header {
            background: var(--bs-primary, #2f5d50); color: #fff; padding: 12px 14px;
            display: flex; align-items: flex-start; justify-content: space-between; font-size: 14px;
        }
        .ai-assistant-subtitle { font-size: 11px; opacity: .85; }
        #ai-assistant-header button { background: none; border: none; color: #fff; font-size: 20px; line-height: 1; cursor: pointer; }
        #ai-assistant-messages { flex: 1; overflow-y: auto; padding: 12px; background: #f6f7f9; }
        .ai-assistant-msg { max-width: 88%; margin-bottom: 8px; padding: 8px 12px; border-radius: 12px; font-size: 13px; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }
        .ai-assistant-msg--bot { background: #fff; border: 1px solid #e5e7eb; border-bottom-left-radius: 2px; }
        .ai-assistant-msg--human { background: #eaf3ff; border: 1px solid #cfe3ff; border-bottom-left-radius: 2px; }
        .ai-assistant-msg--user { background: var(--bs-primary, #2f5d50); color: #fff; margin-left: auto; border-bottom-right-radius: 2px; }
        .ai-assistant-msg--pending { opacity: .6; }
        .ai-assistant-sender-label { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #8a8f98; margin-bottom: 2px; }
        .ai-assistant-cards { display: flex; flex-direction: column; gap: 8px; max-width: 88%; margin-bottom: 8px; }
        .ai-assistant-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; font-size: 13px; }
        .ai-assistant-card strong { display: block; margin-bottom: 2px; }
        .ai-assistant-card .price { color: var(--bs-primary, #2f5d50); font-weight: 600; }
        .ai-assistant-whatsapp-btn {
            display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; padding: 7px 14px;
            background: #25D366; color: #fff; border-radius: 18px; font-size: 12px; font-weight: 600;
            text-decoration: none; text-align: center;
        }
        .ai-assistant-whatsapp-btn:hover { background: #1ebe5a; color: #fff; }
        #ai-assistant-handoff-bar, #ai-assistant-waiting-bar {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            padding: 8px 12px; background: #fff8e6; border-top: 1px solid #f0e4bd; font-size: 12px;
        }
        #ai-assistant-handoff-bar button, #ai-assistant-waiting-bar button {
            border: 1px solid var(--bs-primary, #2f5d50); background: #fff; color: var(--bs-primary, #2f5d50);
            border-radius: 14px; padding: 4px 10px; font-size: 12px; cursor: pointer; white-space: nowrap;
        }
        #ai-assistant-form { display: flex; gap: 6px; padding: 10px; border-top: 1px solid #e5e7eb; background: #fff; }
        #ai-assistant-input { flex: 1; border: 1px solid #d7dbe0; border-radius: 20px; padding: 8px 14px; font-size: 13px; outline: none; }
        #ai-assistant-send { width: 36px; height: 36px; border-radius: 50%; border: none; background: var(--bs-primary, #2f5d50); color: #fff; cursor: pointer; }
        #ai-assistant-send:disabled, #ai-assistant-input:disabled { opacity: .6; cursor: not-allowed; }
    </style>

    <script>
        (function () {
            const widget = document.getElementById('ai-assistant-widget');
            const toggleBtn = document.getElementById('ai-assistant-toggle');
            const closeBtn = document.getElementById('ai-assistant-close');
            const panel = document.getElementById('ai-assistant-panel');
            const messages = document.getElementById('ai-assistant-messages');
            const form = document.getElementById('ai-assistant-form');
            const input = document.getElementById('ai-assistant-input');
            const sendBtn = document.getElementById('ai-assistant-send');
            const handoffBar = document.getElementById('ai-assistant-handoff-bar');
            const requestHumanBtn = document.getElementById('ai-assistant-request-human');
            const waitingBar = document.getElementById('ai-assistant-waiting-bar');
            const resumeAiBtn = document.getElementById('ai-assistant-resume-ai');
            const chatUrl = widget.dataset.chatUrl;
            const csrfToken = document.querySelector('meta[name="_token"]')?.content;
            let conversationId = null;
            let pollTimer = null;
            let renderedMessageCount = 1; // the static greeting already shown

            function appendMessage(text, kind, pending) {
                const el = document.createElement('div');
                el.className = 'ai-assistant-msg ai-assistant-msg--' + kind + (pending ? ' ai-assistant-msg--pending' : '');
                el.textContent = text;
                messages.appendChild(el);
                messages.scrollTop = messages.scrollHeight;
                return el;
            }

            // Structured envelope rendering (brief §12) — 'data' always comes
            // from a Laravel-validated tool result, never AI-authored JSON.
            function appendStructured(type, data) {
                if (type === 'product' && data.name) {
                    renderCards([data]);
                } else if (type === 'product_list' && Array.isArray(data.products)) {
                    renderCards(data.products);
                } else if (type === 'confirmation' && data.order_ids) {
                    const el = document.createElement('div');
                    el.className = 'ai-assistant-card';
                    el.style.maxWidth = '88%';
                    el.style.marginBottom = '8px';
                    const total = document.createElement('strong');
                    total.textContent = '{{ translate('Order_confirmed') }} #' + data.order_ids.join(', #');
                    el.appendChild(total);
                    const amount = document.createElement('div');
                    amount.textContent = '{{ translate('Total') }}: ' + (data.order_amount ?? '');
                    el.appendChild(amount);
                    if (data.whatsapp_link) {
                        el.appendChild(renderWhatsAppButton(data.whatsapp_link, '{{ translate('Send_order_on_WhatsApp') }}'));
                    }
                    messages.appendChild(el);
                    messages.scrollTop = messages.scrollHeight;
                } else if (type === 'whatsapp_link' && data.whatsapp_link) {
                    const el = document.createElement('div');
                    el.className = 'ai-assistant-card';
                    el.style.maxWidth = '88%';
                    el.style.marginBottom = '8px';
                    el.appendChild(renderWhatsAppButton(data.whatsapp_link, '{{ translate('Chat_on_WhatsApp') }}'));
                    messages.appendChild(el);
                    messages.scrollTop = messages.scrollHeight;
                }
            }

            // Always a Laravel-built wa.me URL from the tool result, never
            // AI-authored text — safe to set as a real href.
            function renderWhatsAppButton(url, label) {
                const link = document.createElement('a');
                link.className = 'ai-assistant-whatsapp-btn';
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = label;
                return link;
            }

            function renderCards(products) {
                const wrap = document.createElement('div');
                wrap.className = 'ai-assistant-cards';
                products.slice(0, 5).forEach(function (p) {
                    const card = document.createElement('div');
                    card.className = 'ai-assistant-card';
                    const name = document.createElement('strong');
                    name.textContent = p.name ?? '';
                    card.appendChild(name);
                    if (p.unit_price !== undefined) {
                        const price = document.createElement('span');
                        price.className = 'price';
                        price.textContent = p.unit_price;
                        card.appendChild(price);
                    }
                    wrap.appendChild(card);
                });
                messages.appendChild(wrap);
                messages.scrollTop = messages.scrollHeight;
            }

            function updateHandoffUi(supportStatus) {
                if (supportStatus === 'human_active' || supportStatus === 'human_requested') {
                    handoffBar.classList.add('d-none'); // already requested/active — no need to ask again
                    waitingBar.classList.remove('d-none');
                    startPolling();
                } else {
                    handoffBar.classList.remove('d-none');
                    waitingBar.classList.add('d-none');
                    stopPolling();
                }
            }

            function stopPolling() {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }

            function startPolling() {
                if (pollTimer || !conversationId) return;
                pollTimer = setInterval(function () {
                    fetch(widget.dataset.messagesUrlTemplate.replace('__ID__', conversationId), {credentials: 'same-origin'})
                        .then(r => r.json())
                        .then(function (data) {
                            if (data.messages.length > renderedMessageCount) {
                                data.messages.slice(renderedMessageCount).forEach(function (m) {
                                    if (m.sender_type === 'human') {
                                        appendMessage(m.content, 'human');
                                    }
                                    // customer/ai messages are already rendered
                                    // locally as they're sent/received — only
                                    // human replies arrive purely via polling.
                                });
                                renderedMessageCount = data.messages.length;
                            }
                        });
                }, 6000);
            }

            toggleBtn.addEventListener('click', function () {
                panel.classList.toggle('d-none');
                if (!panel.classList.contains('d-none')) {
                    input.focus();
                }
            });
            closeBtn.addEventListener('click', function () {
                panel.classList.add('d-none');
            });

            requestHumanBtn.addEventListener('click', function () {
                if (!conversationId) return;
                fetch(widget.dataset.requestHumanUrlTemplate.replace('__ID__', conversationId), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'X-CSRF-TOKEN': csrfToken},
                }).then(r => r.json()).then(function (data) {
                    updateHandoffUi(data.support_status);
                    appendMessage('{{ translate('A_team_member_will_join_this_conversation_shortly') }}', 'bot');
                });
            });

            resumeAiBtn.addEventListener('click', function () {
                if (!conversationId) return;
                fetch(widget.dataset.resumeAiUrlTemplate.replace('__ID__', conversationId), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'X-CSRF-TOKEN': csrfToken},
                }).then(r => r.json()).then(function (data) {
                    updateHandoffUi(data.support_status);
                    appendMessage('{{ translate('You_are_now_chatting_with_the_AI_assistant_again') }}', 'bot');
                });
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const text = input.value.trim();
                if (!text) {
                    return;
                }

                appendMessage(text, 'user');
                renderedMessageCount++;
                input.value = '';
                input.disabled = true;
                sendBtn.disabled = true;
                const pendingEl = appendMessage('…', 'bot', true);

                fetch(chatUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ message: text }),
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('request_failed');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        conversationId = data.conversation_id ?? conversationId;
                        pendingEl.remove();
                        if (data.reply) {
                            appendMessage(data.reply, 'bot');
                            renderedMessageCount++;
                        }
                        if (data.type && data.type !== 'text' && data.type !== 'handoff' && data.type !== 'error') {
                            appendStructured(data.type, data.data || {});
                        }
                        updateHandoffUi(data.support_status);
                    })
                    .catch(function () {
                        pendingEl.remove();
                        appendMessage('{{ translate('Sorry_something_went_wrong_Please_try_again') }}', 'bot');
                    })
                    .finally(function () {
                        input.disabled = false;
                        sendBtn.disabled = false;
                        input.focus();
                    });
            });
        })();
    </script>
@endif
