{{-- AI travel assistant (SRS 6.5, FR-24). Talks only to our backend at /api/chat. --}}
<div x-data="chatbot({ url: @js(route('chat.send')) })" @keydown.escape.window="open = false" class="fixed end-4 bottom-4 z-50 sm:end-6 sm:bottom-6">
    <div x-show="open" x-cloak x-transition.origin.bottom.right id="chat-panel" role="dialog" aria-labelledby="chat-panel-title"
         class="fixed inset-0 flex flex-col overflow-hidden bg-white shadow-2xl sm:static sm:inset-auto sm:mb-3 sm:h-[34rem] sm:w-96 sm:rounded-2xl sm:ring-1 sm:ring-slate-200">
        <div class="flex items-center justify-between bg-primary-700 px-4 py-3 text-white">
            <p id="chat-panel-title" class="flex items-center gap-2 font-semibold">
                <x-icon name="sparkles" class="size-5 text-accent-300" /> Travel assistant
            </p>
            <div class="flex items-center gap-1">
                <button type="button" @click="reset()" class="rounded-md px-2 py-1 text-xs text-primary-100 hover:bg-primary-800" x-show="messages.length">New chat</button>
                <button type="button" @click="open = false" class="rounded-md p-1 hover:bg-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                    <span class="sr-only">Close chat</span>
                    <x-icon name="x" class="size-5" />
                </button>
            </div>
        </div>

        <div x-ref="log" class="flex-1 space-y-3 overflow-y-auto p-4 text-sm" aria-live="polite">
            <p class="max-w-[85%] rounded-2xl rounded-tl-sm bg-slate-100 px-3 py-2 text-slate-700">
                Ayubowan! I can suggest places, seasons and routes in Sri Lanka, and help you use the Trip Builder.
            </p>

            <template x-for="(message, i) in messages" :key="i">
                <div :class="message.role === 'user' ? 'flex justify-end' : ''">
                    <div class="max-w-[85%] whitespace-pre-line rounded-2xl px-3 py-2"
                         :class="message.role === 'user' ? 'rounded-tr-sm bg-primary-700 text-white' : 'rounded-tl-sm bg-slate-100 text-slate-700'"
                         x-text="message.content"></div>
                    <div x-show="message.links && message.links.length" class="mt-2 flex flex-wrap gap-2">
                        <template x-for="link in message.links || []" :key="link.url">
                            <a :href="link.url" class="rounded-full bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-800 ring-1 ring-primary-200 hover:bg-primary-100" x-text="link.label"></a>
                        </template>
                    </div>
                </div>
            </template>

            <div x-show="busy" class="flex w-16 gap-1 rounded-2xl rounded-tl-sm bg-slate-100 px-3 py-3" aria-label="Assistant is typing">
                <span class="size-2 animate-bounce rounded-full bg-slate-400"></span>
                <span class="size-2 animate-bounce rounded-full bg-slate-400 [animation-delay:150ms]"></span>
                <span class="size-2 animate-bounce rounded-full bg-slate-400 [animation-delay:300ms]"></span>
            </div>

            <div x-show="! messages.length" class="flex flex-wrap gap-2 pt-1">
                <template x-for="question in suggestions" :key="question">
                    <button type="button" @click="send(question)" class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-primary-800 ring-1 ring-primary-200 hover:bg-primary-50" x-text="question"></button>
                </template>
            </div>
        </div>

        <form @submit.prevent="send()" class="flex gap-2 border-t border-slate-200 p-3">
            <label for="chat-input" class="sr-only">Your question</label>
            <input id="chat-input" x-ref="input" x-model="draft" maxlength="500" autocomplete="off" placeholder="Ask about places, seasons, routes…" class="form-control flex-1">
            <button type="submit" class="btn btn-primary px-3" :disabled="busy || draft.trim() === ''"><span class="sr-only">Send</span><x-icon name="arrow-right" class="size-5" /></button>
        </form>
        <p class="px-3 pb-2 text-[11px] text-slate-400">AI answers can be wrong; prices are confirmed by an agent.</p>
    </div>

    <div class="flex justify-end">
        <button type="button" @click="toggle()" :aria-expanded="open.toString()" aria-controls="chat-panel" :class="open ? 'hidden sm:grid' : 'grid'"
                class="size-14 place-items-center rounded-full bg-primary-700 text-white shadow-lg shadow-primary-900/30 transition hover:bg-primary-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300">
            <span class="sr-only" x-text="open ? 'Close travel assistant' : 'Open travel assistant'">Open travel assistant</span>
            <x-icon name="chat" class="size-7" x-show="! open" />
            <x-icon name="x" class="size-7" x-show="open" x-cloak />
        </button>
    </div>
</div>
