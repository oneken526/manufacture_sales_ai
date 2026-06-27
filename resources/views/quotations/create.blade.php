<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('見積新規作成') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($errors->any())
                        <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3" role="alert" aria-live="assertive">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('quotations.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-gray-700">{{ __('顧客') }}</label>
                            <select id="customer_id" name="customer_id" class="mt-1 border-gray-300 rounded-md shadow-sm w-full" required>
                                <option value="">{{ __('顧客を選択してください') }}</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->company_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-medium text-gray-700">{{ __('明細') }}</h3>
                                <x-secondary-button type="button" id="quotation-add-item">{{ __('明細行を追加') }}</x-secondary-button>
                            </div>

                            <div
                                id="quotation-item-rows"
                                data-calculate-url="{{ route('api.internal.quotations.calculate') }}"
                                data-product-options="{{ $products->map(fn ($product) => '<option value="'.$product->id.'" data-unit-price="'.$product->unit_price.'">'.e($product->product_name).'</option>')->implode('') }}"
                            >
                                @foreach (old('items', []) as $index => $item)
                                    <div class="quotation-item-row flex flex-wrap items-center gap-2 border-b border-gray-200 py-2" data-index="{{ $index }}">
                                        <select name="items[{{ $index }}][product_id]" class="quotation-item-product border-gray-300 rounded-md shadow-sm flex-1" required>
                                            <option value="">{{ __('製品を選択してください') }}</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}" data-unit-price="{{ $product->unit_price }}" @selected(($item['product_id'] ?? null) == $product->id)>{{ $product->product_name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="items[{{ $index }}][quantity]" class="quotation-item-quantity border-gray-300 rounded-md shadow-sm w-24" min="1" value="{{ $item['quantity'] ?? 1 }}" required>
                                        <input type="number" name="items[{{ $index }}][unit_price]" class="quotation-item-unit-price border-gray-300 rounded-md shadow-sm w-32" min="0" value="{{ $item['unit_price'] ?? 0 }}" required>
                                        <span class="quotation-item-amount w-32 text-right">0</span>
                                        <button type="button" class="quotation-remove-item text-red-600 hover:underline text-sm">{{ __('削除') }}</button>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex items-center justify-end gap-2 mt-4 text-sm font-medium text-gray-700">
                                <span>{{ __('合計金額') }}:</span>
                                <span id="quotation-total-amount">0</span>
                            </div>
                        </div>

                        <div>
                            <label for="expires_at" class="block text-sm font-medium text-gray-700">{{ __('有効期限') }}</label>
                            <input type="date" id="expires_at" name="expires_at" value="{{ old('expires_at') }}" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        </div>

                        <div>
                            <label for="remarks" class="block text-sm font-medium text-gray-700">{{ __('備考') }}</label>
                            <textarea id="remarks" name="remarks" rows="3" class="mt-1 border-gray-300 rounded-md shadow-sm w-full">{{ old('remarks') }}</textarea>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('見積を作成する') }}</x-primary-button>
                            <a href="{{ route('quotations.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('一覧に戻る') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/quotations.js'])
</x-app-layout>
