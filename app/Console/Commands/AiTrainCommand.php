<?php

namespace App\Console\Commands;

use App\Ai\Trainer;
use Illuminate\Console\Command;

class AiTrainCommand extends Command
{
    protected $signature = 'ai:train {--bundle : Ghi thêm vào resources/ai/model.json}';

    protected $description = 'Huấn luyện lại classifier NLU Relic Care/Ops từ Corpus + Lexicon';

    public function handle(Trainer $trainer): int
    {
        $this->info('Đang huấn luyện model…');
        $model = $trainer->train((bool) $this->option('bundle'));
        $intents = is_array($model['priors'] ?? null) ? count($model['priors']) : 0;
        $samples = (int) ($model['samples'] ?? 0);

        $this->info("Xong: {$samples} mẫu, {$intents} intent.");
        $this->line('Runtime: '.$trainer->path());
        if ($this->option('bundle')) {
            $this->line('Bundled: '.$trainer->bundledPath());
        }

        return self::SUCCESS;
    }
}
