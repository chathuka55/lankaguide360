<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require __DIR__.'/data/faqs.php' as $position => [$question, $answer, $keywords]) {
            Faq::firstOrCreate(
                ['question' => $question],
                ['answer' => $answer, 'keywords' => $keywords, 'sort_order' => $position + 1],
            );
        }
    }
}
