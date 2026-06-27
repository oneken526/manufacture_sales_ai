/**
 * 見積作成フォームの動的明細行操作（追加・削除・金額リアルタイム計算）
 * 🟡 信頼性: TASK-0008.md実装詳細8「jQueryによる動的明細行の追加・削除・リアルタイム金額計算」
 *           ・/api/internal/quotations/calculate との連携より
 */
$(function () {
    const $rows = $('#quotation-item-rows');
    const $addButton = $('#quotation-add-item');
    const $total = $('#quotation-total-amount');

    if ($rows.length === 0) {
        return;
    }

    const calculateUrl = $rows.data('calculate-url') || '/api/internal/quotations/calculate';
    const productOptionsHtml = $rows.data('product-options') || '';
    let rowIndex = $rows.children('.quotation-item-row').length;

    function buildRow(index) {
        return (
            '<div class="quotation-item-row flex flex-wrap items-center gap-2 border-b border-gray-200 py-2" data-index="' + index + '">'
            + '<select name="items[' + index + '][product_id]" class="quotation-item-product border-gray-300 rounded-md shadow-sm flex-1" required>'
            + '<option value="">製品を選択してください</option>'
            + productOptionsHtml
            + '</select>'
            + '<input type="number" name="items[' + index + '][quantity]" class="quotation-item-quantity border-gray-300 rounded-md shadow-sm w-24" min="1" value="1" required>'
            + '<input type="number" name="items[' + index + '][unit_price]" class="quotation-item-unit-price border-gray-300 rounded-md shadow-sm w-32" min="0" value="0" required>'
            + '<span class="quotation-item-amount w-32 text-right">0</span>'
            + '<button type="button" class="quotation-remove-item text-red-600 hover:underline text-sm">削除</button>'
            + '</div>'
        );
    }

    function recalculate() {
        const items = [];

        $rows.children('.quotation-item-row').each(function () {
            const $row = $(this);
            items.push({
                quantity: parseInt($row.find('.quotation-item-quantity').val(), 10) || 0,
                unit_price: parseInt($row.find('.quotation-item-unit-price').val(), 10) || 0,
            });
        });

        $.ajax({
            url: calculateUrl,
            method: 'POST',
            data: JSON.stringify({ items: items }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        }).done(function (response) {
            $rows.children('.quotation-item-row').each(function (i) {
                const result = response.items[i];
                $(this).find('.quotation-item-amount').text(result ? result.amount.toLocaleString() : '0');
            });
            $total.text((response.total || 0).toLocaleString());
        });
    }

    $addButton.on('click', function () {
        $rows.append(buildRow(rowIndex));
        rowIndex += 1;
        recalculate();
    });

    $rows.on('click', '.quotation-remove-item', function () {
        $(this).closest('.quotation-item-row').remove();
        recalculate();
    });

    $rows.on('change', '.quotation-item-product', function () {
        const $row = $(this).closest('.quotation-item-row');
        const unitPrice = $(this).find(':selected').data('unit-price');

        if (unitPrice !== undefined) {
            $row.find('.quotation-item-unit-price').val(unitPrice);
        }

        recalculate();
    });

    $rows.on('input', '.quotation-item-quantity, .quotation-item-unit-price', recalculate);

    recalculate();
});
