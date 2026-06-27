<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('管理ダッシュボード') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-2">
                    <p>{{ __('管理者用ダッシュボード（暫定画面・後続タスクで実装予定）') }}</p>
                    <a href="{{ route('admin.users.index') }}" class="text-indigo-600 hover:underline">{{ __('ユーザー管理へ') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
