<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;

// Models - tables
use App\Models\User;
use App\Models\Category;
use App\Models\Article;

class UserController extends Controller
{
    // * All Roles
    public function getProfile() {
        $user = auth()->user();
        return $this->successfulResponseJSON(null, $user);
    }

    public function updateProfile(Request $request) {
        $request->validate([
            'name' => 'required|string|min:3|max:64',
            'username' => [
                'required',
                'string',
                'min:3',
                'max:12',
                'regex:/^\S*$/u',
                Rule::unique('users', 'username')->ignore(auth()->user()->id)
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore(auth()->user()->id)
            ]
        ]);

        $data = $request->all();

        DB::beginTransaction();
        $update = User::where('id', auth()->user()->id)
            ->update($data);

        if ($update) {
            DB::commit();
            return $this->successfulResponseJSON('The name and email have been successfully changed');
        }

        DB::rollBack();
        return $this->failedResponseJSON('The name and email failed to change');
    }

    public function updatePassword(Request $request) {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8|max:64|regex:/^\S*$/u',
            'confirm_new_password' => 'required|same:new_password'
        ]);

        if (!Hash::check($request->old_password, auth()->user()->password)) {
            return $this->failedResponseJSON('The old password does not match', 400);
        }

        $newPassword = Hash::make($request->new_password);

        DB::beginTransaction();
        $update = User::where('id', auth()->user()->id)
            ->update([
                'password' => $newPassword
            ]);

        if ($update) {
            DB::commit();
            return $this->successfulResponseJSON('The password has been successfully changed');
        }

        DB::rollBack();
        return $this->failedResponseJSON('Password failed to change');
    }

    public function updateImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|image|mimes:png,jpg|max:1024'
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = $image->hashName();
            $destinationPath = public_path('images/users');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $image->move($destinationPath, $imageName);

            $data = [
                'image' => 'images/users/' . $imageName
            ];

            DB::beginTransaction();
            $user = User::where('id', auth()->user()->id)->first();
            $update = User::where('id', auth()->user()->id)->update($data);

            if ($update) {
                if (!is_null($user['image'])) {
                    $oldImagePath = public_path($user['image']);
                    if (File::exists($oldImagePath)) {
                        File::delete($oldImagePath);
                    }
                }

                DB::commit();
                return $this->successfulResponseJSON(null, [
                    'image_url' => config('app.url') . '/' . $data['image']
                ]);
            }
        }

        DB::rollBack();
        return $this->failedResponseJSON('Image failed to upload');
    }


    // * Admin
    public function getUsers() {
        $users = User::orderBy('updated_at', 'DESC')
            ->whereNot('id', auth()->user()->id)
            ->withCount('articles')
            ->get();

        return $this->successfulResponseJSON(null, [
            'users' => $users
        ]);
    }

    public function getOneUser(User $user) {
        $articles = Category::withCount(['articles' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])->get();
        $user = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'image' => $user->image,
            'articles' => $articles
        ];

        return $this->successfulResponseJSON(null, $user);
    }

    public function addUser(Request $request) {
        $request->validate([
            'name' => 'required|string|min:3|max:64',
            'username' => 'required|string|min:3|max:12|unique:users,username|regex:/^\S*$/u',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|max:32|regex:/^\S*$/u',
        ]);

        DB::beginTransaction();
        $data = $request->all();
        $data['password'] = Hash::make($request->password);
        $create = User::create($data);

        if ($create) {
            DB::commit();
            return $this->successfulResponseJSON('New user account has been successfully created');
        }

        DB::rollBack();
        return $this->failedResponseJSON('New user account failed to create');
    }

    public function deleteUser(User $user) {
        if (auth()->user()->id === $user->id) {
            return $this->failedResponseJSON('Please do not delete yourself, you mean so much to me :)');
        }

        // check articles
        $articleExists = Article::where('user_id', $user->id)->exists();

        if ($articleExists) {
            return $this->failedResponseJSON('The user could not be deleted because they have articles', 400);
        }

        DB::beginTransaction();
        $delete = User::where('id', $user->id)->delete();

        if ($delete) {
            DB::commit();
            return $this->successfulResponseJSON('User account has been successfully deleted');
        }

        DB::rollBack();
        return $this->failedResponseJSON('User account failed to delete');
    }

    public function updateProfileUser(Request $request, User $user) {
        $request->validate([
            'name' => 'required|string|min:3|max:64',
            'username' => [
                'required',
                'string',
                'min:3',
                'max:12',
                'regex:/^\S*$/u',
                Rule::unique('users', 'username')->ignore($user->id)
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id)
            ]
        ]);

        DB::beginTransaction();
        $update = User::where('id', $user->id)
            ->update($request->all());

        if ($update) {
            DB::commit();
            return $this->successfulResponseJSON('User account has been successfully updated');
        }

        DB::rollBack();
        return $this->failedResponseJSON('User account failed to update');
    }

    public function updatePasswordUser(Request $request, User $user) {
        $request->validate([
            'new_password' => 'required|string|min:8|max:64|regex:/^\S*$/u'
        ]);

        $hashPassword = Hash::make($request->new_password);

        DB::beginTransaction();
        $update = User::where('id', $user->id)
            ->update([
                'password' => $hashPassword
            ]);

        if ($update) {
            DB::commit();
            return $this->successfulResponseJSON('User password has been successfully changed');
        }

        DB::rollBack();
        return $this->failedResponseJSON('User password failed to change');
    }
}
