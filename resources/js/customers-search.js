/**
 * 顧客検索フォームのインクリメンタルサーチ
 * 🔵 信頼性: TASK-0005.md実装詳細6「検索フォームのkeyupイベントでAJAXリクエストを送信し、結果を非同期に一覧へ反映する」より
 *
 * デバウンス処理を入れ、入力中の過剰なリクエスト送信を防止する。
 */
$(function () {
    const $input = $('#customer-search');
    const $status = $('#customer-search-status');
    const $tableBody = $('#customer-table tbody');

    if ($input.length === 0 || $tableBody.length === 0) {
        return;
    }

    const searchUrl = $input.data('search-url') || '/api/internal/customers/search';
    let debounceTimer = null;

    function renderRows(customers) {
        if (customers.length === 0) {
            $tableBody.html(
                '<tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">該当する顧客が見つかりません</td></tr>'
            );
            return;
        }

        const rows = customers.map(function (customer) {
            return (
                '<tr>'
                + '<td class="px-4 py-2 whitespace-nowrap">' + $('<div>').text(customer.company_name).html() + '</td>'
                + '<td class="px-4 py-2 whitespace-nowrap">' + $('<div>').text(customer.contact_name || '').html() + '</td>'
                + '<td class="px-4 py-2 whitespace-nowrap">' + $('<div>').text(customer.phone || '').html() + '</td>'
                + '<td class="px-4 py-2 whitespace-nowrap"></td>'
                + '<td class="px-4 py-2 whitespace-nowrap text-right"></td>'
                + '<td class="px-4 py-2 whitespace-nowrap">'
                + '<a href="/customers/' + customer.id + '" class="text-indigo-600 hover:underline">詳細</a>'
                + '</td>'
                + '</tr>'
            );
        });

        $tableBody.html(rows.join(''));
    }

    $input.on('keyup', function () {
        const keyword = $input.val().trim();

        window.clearTimeout(debounceTimer);
        $status.text('検索中...');

        debounceTimer = window.setTimeout(function () {
            $.getJSON(searchUrl, { q: keyword })
                .done(function (response) {
                    renderRows(response.data || []);
                    $status.text('');
                })
                .fail(function () {
                    $status.text('検索に失敗しました');
                });
        }, 300);
    });
});
