<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ユーザー編集') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6" id="user-edit-form">
                        @csrf
                        @method('PUT')
                        @include('admin.users._form', ['user' => $user])

                        <div class="flex items-center gap-4">
                            <x-primary-button id="user-edit-submit">{{ __('更新する') }}</x-primary-button>
                            <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('一覧に戻る') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(function () {
            $('#user-edit-form').on('submit', function () {
                $('#user-edit-submit')
                    .prop('disabled', true)
                    .text('処理中...');
            });
        });
    </script>
</x-app-layout>
