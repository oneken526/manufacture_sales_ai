<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('顧客情報編集') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        @include('customers._form', ['customer' => $customer])

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('更新する') }}</x-primary-button>
                            <a href="{{ route('customers.show', $customer) }}" class="text-sm text-gray-600 hover:underline">{{ __('詳細に戻る') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
