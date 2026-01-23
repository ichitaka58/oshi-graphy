<x-app-layout>
    <x-slot name="title">Oshi Graphy | ダッシュボード</x-slot>

    <x-slot name="header">
        <div class="flex items-center gap-1">
            <img src="{{ auth()->user()->icon_url }}" alt="アイコン画像" class="inline-block w-8 h-8 rounded-full object-cover border">
            <h2 class="font-semibold text-lg sm:text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                <span class="text-red-400">{{ auth()->user()->name }}</span>さんのダッシュボード
            </h2>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto space-y-6">
        <div class="bg-white dark:bg-gray-900 shadow rounded-2xl mx-2 my-6 p-4 motion-safe:animate-fade-up">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">🔔 最近の通知</h3>
                <form method="POST" action="{{ route('notifications.markAllRead') }}">
                    @csrf
                    <x-primary-button>すべて既読にする</x-primary-button>
                </form>
            </div>

            <ul class="divide-y mt-6">
                @forelse($notifications as $n)
                @php $data = $n->data; @endphp
                <li class="py-3 flex items-center gap-3">
                    @if(($data['type'] ?? '' ) === 'comment' || ($data['type'] ?? '' ) === 'reply')
                    <x-icons.chat-bubble-left size="size-5" class="mt-1 text-blue-600" />
                    @else
                    <x-icons.heart size="size-5" class="mt-1 text-rose-600" />
                    @endif
                    <div class="flex-1">
                        <a href="{{ route('notifications.go', $n->id) }}" class="{{ is_null($n->read_at) ? 'underline font-semibold text-gray-900 dark:text-gray-100' : '' }}">
                            {{ $data['message'] ?? '通知' }}
                        </a>
                        <div class="text-xs text-gray-500">{{ $n->created_at->diffForHumans() }}</div>
                    </div>
                    <div class="flex items-center">
                        <form method="POST" action="{{ route('notifications.destroy', $n->id) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs text-gray-500 underline">削除</button>
                        </form>
                    </div>
                </li>
                @empty
                <li class="py-6 text-gray-500">新しい通知はありません。</li>
                @endforelse
            </ul>
        </div>
    </div>

    @push('scripts')
    <script>
        window.addEventListener('pageshow', (e) => {
            // back forwardキャッシュから復元された場合のみリロード
            if (e.persisted) location.reload();
        });
    </script>
    @endpush
</x-app-layout>