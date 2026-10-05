<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('relic:ai-train', function () {
    $model = app(\App\Ai\Trainer::class)->train(true);
    $this->info('Relic AI trained: '.$model['samples'].' samples, '.count($model['priors']).' intents.');
})->purpose('Train Relic Care NLU (intents + abbreviation lexicon)');
