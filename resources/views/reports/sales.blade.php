<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('売上レポート') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- フィルターフォーム --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('reports.sales') }}" class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('集計期間') }}</label>
                            <select name="period" id="period-select" class="rounded-md border-gray-300 text-sm">
                                <option value="monthly" @selected($period === 'monthly')>月次</option>
                                <option value="yearly" @selected($period === 'yearly')>年次</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('年') }}</label>
                            <input type="number" name="year" value="{{ $year }}" min="2000" max="2099"
                                   class="rounded-md border-gray-300 text-sm w-24">
                        </div>
                        <div id="month-selector" style="{{ $period === 'yearly' ? 'display:none' : '' }}">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('月') }}</label>
                            <select name="month" class="rounded-md border-gray-300 text-sm">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($month === $m)>{{ $m }}月</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('集計軸') }}</label>
                            <select name="group" class="rounded-md border-gray-300 text-sm">
                                <option value="customer" @selected($group === 'customer')>顧客別</option>
                                <option value="product" @selected($group === 'product')>商品別</option>
                                <option value="period" @selected($group === 'period')>期間別</option>
                            </select>
                        </div>
                        <x-primary-button type="submit">{{ __('集計') }}</x-primary-button>
                        <a href="{{ route('reports.sales.export', array_merge(request()->query(), [])) }}"
                           class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                            {{ __('CSVエクスポート') }}
                        </a>
                    </form>
                </div>
            </div>

            {{-- 合計 --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-lg font-semibold text-gray-800">
                    {{ __('売上合計') }}: <span class="text-indigo-700">¥{{ number_format($report->totalAmount) }}</span>
                </p>
            </div>

            @if (!empty($report->rows))
                {{-- グラフ --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-700 mb-4">{{ __('売上グラフ') }}</h3>
                    <canvas id="salesChart" class="max-h-64"></canvas>
                </div>

                {{-- 集計明細テーブル --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-base font-semibold text-gray-700 mb-4">{{ __('集計明細') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">#</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                                            @if ($group === 'customer') {{ __('顧客名') }}
                                            @elseif ($group === 'product') {{ __('商品名') }}
                                            @else {{ __('期間') }}
                                            @endif
                                        </th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-500">{{ __('売上金額（円）') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($report->rows as $i => $row)
                                        <tr class="{{ $i < 3 && $group !== 'period' ? 'bg-yellow-50' : '' }}">
                                            <td class="px-4 py-2 text-gray-500">{{ $i + 1 }}</td>
                                            <td class="px-4 py-2 text-gray-800">{{ $row['label'] }}</td>
                                            <td class="px-4 py-2 text-right text-gray-800">¥{{ number_format($row['amount']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-100 font-semibold">
                                        <td colspan="2" class="px-4 py-2 text-right text-gray-700">{{ __('合計') }}</td>
                                        <td class="px-4 py-2 text-right text-gray-700">¥{{ number_format($report->totalAmount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-500 text-sm">
                    {{ __('対象期間にデータがありません。') }}
                </div>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        var periodSelect = document.getElementById('period-select');
        var monthSelector = document.getElementById('month-selector');
        if (periodSelect && monthSelector) {
            periodSelect.addEventListener('change', function () {
                monthSelector.style.display = this.value === 'yearly' ? 'none' : '';
            });
        }

        var ctx = document.getElementById('salesChart');
        if (!ctx) return;

        var chartData = @json($chartData);
        var isBar = {{ $group !== 'period' ? 'true' : 'false' }};

        new Chart(ctx, {
            type: isBar ? 'bar' : 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: '売上金額（円）',
                    data: chartData.amounts,
                    backgroundColor: 'rgba(99, 102, 241, 0.5)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    })();
    </script>
</x-app-layout>
