<?php

namespace App\Domain\Prediction;

/**
 * Small, deterministic L2-regularized logistic baseline, no network or vendor runtime.
 * Training, calibration and testing use separate time slices and customer groups.
 */
final class LogisticModel
{
    /**
     * @param  list<list<float>>  $x
     * @param  list<int>  $y
     * @return array<string, mixed>
     */
    public function fit(array $x, array $y, int $iterations = 600): array
    {
        $n = count($x);
        $d = count($x[0]);
        $means = [];
        $scales = [];
        for ($j = 0; $j < $d; $j++) {
            $values = array_column($x, $j);
            $mean = array_sum($values) / $n;
            $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $n;
            $means[] = $mean;
            $scales[] = max(sqrt($variance), 0.001);
        }
        $z = array_map(fn ($row) => $this->standardize($row, $means, $scales), $x);
        $prevalence = max(0.001, min(0.999, array_sum($y) / $n));
        $weights = array_fill(0, $d, 0.0);
        $bias = log($prevalence / (1 - $prevalence));
        for ($i = 0; $i < $iterations; $i++) {
            $gradient = array_fill(0, $d, 0.0);
            $gb = 0.0;
            foreach ($z as $k => $row) {
                $error = $this->sigmoid($bias + $this->dot($weights, $row)) - $y[$k];
                $gb += $error;
                foreach ($row as $j => $value) {
                    $gradient[$j] += $error * $value;
                }
            }
            $rate = 0.08;
            $bias -= $rate * $gb / $n;
            foreach ($weights as $j => $w) {
                $weights[$j] -= $rate * ($gradient[$j] / $n + 0.01 * $w);
            }
        }

        return ['weights' => $weights, 'bias' => $bias, 'means' => $means, 'scales' => $scales, 'baseline' => $prevalence];
    }

    /**
     * @param  list<float>  $x
     * @param  array<string, mixed>  $model
     */
    public function score(array $x, array $model): float
    {
        $logit = $model['bias'] + $this->dot($model['weights'], $this->standardize($x, $model['means'], $model['scales']));
        $p = $this->sigmoid($logit);
        if (isset($model['calibration'])) {
            $c = $model['calibration'];
            $p = $this->sigmoid($c['a'] * $logit + $c['b']);
        }

        return $p;
    }

    /**
     * @param  list<list<float>>  $x
     * @param  list<int>  $y
     * @param  array<string, mixed>  $model
     * @return array<string, mixed>
     */
    public function calibrate(array $x, array $y, array $model): array
    {
        $logits = array_map(function ($row) use ($model) {
            $p = max(0.0001, min(0.9999, $this->score($row, $model)));

            return log($p / (1 - $p));
        }, $x);
        $a = 1.0;
        $b = 0.0;
        $n = count($y);
        for ($i = 0; $i < 400; $i++) {
            $da = 0.0;
            $db = 0.0;
            foreach ($logits as $j => $value) {
                $error = $this->sigmoid($a * $value + $b) - $y[$j];
                $da += $error * $value;
                $db += $error;
            }
            $a = max(0.01, $a - 0.03 * ($da / $n + 0.01 * ($a - 1)));
            $b -= 0.03 * $db / $n;
        }
        $model['calibration'] = ['a' => $a, 'b' => $b];

        return $model;
    }

    /**
     * @param  list<int>  $y
     * @param  list<float>  $p
     * @return array<string, mixed>
     */
    public function metrics(array $y, array $p): array
    {
        $n = count($y);
        $brier = 0.0;
        $logloss = 0.0;
        $pairs = 0;
        $wins = 0.0;
        $bins = [];
        foreach ($y as $i => $label) {
            $brier += ($p[$i] - $label) ** 2;
            $safe = max(0.000001, min(0.999999, $p[$i]));
            $logloss -= $label * log($safe) + (1 - $label) * log(1 - $safe);
            $bin = min(4, (int) floor($p[$i] * 5));
            $bins[$bin]['n'] = ($bins[$bin]['n'] ?? 0) + 1;
            $bins[$bin]['p'] = ($bins[$bin]['p'] ?? 0) + $p[$i];
            $bins[$bin]['y'] = ($bins[$bin]['y'] ?? 0) + $label;
            if ($label === 1) {
                foreach ($y as $j => $other) {
                    if ($other === 0) {
                        $pairs++;
                        $wins += $p[$i] > $p[$j] ? 1 : ($p[$i] === $p[$j] ? 0.5 : 0);
                    }
                }
            }
        }
        $ece = 0.0;
        $reliability = [];
        ksort($bins);
        foreach ($bins as $bin => $v) {
            $avg = $v['p'] / $v['n'];
            $observed = $v['y'] / $v['n'];
            $ece += abs($avg - $observed) * $v['n'] / $n;
            $reliability[] = ['from' => $bin / 5, 'to' => ($bin + 1) / 5, 'n' => $v['n'], 'predicted' => $avg, 'observed' => $observed];
        }

        return ['n' => $n, 'positive' => array_sum($y), 'brier' => $brier / $n, 'log_loss' => $logloss / $n, 'auc' => $pairs ? $wins / $pairs : null, 'ece' => $ece, 'reliability' => $reliability];
    }

    /**
     * @param  list<float>  $x
     * @param  list<float>  $means
     * @param  list<float>  $scales
     * @return list<float>
     */
    private function standardize(array $x, array $means, array $scales): array
    {
        $out = [];
        foreach ($x as $j => $value) {
            $out[] = ($value - $means[$j]) / $scales[$j];
        }

        return $out;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function dot(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $v) {
            $sum += $v * $b[$i];
        }

        return $sum;
    }

    private function sigmoid(float $x): float
    {
        return 1 / (1 + exp(-max(-40, min(40, $x))));
    }
}
