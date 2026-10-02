<?php

namespace App\Ai;

use Illuminate\Support\Facades\File;

class Trainer
{
    public function __construct(
        private Classifier $classifier,
        private Corpus $corpus,
    ) {}

    public function path(): string
    {
        return storage_path('app/ai/model.json');
    }

    public function bundledPath(): string
    {
        return resource_path('ai/model.json');
    }

    public function train(bool $bundle = false): array
    {
        $samples = $this->corpus->samples();
        $model = $this->classifier->train($samples);
        $model['samples'] = count($samples);
        $json = json_encode($model, JSON_UNESCAPED_UNICODE);

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), $json);
        if ($bundle) {
            File::ensureDirectoryExists(dirname($this->bundledPath()));
            File::put($this->bundledPath(), $json);
        }

        return $model;
    }

    public function load(): array
    {
        foreach ([$this->path(), $this->bundledPath()] as $file) {
            if (is_file($file)) {
                $data = json_decode((string) File::get($file), true);
                if (is_array($data) && isset($data['priors'])) {
                    return $data;
                }
            }
        }

        $this->train();

        return json_decode((string) File::get($this->path()), true);
    }
}
