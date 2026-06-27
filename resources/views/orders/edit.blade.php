<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('受注編集') }}: {{ $order->order_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('orders.update', $order) }}">
                        @csrf
                        @method('PUT')

                        <div class="space-y-4">
                            <div>
                                <x-input-label for="remarks" :value="__('備考')" />
                                <textarea id="remarks" name="remarks" rows="4"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('remarks', $order->remarks ?? '') }}</textarea>
                                <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <x-primary-button type="submit">{{ __('更新する') }}</x-primary-button>
                            <a href="{{ route('orders.show', $order) }}">
                                <x-secondary-button type="button">{{ __('キャンセル') }}</x-secondary-button>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
