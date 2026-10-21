<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index()
    {
        $modules = Permissions::MODULES;
        $roles = User::ROLES;
        $stored = RolePermission::all()->keyBy(fn ($row) => $row->role.'.'.$row->module);

        $matrix = [];
        foreach (array_keys($roles) as $role) {
            foreach ($modules as $module => $meta) {
                $entry = $stored->get($role.'.'.$module);
                $view = $role === 'admin' ? true : (bool) ($entry->can_view ?? false);
                $manage = $role === 'admin' ? true : (bool) ($entry->can_manage ?? false);
                if ($module === 'dashboard') {
                    $view = true;
                    $manage = false;
                }
                $matrix[$role][$module] = ['view' => $view, 'manage' => $manage];
            }
        }

        $users = User::withCount('evaluations')->orderBy('name')->get();

        return view('permissions.index', compact('matrix', 'modules', 'roles', 'users'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
        ]);

        $old = RolePermission::all()->mapWithKeys(fn ($row) => [
            $row->role.'.'.$row->module => ['view' => (bool) $row->can_view, 'manage' => (bool) $row->can_manage],
        ])->all();

        foreach (array_keys(User::ROLES) as $role) {
            if ($role === 'admin') {
                continue;
            }

            foreach (array_keys(Permissions::MODULES) as $module) {
                $view = (bool) data_get($data, 'permissions.'.$role.'.'.$module.'.view', false);
                $manage = (bool) data_get($data, 'permissions.'.$role.'.'.$module.'.manage', false);

                if ($module === 'dashboard') {
                    $view = true;
                    $manage = false;
                }

                if ($manage) {
                    $view = true;
                }

                RolePermission::updateOrCreate(
                    ['role' => $role, 'module' => $module],
                    ['can_view' => $view, 'can_manage' => $manage]
                );
            }
        }

        Permissions::flush();

        $new = RolePermission::all()->mapWithKeys(fn ($row) => [
            $row->role.'.'.$row->module => ['view' => (bool) $row->can_view, 'manage' => (bool) $row->can_manage],
        ])->all();

        ActivityLogger::log(
            'updated',
            'permission',
            null,
            'Matriks Hak Akses',
            $old,
            $new,
            'Hak akses per role diperbarui'
        );

        return redirect()->route('permissions.index')->with('success', 'Hak akses berhasil diperbarui.');
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => true,
            'password' => $data['password'],
        ]);

        ActivityLogger::log('created', 'user', $user->id, $user->name, null, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => true,
        ], 'Pengguna baru ditambahkan');

        return redirect()->route('permissions.index')->with('success', 'Pengguna "'.$user->name.'" berhasil ditambahkan.');
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $active = $request->boolean('is_active');

        if ($user->id === auth()->id() && ! $active) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        if ($user->role === 'admin' && ($data['role'] !== 'admin' || ! $active)) {
            $otherAdmins = User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($otherAdmins === 0) {
                return back()->with('error', 'Minimal harus ada satu administrator aktif.');
            }
        }

        $old = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'is_active' => $user->is_active];

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $active,
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        ActivityLogger::log('updated', 'user', $user->id, $user->name, $old, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ], 'Data pengguna diperbarui');

        return redirect()->route('permissions.index')->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        if ($user->role === 'admin') {
            $otherAdmins = User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($otherAdmins === 0) {
                return back()->with('error', 'Administrator terakhir tidak dapat dihapus.');
            }
        }

        $snapshot = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'is_active' => $user->is_active];
        $evaluationCount = Evaluation::where('created_by', $user->id)->count();

        if ($evaluationCount > 0) {
            Evaluation::where('created_by', $user->id)->update(['created_by' => null]);
        }

        $name = $user->name;
        $id = $user->id;
        $user->delete();

        ActivityLogger::log('deleted', 'user', $id, $name, $snapshot, null, 'Pengguna dihapus permanen');

        return redirect()->route('permissions.index')->with('success', 'Pengguna "'.$name.'" dihapus.');
    }
}
