<?php

namespace App\Services\Profile;

use App\Models\Account;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfilePictureService
{
    public function update(
        Account $account,
        UploadedFile $file
    ): string {
        $oldPath = $account->profile_picture;

        $newPath = $file->store(
            'profile-pictures/'.$account->id,
            'public'
        );

        $account->profile_picture = $newPath;
        $account->save();

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $newPath;
    }
}
