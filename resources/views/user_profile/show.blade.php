@php
$isFollowing = auth()->user()->isFollowing($user);
$followingsCount = $user->followings()->count();
$followersCount = $user->followers()->count();
$isBlocking = auth()->user()->isBlocking($user);
@endphp
<x-app-layout>
    <x-slot name="title">Oshi Graphy | {{ $user->name }}プロフィール</x-slot>

    <x-slot name="header">
        <h2 class="text-lg sm:text-2xl font-semibold dark:text-gray-300">{{ $user->name }}さんのプロフィール</h2>
    </x-slot>

    {{-- パンくず --}}
    <nav class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-3 sm:mt-5 flex justify-between items-center text-[11px] text-gray-600 dark:text-gray-300 sm:text-base no-print">
        <div>
            <a href="{{ route('public.diaries.user', $user) }}" class="underline">{{ $user->name }}さんのページ</a>
            <span class="mx-1">/</span>
            <span>プロフィール</span>
        </div>
        <div x-data @click="history.back()" class="underline cursor-pointer">戻る</div>
    </nav>

    <div class="max-w-5xl w-full mx-auto px-4 py-4 sm:py-6">
        <div class="max-w-3xl mx-auto border rounded-2xl px-4 sm:px-8 py-6 shadow bg-white dark:bg-gray-800 space-y-4 motion-safe:animate-fade-up">
            <div class="flex justify-between">
                <div class="flex items-center gap-6">
                    <img src="{{ $user->icon_url }}" alt="アイコン" class="w-32 h-32 rounded-full object-cover border">
                    <div>
                        <div class="text-2xl font-semibold dark:text-gray-300">{{ $user->name }}</div>
                        {{-- フォローボタン＆フォロー、フォロワー数 --}}
                        <x-follow-button :user="$user" :initialFollowing="$isFollowing" :followingsCount="$followingsCount" :followersCount="$followersCount" :isBlocking="$isBlocking" />
                    </div>
                </div>
                {{-- 右上の三点リーダアイコンメニュー --}}
                <div x-data="{ open : false }" class="relative">
                    <x-icons.ellipsis-horizontal-circle class="text-gray-300 hover:cursor-pointer" @click="open=!open" />
                    <div x-show="open" @click="open=false" class="absolute top-0 right-6 bg-gray-50 shadow p-4 w-48 space-y-2 rounded">
                        @if(auth()->user()->id !== $user->id)
                        <x-block-button :user="$user" :initialBlocking="$isBlocking" />
                        @else
                        <a href="{{ route('user.block.blocks') }}" class="inline-block">ブロックユーザー一覧</a>
                        <a href="{{ route('user.profile.edit') }}" class="inline-block">プロフィール編集</a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-brand-light dark:bg-brand-dark min-h-16 rounded-lg p-6 whitespace-pre-line">{{ $user->profile ?: '(未設定)'}}</div>
            <div class="text-lg text-gray-800 dark:text-gray-300">
                ⭐️公開日記数：{{ $user->public_diaries_count }}
            </div>
        </div>


    </div>

</x-app-layout>