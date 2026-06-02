<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProductUpdateRead;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class ProductUpdateService
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $manifestCache = null;

    /**
     * @return array{updates: list<array<string, mixed>>, unread_count: int}
     */
    public function forUser(User $user): array
    {
        $readIds = UserProductUpdateRead::query()
            ->where('user_id', $user->id)
            ->pluck('update_id')
            ->all();

        $updates = [];
        $unreadCount = 0;

        foreach ($this->activeManifestEntries() as $entry) {
            $isRead = in_array($entry['id'], $readIds, true);
            $updates[] = [
                ...$entry,
                'is_read' => $isRead,
            ];
            if (! $isRead) {
                $unreadCount++;
            }
        }

        usort($updates, function (array $a, array $b): int {
            $priority = ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
            if ($priority !== 0) {
                return $priority;
            }

            return strcmp($b['published_at'] ?? '', $a['published_at'] ?? '');
        });

        return [
            'updates' => $updates,
            'unread_count' => $unreadCount,
        ];
    }

    public function dismiss(User $user, string $updateId): void
    {
        $exists = collect($this->activeManifestEntries())
            ->contains(fn (array $entry): bool => ($entry['id'] ?? '') === $updateId);

        if (! $exists) {
            abort(404, 'Обновление не найдено.');
        }

        UserProductUpdateRead::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'update_id' => $updateId,
            ],
            ['read_at' => now()],
        );
    }

    public function dismissAll(User $user): void
    {
        $now = now();
        foreach ($this->activeManifestEntries() as $entry) {
            UserProductUpdateRead::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'update_id' => $entry['id'],
                ],
                ['read_at' => $now],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeManifestEntries(): array
    {
        $now = Carbon::now();
        $active = [];

        foreach ($this->manifestEntries() as $entry) {
            if (! $this->isPublished($entry, $now)) {
                continue;
            }
            if ($this->isExpired($entry, $now)) {
                continue;
            }
            $active[] = $entry;
        }

        return $active;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manifestEntries(): array
    {
        if ($this->manifestCache !== null) {
            return $this->manifestCache;
        }

        $path = config('product_updates.manifest_path');
        if (! is_string($path) || ! File::exists($path)) {
            $this->manifestCache = [];

            return $this->manifestCache;
        }

        $decoded = json_decode(File::get($path), true);
        if (! is_array($decoded)) {
            $this->manifestCache = [];

            return $this->manifestCache;
        }

        $entries = [];
        foreach ($decoded as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }
            $entries[] = [
                'id' => (string) $item['id'],
                'published_at' => (string) ($item['published_at'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'summary' => (string) ($item['summary'] ?? ''),
                'cta_label' => (string) ($item['cta_label'] ?? 'Попробовать'),
                'cta_path' => (string) ($item['cta_path'] ?? '/'),
                'context_paths' => array_values(array_filter(
                    array_map('strval', $item['context_paths'] ?? []),
                )),
                'priority' => (int) ($item['priority'] ?? 0),
                'expires_at' => isset($item['expires_at']) ? (string) $item['expires_at'] : null,
            ];
        }

        $this->manifestCache = $entries;

        return $this->manifestCache;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isPublished(array $entry, Carbon $now): bool
    {
        $publishedAt = $entry['published_at'] ?? '';
        if ($publishedAt === '') {
            return true;
        }

        try {
            return Carbon::parse($publishedAt)->startOfDay()->lte($now);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isExpired(array $entry, Carbon $now): bool
    {
        $expiresAt = $entry['expires_at'] ?? null;
        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }

        try {
            return Carbon::parse($expiresAt)->endOfDay()->lt($now);
        } catch (\Throwable) {
            return false;
        }
    }
}
