<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('在庫数の手動調整') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('error'))
                <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3" role="alert" aria-live="assertive">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <h3 class="text-lg font-semibold">{{ $product->product_code }} {{ $product->product_name }}</h3>

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

                    <form method="POST" action="{{ route('products.adjust-stock', $product) }}" class="space-y-6" id="adjust-stock-form">
                        @csrf

                        <div>
                            <x-input-label for="quantity_change" :value="__('増減数（マイナス値で減算）')" />
                            <x-text-input id="quantity_change" name="quantity_change" type="number" step="1" class="mt-1 block w-full" :value="old('quantity_change')" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('quantity_change')" />
                        </div>

                        <div>
                            <x-input-label for="memo" :value="__('調整理由（メモ）')" />
                            <textarea id="memo" name="memo" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('memo') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('memo')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button id="adjust-stock-submit">{{ __('調整を実行する') }}</x-primary-button>
                            <a href="{{ route('products.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('一覧に戻る') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(function () {
            $('#adjust-stock-form').on('submit', function () {
                $('#adjust-stock-submit')
                    .prop('disabled', true)
                    .text('処理中...');
            });
        });
    </script>
</x-app-layout>
