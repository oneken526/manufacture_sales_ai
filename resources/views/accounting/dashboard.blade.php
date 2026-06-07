<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('請求/入金管理') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __('管理職・経理担当者用 請求/入金管理画面（暫定画面・後続タスクで実装予定）') }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
