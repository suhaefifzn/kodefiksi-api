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
            // delete old image
            if (!is_null(auth()->user()->image)) {
                $explodedPath = explode('/', auth()->user()->image);
                $fileImage = end($explodedPath);
                $oldImagePath = __DIR__ . '/../../../../../../public_html/api.kodefiksi.com/images/users/' . $fileImage;

                if (File::exists($oldImagePath)) {
                    File::delete($oldImagePath);
                }
            }

            $image = $request->file('image');
            $imageName = $image->hashName();
            $destinationPath = realpath(__DIR__ . '/../../../../../../public_html/api.kodefiksi.com/images/users/');

            if ($destinationPath === false) {
                $destinationPath = __DIR__ . '/../../../../../../public_html/api.kodefiksi.com/images/users/';
            }

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
}
