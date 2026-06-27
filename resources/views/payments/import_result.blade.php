<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('インポート結果') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            {{-- サマリー --}}
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3">
                <strong>{{ $result->matchedCount }}件成功</strong>、{{ $result->unmatchedCount }}件未照合
            </div>

            @if ($result->unmatchedCount > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ __('未照合明細') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('CSVデータ') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('未照合理由') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($result->unmatchedItems as $item)
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-mono text-gray-700">{{ $item['row'] }}</td>
                                            <td class="px-4 py-2 text-sm text-red-600">{{ $item['reason'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div>
                <a href="{{ route('payments.import') }}" class="text-indigo-600 hover:underline text-sm">
                    ← {{ __('インポート画面に戻る') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
