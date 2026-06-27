<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('製品情報編集') }}
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
                    <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        @include('products._form', ['product' => $product])

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('更新する') }}</x-primary-button>
                            <a href="{{ route('products.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('一覧に戻る') }}</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-2">
                    <h3 class="text-lg font-semibold">{{ __('在庫情報') }}</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('実在庫数') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ number_format($product->stock_quantity) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('引当中在庫数') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ number_format($product->reserved_quantity) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('利用可能在庫数') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ number_format($product->stock_quantity - $product->reserved_quantity) }}</dd>
                        </div>
                    </dl>
                    <a href="{{ route('products.adjust-stock.form', $product) }}" class="text-sm text-indigo-600 hover:underline">{{ __('在庫数を手動調整する') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
