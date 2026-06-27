<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\CustomerData;
use App\Exceptions\CustomerHasOrdersException;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 顧客マスタ管理コントローラ
 * 🔵 信頼性: api-endpoints.md（顧客管理エンドポイント群）・TASK-0005.md実装詳細2より
 *
 * ロールベースアクセス制御はルート定義側の`role:`ミドルウェアに委譲する。
 */
class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {
    }

    /**
     * 顧客一覧（検索・ページネーション）
     * 🔵 信頼性: NFR-021（ページネーション1ページ50件）・🟡 REQ-011（部分一致検索）より
     */
    public function index(Request $request): View
    {
        $keyword = $request->string('q')->toString();
        $customers = $this->customerService->paginate($keyword !== '' ? $keyword : null);

        return view('customers.index', [
            'customers' => $customers,
            'keyword' => $keyword,
        ]);
    }

    /**
     * jQueryインクリメンタルサーチ用の内部AJAXエンドポイント
     * 🟡 信頼性: api-endpoints.md「GET /api/internal/customers/search?q={keyword}」より
     */
    public function searchJson(Request $request): JsonResponse
    {
        $keyword = $request->string('q')->toString();

        $customers = $keyword !== ''
            ? $this->customerService->paginate($keyword)
            : $this->customerService->paginate();

        return response()->json([
            'data' => $customers->getCollection()->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'company_name' => $customer->company_name,
                'contact_name' => $customer->contact_name,
                'phone' => $customer->phone,
            ])->values(),
        ]);
    }

    public function create(): View
    {
        return view('customers.create');
    }

    /**
     * 🔵 信頼性: REQ-010（顧客情報の登録）より
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customerService->create(
            CustomerData::fromArray($request->validated())
        );

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', '顧客を登録しました');
    }

    /**
     * 顧客詳細（受注履歴含む）
     * 🟡 信頼性: REQ-013（顧客ごとの受注履歴一覧表示）より
     */
    public function show(Customer $customer): View
    {
        $customer->load(['salesOrders' => function ($query) {
            $query->latest('id');
        }]);

        return view('customers.show', [
            'customer' => $customer,
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * 🔵 信頼性: REQ-010（顧客情報の編集）より
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customerService->update(
            $customer->id,
            CustomerData::fromArray($request->validated(), $customer->id)
        );

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', '顧客情報を更新しました');
    }

    /**
     * 受注存在時は削除を拒否し、警告メッセージを表示する
     * 🟡 信頼性: REQ-012「受注が存在する顧客の場合、システムは削除を禁止し警告を表示しなければならない」より
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        try {
            $this->customerService->delete($customer->id);
        } catch (CustomerHasOrdersException $e) {
            return redirect()
                ->route('customers.show', $customer)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('customers.index')
            ->with('status', '顧客を削除しました');
    }
}
