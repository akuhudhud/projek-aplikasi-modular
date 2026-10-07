<?php

namespace App\Services\Profile;

use App\Models\Account;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfilePictureService
{
    public function update(
        Account $account,
        UploadedFile $file
    ): string {
        if ($account->profile_picture) {
            Storage::disk('public')->delete(
                $account->profile_picture
            );
        }

        $path = $file->store(
            'profile-pictures/'.$account->id,
            'public'
        );

        $account->profile_picture = $path;
        $account->save();

        return $path;
    }
}
