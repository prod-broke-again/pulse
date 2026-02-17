<x-filament-panels::page>
    <div class="fi-section overflow-hidden rounded-xl">
        {{-- ВЕРХНЯЯ ПАНЕЛЬ: Фильтры и Очереди --}}
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="flex bg-gray-100 dark:bg-white/5 p-1 rounded-lg">
                <button
                    wire:click="$set('activeTab', 'my')"
                    type="button"
                    class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'my' ? 'bg-white dark:bg-gray-800 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    Мои ({{ $myChatsCount }})
                </button>
                <button
                    wire:click="$set('activeTab', 'unassigned')"
                    type="button"
                    class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'unassigned' ? 'bg-white dark:bg-gray-800 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    Нераспределённые ({{ $unassignedChatsCount }})
                </button>
                @if($isAdmin)
                    <button
                        wire:click="$set('activeTab', 'all')"
                        type="button"
                        class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'all' ? 'bg-white dark:bg-gray-800 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                    >
                        Все ({{ $allChatsCount }})
                    </button>
                @endif
            </div>

            <div class="flex flex-col items-end gap-1">
                @if(count($moderatorSourceNames) > 0)
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <span class="text-xs text-gray-400 dark:text-gray-500">Твои проекты:</span>
                        @foreach($moderatorSourceNames as $sourceName)
                            <x-filament::badge color="gray" size="sm">{{ $sourceName }}</x-filament::badge>
                        @endforeach
                    </div>
                @endif
                @if(count($moderatorDepartmentNames) > 0)
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <span class="text-xs text-gray-400 dark:text-gray-500">Твои отделы:</span>
                        @foreach($moderatorDepartmentNames as $deptName)
                            <x-filament::badge color="info" size="sm">{{ $deptName }}</x-filament::badge>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="flex h-[calc(100vh-16rem)] min-h-[500px] bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl overflow-hidden shadow-sm">

            {{-- ЛЕВАЯ ПАНЕЛЬ: Список с группировкой по источникам --}}
            <div class="w-80 shrink-0 border-r border-gray-200 dark:border-white/10 flex flex-col">
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-white/5">
                    @forelse($groupedChats as $sourceName => $sourceChats)
                        <div class="bg-gray-50/80 dark:bg-white/5 px-4 py-2">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ $sourceName }}</span>
                        </div>

                        @foreach($sourceChats as $chat)
                            @php
                                $isUrgent = $chat->isUrgent();
                                $chatBg = $selectedChatId === $chat->id
                                    ? 'bg-primary-50 dark:bg-primary-500/10 border-l-4 border-primary-500'
                                    : ($isUrgent ? 'bg-red-50/80 dark:bg-red-500/10 border-l-4 border-red-500' : '');
                            @endphp
                            <button
                                wire:click="selectChat({{ $chat->id }})"
                                type="button"
                                class="w-full text-left p-4 transition-all hover:bg-gray-50 dark:hover:bg-white/5 {{ $chatBg }}"
                            >
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-sm font-bold dark:text-white truncate">
                                        {{ $chat->user_metadata['name'] ?? 'Гость ' . Str::limit($chat->external_user_id, 8) }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0 ml-2">
                                        {{ $chat->updated_at?->diffForHumans(short: true) ?? '-' }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1 mb-2">
                                    {{ $chat->latestMessage?->text ?? 'Нет сообщений' }}
                                </div>
                                {{-- Метка департамента --}}
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400 font-bold uppercase">
                                        {{ $chat->department?->name ?? 'Общее' }}
                                    </span>
                                    @if($chat->status === 'new')
                                        <x-filament::badge size="xs" color="danger">новый</x-filament::badge>
                                    @endif
                                    @if($isUrgent)
                                        <span class="text-[9px] text-red-600 dark:text-red-400 font-bold" title="Без ответа более 5 минут">!</span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    @empty
                        <div class="p-8 text-center">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-2" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                @if($activeTab === 'my')
                                    Нет чатов, взятых на себя
                                @elseif($activeTab === 'unassigned')
                                    Нет нераспределённых чатов
                                @else
                                    Чатов пока нет
                                @endif
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- ПРАВАЯ ПАНЕЛЬ: Чат --}}
            <div class="flex-1 flex flex-col bg-gray-50/30 dark:bg-black/10">
                @if($selectedChat)
                    {{-- Хедер с контекстом пользователя --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <x-filament::avatar
                                src="https://ui-avatars.com/api/?name={{ urlencode($selectedChat->user_metadata['name'] ?? 'U') }}&color=FFFFFF&background=0ea5e9"
                                size="lg"
                            />
                            <div>
                                <h2 class="text-md font-bold dark:text-white">{{ $selectedChat->user_metadata['name'] ?? 'Клиент' }}</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $selectedChat->source?->name ?? 'Без источника' }}
                                    | ID: {{ $selectedChat->external_user_id }}
                                    @if($selectedChat->user_metadata['email'] ?? null)
                                        | {{ $selectedChat->user_metadata['email'] }}
                                    @else
                                        | почта не указана
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <x-filament::button
                                color="gray"
                                size="sm"
                                icon="heroicon-m-user-plus"
                                wire:click="assignToMe"
                            >
                                На себя
                            </x-filament::button>
                            <x-filament::button
                                color="success"
                                size="sm"
                                icon="heroicon-m-check-circle"
                                wire:click="closeChat"
                            >
                                Решить
                            </x-filament::button>
                        </div>
                    </div>

                    {{-- Сообщения --}}
                    <div
                        class="flex-1 overflow-y-auto p-6 flex flex-col gap-4 scroll-smooth"
                        id="chat-messages-container"
                    >
                        @foreach($messages as $msg)
                            @php $isMod = $msg['senderType'] === 'moderator'; @endphp
                            <div class="flex {{ $isMod ? 'justify-end' : 'justify-start' }}">
                                <div class="flex flex-col {{ $isMod ? 'items-end' : 'items-start' }} max-w-[75%]">
                                    <div class="px-4 py-2 rounded-2xl text-sm shadow-sm {{ $isMod ? 'bg-primary-600 text-white rounded-br-none' : 'bg-white dark:bg-gray-800 dark:text-white border border-gray-200 dark:border-white/5 rounded-bl-none' }}">
                                        {!! nl2br(e($msg['text'])) !!}
                                        @if(isset($msg['payload']['image_url']))
                                            <div class="mt-2">
                                                <img src="{{ $msg['payload']['image_url'] }}" alt="" class="rounded-lg max-h-60 cursor-pointer" />
                                            </div>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 px-1">
                                        {{ $isMod ? 'Вы' : 'Клиент' }} · {{ isset($msg['created_at']) && $msg['created_at'] ? \Carbon\Carbon::parse($msg['created_at'])->format('H:i') : '-' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Поле ввода --}}
                    <div class="p-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-white/10">
                        <form wire:submit="sendMessage" class="flex items-end gap-3">
                            <div class="flex-1">
                                <textarea
                                    wire:model="newMessageText"
                                    placeholder="Напишите ответ..."
                                    rows="1"
                                    class="block w-full rounded-xl border-none bg-gray-100 px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white"
                                    @keydown.enter.prevent="$wire.sendMessage()"
                                ></textarea>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::icon-button icon="heroicon-o-paper-clip" color="gray" size="lg" />
                                <x-filament::icon-button
                                    type="submit"
                                    icon="heroicon-s-paper-airplane"
                                    color="primary"
                                    size="lg"
                                    class="rounded-full shadow-lg"
                                />
                            </div>
                        </form>
                    </div>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center bg-gray-50/50 dark:bg-black/20">
                        <div class="p-8 rounded-full bg-white dark:bg-gray-800 shadow-xl mb-4">
                            <x-filament::icon icon="heroicon-o-sparkles" class="h-12 w-12 text-primary-500 dark:text-primary-400" />
                        </div>
                        <h3 class="text-lg font-bold dark:text-white">Выберите чат</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Сообщения из твоих проектов</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>

<script>
    (function initPulseChatRealtime() {
        const userId = @js(auth()->id());
        const selectedChatId = @js($selectedChatId);

        if (!userId || !window.Echo || !window.Livewire) {
            return;
        }

        if (!window.__pulseChatRealtime) {
            window.__pulseChatRealtime = {
                currentChatId: null,
                moderatorChannel: null,
                chatChannel: null,
            };
        }

        const state = window.__pulseChatRealtime;

        if (!state.moderatorChannel) {
            state.moderatorChannel = window.Echo.private(`moderator.${userId}`)
                .listen('ChatAssigned', (event) => {
                    window.Livewire.dispatch('chat-realtime-refresh', { chatId: event.chatId });
                })
                .listen('.App\\Events\\ChatAssigned', (event) => {
                    window.Livewire.dispatch('chat-realtime-refresh', { chatId: event.chatId });
                });
        }

        if (state.currentChatId && state.currentChatId !== selectedChatId) {
            window.Echo.leave(`private-chat.${state.currentChatId}`);
            state.chatChannel = null;
            state.currentChatId = null;
        }

        if (selectedChatId && state.currentChatId !== selectedChatId) {
            state.currentChatId = selectedChatId;
            state.chatChannel = window.Echo.private(`chat.${selectedChatId}`)
                .listen('NewChatMessage', (event) => {
                    window.Livewire.dispatch('chat-realtime-refresh', { chatId: event.chatId });
                })
                .listen('.App\\Events\\NewChatMessage', (event) => {
                    window.Livewire.dispatch('chat-realtime-refresh', { chatId: event.chatId });
                })
                .listen('ChatAssigned', (event) => {
                    window.Livewire.dispatch('chat-realtime-refresh', { chatId: event.chatId });
                })
                .listen('.App\\Events\\ChatAssigned', (event) => {
                    window.Livewire.dispatch('chat-realtime-refresh', { chatId: event.chatId });
                });
        }
    })();
</script>
