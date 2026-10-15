<?php

namespace Database\Seeders;

use App\Models\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $base = now()->subDays(1); // 1日前の日時を取得

        Message::factory()
            ->count(25)
            ->sequence(fn($seq) => [
                'conversation_id' => 6,
                'sender_id' => $seq->index % 2 === 0 ? 4 : 6,
                'created_at' => $base->copy()->addMinutes($seq->index),
                'updated_at' => $base->copy()->addMinutes($seq->index),
            ])
            ->create();
    }
}
