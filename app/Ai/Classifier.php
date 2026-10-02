<?php

namespace App\Ai;

class Classifier
{
    /** @param  list<array{text: string, intent: string}>  $samples */
    public function train(array $samples): array
    {
        $df = [];
        $classTerm = [];
        $classDocs = [];
        $classLen = [];

        foreach ($samples as $row) {
            $intent = $row['intent'];
            $grams = $this->features($row['text']);
            $classDocs[$intent] = ($classDocs[$intent] ?? 0) + 1;
            $seen = [];
            foreach ($grams as $g) {
                $classTerm[$intent][$g] = ($classTerm[$intent][$g] ?? 0) + 1;
                $classLen[$intent] = ($classLen[$intent] ?? 0) + 1;
                $seen[$g] = true;
            }
            foreach (array_keys($seen) as $g) {
                $df[$g] = ($df[$g] ?? 0) + 1;
            }
        }

        $n = max(1, count($samples));
        $vocab = array_keys($df);
        $idf = [];
        foreach ($df as $g => $c) {
            $idf[$g] = log(($n + 1) / ($c + 1)) + 1;
        }

        $priors = [];
        foreach ($classDocs as $intent => $c) {
            $priors[$intent] = log($c / $n);
        }

        return [
            'vocab' => $vocab,
            'idf' => $idf,
            'class_term' => $classTerm,
            'class_len' => $classLen,
            'class_docs' => $classDocs,
            'priors' => $priors,
            'n' => $n,
            'trained_at' => now()->toIso8601String(),
        ];
    }

    public function predict(array $model, string $text): array
    {
        $grams = $this->features($text);
        $vocabSize = max(1, count($model['vocab'] ?? []));
        $best = 'help';
        $bestScore = -INF;
        $scores = [];

        foreach ($model['priors'] ?? [] as $intent => $prior) {
            $len = (int) ($model['class_len'][$intent] ?? 0);
            $score = $prior;
            foreach ($grams as $g) {
                $tf = (int) ($model['class_term'][$intent][$g] ?? 0);
                $idf = (float) ($model['idf'][$g] ?? 1);
                $score += log(($tf + 1) / ($len + $vocabSize)) * $idf;
            }
            $scores[$intent] = $score;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $intent;
            }
        }

        $max = max($scores ?: [0]);
        $exps = [];
        $sum = 0.0;
        foreach ($scores as $intent => $s) {
            $exps[$intent] = exp($s - $max);
            $sum += $exps[$intent];
        }
        $conf = $sum > 0 ? ($exps[$best] / $sum) : 0.0;

        return [
            'intent' => $best,
            'confidence' => round($conf, 4),
            'scores' => $scores,
        ];
    }

    public function features(string $text): array
    {
        $expanded = Lexicon::expand($text)['expanded'];
        $tokens = Text::tokens($expanded);

        return Text::ngrams($tokens, 2);
    }
}
