<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ユーザー管理') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3" role="alert" aria-live="assertive">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="flex items-center justify-end">
                        <a href="{{ route('admin.users.create') }}">
                            <x-primary-button type="button">{{ __('新規ユーザー登録') }}</x-primary-button>
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="user-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('名前') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('メールアドレス') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('役割') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('状態') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($users as $user)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $user->name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $user->email }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $user->role->label() }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            @if ($user->is_active)
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">{{ __('有効') }}</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700">{{ __('無効') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap space-x-2">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:underline">{{ __('編集') }}</a>

                                            @unless ($user->is(Auth::user()))
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.users.toggle-active', $user) }}"
                                                    class="inline"
                                                    onsubmit="return confirm('{{ $user->is_active ? __('このユーザーを無効化しますか？無効化するとログインできなくなります') : __('このユーザーを有効化しますか？') }}');"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="{{ $user->is_active ? 'text-red-600' : 'text-green-600' }} hover:underline">
                                                        {{ $user->is_active ? __('無効化') : __('有効化') }}
                                                    </button>
                                                </form>
                                            @endunless

                                            <form method="POST" action="{{ route('admin.users.send-password-reset', $user) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-gray-600 hover:underline">{{ __('パスワード再設定メール送信') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('ユーザーが見つかりません') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
