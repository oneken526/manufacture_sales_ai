<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * システム管理者専用のユーザー管理コントローラ
 * 🟡 信頼性: TASK-0007.md実装詳細1（REQ-004「システム管理者はユーザーの作成・編集・無効化ができなければならない」）より
 *
 * ロールベースアクセス制御はルート定義側の`role:admin`ミドルウェアに委譲する。
 */
class UserController extends Controller
{
    /**
     * ユーザー一覧（ページネーション50件 NFR-021）
     */
    public function index(): View
    {
        $users = User::query()->orderBy('id')->paginate(50);

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * 🟡 信頼性: TASK-0007.md実装詳細1（名前・メールアドレス・初期パスワード・役割の入力）より
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => UserRole::fromRouteKey($request->validated('role')),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'ユーザーを登録しました');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * 🟡 信頼性: TASK-0007.md実装詳細1（名前・メールアドレス・役割の変更）より
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => UserRole::fromRouteKey($request->validated('role')),
        ]);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', 'ユーザー情報を更新しました');
    }

    /**
     * is_activeフラグの切り替え（無効化確認ダイアログを経由してフォーム送信される）
     * 🟡 信頼性: TASK-0007.md実装詳細1・注意事項（自分自身を無効化できないようにする安全策）より
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', '自分自身を無効化／有効化することはできません');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', $user->is_active ? 'ユーザーを有効化しました' : 'ユーザーを無効化しました');
    }

    /**
     * 管理者による任意ユーザーへのパスワードリセットメール再送
     * 🟡 信頼性: TASK-0007.md実装詳細3（管理者が任意のユーザーに対してパスワードリセットメールを再送できる導線）より
     */
    public function sendPasswordResetLink(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->email]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'パスワード再設定メールを送信しました');
    }
}
