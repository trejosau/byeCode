<?php

use App\Domain\Prediction\LogisticModel;
use App\Domain\Prediction\PredictionService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
});

function predictionCsv(int $count, bool $sameGroup = false, bool $rapid = false): string
{
    $csv = implode(',', PredictionService::COLUMNS)."\n";
    $base = CarbonImmutable::parse('2023-01-01T12:00:00Z');
    for ($i = 0; $i < $count; $i++) {
        $at = $rapid ? $base->addMinutes($i) : $base->addDays($i);
        $win = $i % 2 === 0;
        $csv .= implode(',', ['C'.$i, $sameGroup ? 'one-customer' : 'G'.$i, $at->format('Y-m-d\TH:i:s\Z'), $at->subDay()->format('Y-m-d\TH:i:s\Z'), $at->subHour()->format('Y-m-d\TH:i:s\Z'), '', $win ? 5 : 1, $win ? 8 : 1, $win ? '10000' : '100', $win ? 'accepted' : 'rejected', $at->addDay()->format('Y-m-d\TH:i:s\Z'), $at->addDays(20)->format('Y-m-d\TH:i:s\Z')])."\n";
    }

    return $csv;
}

it('computes proper scoring metrics and a reproducible logistic baseline', function () {
    $model = app(LogisticModel::class);
    $m = $model->metrics([0, 1], [0.1, 0.9]);
    expect($m['brier'])->toEqualWithDelta(.01, .000001)->and($m['auc'])->toBe(1.0)->and($m['ece'])->toEqualWithDelta(.1, .000001);
    $x = [[-2.0], [-1.0], [1.0], [2.0]];
    $y = [0, 0, 1, 1];
    $fit = $model->fit($x, $y);
    expect($model->score([2.0], $fit))->toBeGreaterThan(.8)->and($model->score([-2.0], $fit))->toBeLessThan(.2)->and($model->fit($x, $y))->toBe($fit);
});

it('does not activate a model from synthetic or insufficient data', function () {
    $service = app(PredictionService::class);
    $small = $service->train($this->user, predictionCsv(30), 'real');
    expect($small->status)->toBe('insufficient_data')->and($small->model)->toBeNull();
    $synthetic = $service->train($this->user, predictionCsv(250), 'synthetic');
    expect($synthetic->status)->toBe('not_validated')->and($synthetic->report['gates']['real_history'])->toBeFalse()->and($synthetic->report['metrics']['n'])->toBe(50);
    Http::assertNothingSent();
});

it('purges overlapping clients and labels that mature after a split boundary', function () {
    $s = app(PredictionService::class);
    $grouped = $s->train($this->user, predictionCsv(250, true), 'real');
    expect($grouped->status)->toBe('insufficient_data')->and($grouped->report['split_counts']['train'])->toBe(0);
    $rapid = $s->train($this->user, predictionCsv(250, false, true), 'real');
    expect($rapid->status)->toBe('insufficient_data')->and($rapid->report['split_counts']['train'])->toBe(0)->and($rapid->report['split_counts']['calibration'])->toBe(0);
});

it('rejects leaked activity and preserves censored open cases separately', function () {
    $csv = predictionCsv(1);
    $csv = str_replace('2023-01-01T11:00:00Z', '2023-01-02T11:00:00Z', $csv);
    expect(fn () => app(PredictionService::class)->train($this->user, $csv, 'real'))->toThrow(InvalidArgumentException::class);
    $csv = implode(',', PredictionService::COLUMNS)."\nC1,G1,2023-01-01T12:00:00Z,2023-01-01T10:00:00Z,,,0,0,100,open,,2023-01-02T12:00:00Z\n";
    $run = app(PredictionService::class)->train($this->user, $csv, 'real');
    expect($run->report['censored_rows'])->toBe(1)->and($run->report['eligible_rows'])->toBe(0);
});
