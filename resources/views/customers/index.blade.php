<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('顧客一覧') }}
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
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <form method="GET" action="{{ route('customers.index') }}" class="flex items-center gap-2" role="search">
                            <label for="customer-search" class="sr-only">{{ __('顧客検索') }}</label>
                            <input
                                type="text"
                                id="customer-search"
                                name="q"
                                value="{{ $keyword }}"
                                placeholder="{{ __('会社名・担当者名・電話番号で検索') }}"
                                class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-72"
                                aria-describedby="customer-search-status"
                            >
                            <x-secondary-button type="submit">{{ __('検索') }}</x-secondary-button>
                            <span id="customer-search-status" class="text-sm text-gray-500" aria-live="polite"></span>
                        </form>

                        @if (in_array(Auth::user()->role, [\App\Enums\UserRole::SALES, \App\Enums\UserRole::ADMIN], true))
                            <a href="{{ route('customers.create') }}">
                                <x-primary-button type="button">{{ __('新規顧客登録') }}</x-primary-button>
                            </a>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="customer-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('会社名') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('担当者名') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('電話番号') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('メール') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('与信枠') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($customers as $customer)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $customer->company_name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $customer->contact_name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $customer->phone }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $customer->email }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">{{ number_format($customer->credit_limit) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap space-x-2">
                                            <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:underline">{{ __('詳細') }}</a>
                                            @if (in_array(Auth::user()->role, [\App\Enums\UserRole::SALES, \App\Enums\UserRole::ADMIN], true))
                                                <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:underline">{{ __('編集') }}</a>
                                            @endif
                                            @if (Auth::user()->role === \App\Enums\UserRole::ADMIN)
                                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline" onsubmit="return confirm('{{ __('この顧客を削除しますか？') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:underline">{{ __('削除') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('該当する顧客が見つかりません') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $customers->appends(['q' => $keyword])->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/customers-search.js'])
</x-app-layout>
