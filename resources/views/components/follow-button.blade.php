@props([
'user',
'initialFollowing' => false,
'followingsCount' => 0,
'followersCount' => 0,
'isBlocking',
])

@php
$followUrl = route('users.follow.store', $user);
$unfollowUrl = route('users.follow.destroy', $user);
@endphp

<div
  x-data="{
    following: @js((bool)$initialFollowing),
    followingsCount: @js((int)$followingsCount),
    followersCount: @js((int)$followersCount),
    busy: false,
    async toggle() {
      if(this.busy) return;
      this.busy = true;

      const url = this.following ? @js($unfollowUrl) : @js($followUrl);
      const method = this.following ? 'DELETE' : 'POST';

      try {
        const res = await fetch(url,{
          method,
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
          },
          credentials: 'same-origin',
        });
        if(!res.ok) throw new Error('Request failed');
        const json = await res.json();
        this.following = !!json.following;
        this.followingsCount = Number(json.followings_count) ?? Number($this.followingsCount);
        this.followersCount = Number(json.followers_count) ?? Number($this.followersCount);
        $dispatch('toast',{
          message: json.message,
          type: json.status_type
        });
      } catch(e) {
          console.error(e);
          $dispatch('toast', {
            message: 'ネットワークエラーです。時間をおいて再試行してください。',
            type: 'error'
          });
      } finally {
          this.busy = false;
      }
    }
  }">
  {{-- 自分以外のprofileかつブロックしてないユーザーでフォローボタンを表示 --}}
  @if((auth()->user()->id !== $user->id) && !$isBlocking)
  <button
    type="button"
    x-data="{ hover : false }"
    @mouseover="hover = true"
    @mouseleave="hover = false"
    @click.stop="toggle"
    :disabled="busy"
    :class="following 
      ? (hover 
        ? 'bg-red-200 text-red-500 dark:bg-red-500 dark:text-red-100' 
        : 'bg-gray-200 dark:bg-gray-700' )
      : 'bg-brand hover:bg-brand-dark dark:bg-brand-dark dark:hover:bg-brand dark:hover:text-gray-500'"
    class="font-semibold rounded-xl px-4 py-1 shadow-md focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
  >
    <template x-if="following">
      <span x-text="hover ? 'フォロー解除' : 'フォロー中'"></span>
    </template>
    <template x-if="!following"><span>フォロー</span></template>
  </button>
  @endif
  <div class="text-xs mt-2 flex flex-col sm:flex-row gap-1 sm:gap-2">
    @if(auth()->user()->id === $user->id)
    <a href="{{ route('user.follow.followings') }}">
      フォロー<span x-text="followingsCount ?? 0"></span>人
    </a>
    <a href="{{ route('user.follow.followers') }}" class="">
      フォロワー<span x-text="followersCount ?? 0"></span>人
    </a>
    @else
    <span>フォロー<span x-text="followingsCount ?? 0"></span>人</span>
    <span class="">フォロワー<span x-text="followersCount ?? 0"></span>人</span>
    @endif
  </div>

</div>