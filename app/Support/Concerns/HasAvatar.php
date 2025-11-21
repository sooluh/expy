<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

trait HasAvatar
{
    public function updateAvatar(UploadedFile $photo): void
    {
        tap($this->avatar, function ($prev) use ($photo) {
            $diskName = config('filesystems.default');
            $avatar = $photo->store('users', ['disk' => $diskName]);
            $this->forceFill(['avatar' => $avatar])->save();

            if ($prev) {
                Storage::disk($diskName)->delete($prev);
            }
        });
    }

    public function deleteAvatar(): void
    {
        Storage::disk(config('filesystems.default'))->delete($this->avatar);
        $this->forceFill(['avatar' => null])->save();
    }

    public function avatarUrl(): Attribute
    {
        $diskName = config('filesystems.default');
        $isPublic = (config("filesystems.disks.$diskName.visibility") === 'public');

        return Attribute::get(function () use ($diskName, $isPublic) {
            if (! $this->avatar) {
                return $this->defaultAvatarUrl();
            }

            $value = (string) $this->avatar;

            if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, '//') || str_starts_with($value, 'data:')) {
                return $value;
            }

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk($diskName);

            if ($isPublic) {
                return $disk->url($this->avatar);
            }

            try {
                return $disk->temporaryUrl($this->avatar, now()->addMinutes(15));
            } catch (Throwable $e) {
                return $disk->url($this->avatar);
            }
        });
    }

    protected function defaultAvatarUrl(): string
    {
        $email = strtolower(trim((string) ($this->email ?? '')));
        $hash = md5($email);

        $params = http_build_query([
            's' => 80,
            'd' => 'mp',
            'r' => 'g',
        ]);

        return "https://www.gravatar.com/avatar/{$hash}?{$params}";
    }
}
