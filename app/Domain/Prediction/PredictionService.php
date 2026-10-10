<?php

namespace App\Domain\Prediction;

use App\Domain\Cases\CaseProjector;
use App\Domain\Support\Money;
use App\Domain\Support\Utc;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\PredictionRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class PredictionService
{
    public const COLUMNS = ['case_id', 'customer_group', 'evaluation_at', 'opened_at', 'last_message_at', 'last_contact_at', 'views', 'messages', 'amount_mxn', 'outcome', 'closed_at', 'observed_until'];

    public const FEATURES = ['log_amount', 'log_views', 'log_messages', 'age_days', 'days_since_message', 'days_since_contact', 'message_missing', 'contact_missing'];

    public function __construct(private LogisticModel $logistic) {}

    public function train(User $user, string $csv, string $kind): PredictionRun
    {
        if (strlen($csv) > 2097152 || ! mb_check_encoding($csv, 'UTF-8')) {
            throw new InvalidArgumentException('CSV inválido o mayor de 2 MB.');
        }
        if (! in_array($kind, ['real', 'synthetic'], true)) {
            throw new InvalidArgumentException('Origen no reconocido.');
        }
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new InvalidArgumentException('No se pudo preparar el archivo para lectura.');
        }
        fwrite($stream, $csv);
        rewind($stream);
        $header = fgetcsv($stream, 0, ',', '"', '');
        if ($header !== self::COLUMNS) {
            fclose($stream);
            throw new InvalidArgumentException('Columnas incorrectas. Usa la plantilla, en el orden indicado.');
        }
        $rows = [];
        $censored = 0;
        $seen = [];
        $line = 1;
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $line++;
            if ($values === [null]) {
                continue;
            }
            if ($line > 5001) {
                fclose($stream);
                throw new InvalidArgumentException('Máximo 5,000 filas.');
            }
            if (count($values) !== count($header)) {
                fclose($stream);
                throw new InvalidArgumentException('Línea '.$line.': número incorrecto de columnas.');
            }
            $r = array_combine($header, $values);
            try {
                if (! $r['case_id'] || ! $r['customer_group'] || isset($seen[$r['case_id']])) {
                    throw new InvalidArgumentException('Identidad vacía o caso repetido. Usa una instantánea por caso.');
                }
                $seen[$r['case_id']] = true;
                $at = Utc::parse($r['evaluation_at']);
                $opened = Utc::parse($r['opened_at']);
                $observed = Utc::parse($r['observed_until']);
                $closed = $r['closed_at'] !== '' ? Utc::parse($r['closed_at']) : null;
                $lastMessage = $r['last_message_at'] !== '' ? Utc::parse($r['last_message_at']) : null;
                $lastContact = $r['last_contact_at'] !== '' ? Utc::parse($r['last_contact_at']) : null;
                if ($opened->greaterThan($at) || $observed->lessThan($at) || ($closed && ($closed->lessThanOrEqualTo($at) || $closed->greaterThan($observed))) || $at->greaterThan(CarbonImmutable::now('UTC')) || $observed->greaterThan(CarbonImmutable::now('UTC'))) {
                    throw new InvalidArgumentException('Fechas imposibles, futuras o resultado ya conocido al evaluar.');
                }
                foreach ([$lastMessage, $lastContact] as $time) {
                    if ($time && $time->greaterThan($at)) {
                        throw new InvalidArgumentException('Fuga temporal: actividad posterior a la instantánea.');
                    }
                }
                foreach (['views', 'messages'] as $key) {
                    if (! ctype_digit($r[$key]) || (int) $r[$key] > 1000000) {
                        throw new InvalidArgumentException('Contadores inválidos.');
                    }
                }
                if (! in_array($r['outcome'], ['accepted', 'rejected', 'cancelled', 'open'], true) || (($r['outcome'] === 'open') !== ($closed === null))) {
                    throw new InvalidArgumentException('Resultado y cierre incoherentes.');
                }
                $horizon = $at->addDays(14);
                if (! $closed && $observed->lessThan($horizon)) {
                    $censored++;

                    continue;
                }
                $label = ($r['outcome'] === 'accepted' && $closed && $closed->lessThanOrEqualTo($horizon)) ? 1 : 0;
                $rows[] = ['case_id' => $r['case_id'], 'group' => $r['customer_group'], 'at' => Utc::iso($at), 'label' => $label,
                    'label_known_at' => Utc::iso($closed ? $closed->min($horizon) : $horizon),
                    'x' => $this->features(Money::cents($r['amount_mxn']), (int) $r['views'], (int) $r['messages'], $opened, $at, $lastMessage, $lastContact)];
            } catch (InvalidArgumentException $e) {
                fclose($stream);
                throw new InvalidArgumentException('Línea '.$line.': '.$e->getMessage());
            }
        }
        fclose($stream);

        return $this->trainRows($user, $rows, count($seen), $censored, $kind, $csv, self::FEATURES, 'csv-v1');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $features
     */
    public function trainRows(User $user, array $rows, int $seen, int $censored, string $kind, string $csv, array $features, string $registry): PredictionRun
    {
        usort($rows, fn ($a, $b) => strcmp($a['at'], $b['at']));
        $report = ['target' => 'Aceptación confirmada dentro de 14 días desde la instantánea', 'horizon_days' => 14, 'dataset_rows' => $seen, 'eligible_rows' => count($rows), 'censored_rows' => $censored,
            'features' => $features, 'feature_registry' => $registry, 'split' => '60/20/20 temporal; clientes de validación/prueba se purgan de entrenamiento', 'limitations' => [
                'Contadores y resultado deben proceder de registros autorizados; el importador no puede certificar su origen.',
                'Los umbrales son criterios técnicos iniciales, no una aprobación de uso comercial ni garantía de precisión.',
                'Asociación predictiva no demuestra efecto causal de contactar ni debe superar R1–R6.',
                'Validación interna de una muestra; requiere prueba prospectiva y seguimiento de deriva.',
            ], 'gates' => []];
        $model = null;
        $status = 'insufficient_data';
        if (count($rows) >= 200) {
            $n = count($rows);
            $a = (int) floor($n * .6);
            $b = (int) floor($n * .8);
            $test = array_slice($rows, $b);
            $testGroups = array_column($test, 'group');
            $calibrationBoundary = $rows[$a]['at'];
            $calibration = array_values(array_filter(array_slice($rows, $a, $b - $a), fn ($r) => ! in_array($r['group'], $testGroups, true) && strcmp($r['label_known_at'], $test[0]['at']) < 0));
            $heldGroups = array_unique([...$testGroups, ...array_column($calibration, 'group')]);
            $train = array_values(array_filter(array_slice($rows, 0, $a), fn ($r) => ! in_array($r['group'], $heldGroups, true) && strcmp($r['label_known_at'], $calibrationBoundary) < 0));
            $report['split_counts'] = ['train' => count($train), 'calibration' => count($calibration), 'test' => count($test), 'purged' => $n - count($train) - count($calibration) - count($test)];
            $balanced = fn ($part, $minimum) => count($part) >= $minimum && array_sum(array_column($part, 'label')) >= 10 && count($part) - array_sum(array_column($part, 'label')) >= 10;
            if ($balanced($train, 100) && $balanced($calibration, 30) && $balanced($test, 30)) {
                $model = $this->logistic->fit(array_column($train, 'x'), array_column($train, 'label'));
                $model = $this->logistic->calibrate(array_column($calibration, 'x'), array_column($calibration, 'label'), $model);
                $scores = array_map(fn ($r) => $this->logistic->score($r['x'], $model), $test);
                $metrics = $this->logistic->metrics(array_column($test, 'label'), $scores);
                $baseline = $this->logistic->metrics(array_column($test, 'label'), array_fill(0, count($test), $model['baseline']));
                $report['metrics'] = $metrics;
                $report['baseline'] = $baseline;
                $report['gates'] = [
                    'brier_beats_baseline' => $metrics['brier'] < $baseline['brier'],
                    'auc_at_least_065' => $metrics['auc'] !== null && $metrics['auc'] >= 0.65,
                    'ece_at_most_015' => $metrics['ece'] <= 0.15,
                    'real_history' => $kind === 'real',
                ];
                $status = in_array(false, $report['gates'], true) ? 'not_validated' : 'experimental_ready';
                $model['feature_names'] = $features;
                $model['feature_registry'] = $registry;
                $model['horizon_days'] = 14;
                $model['training_until'] = $train[count($train) - 1]['at'];
                $model['test_until'] = $test[count($test) - 1]['at'];
            } else {
                $report['reason'] = 'Se requieren al menos 100/30/30 filas tras purga y 10 resultados de cada clase en cada partición.';
            }
        } else {
            $report['reason'] = 'Se requieren al menos 200 casos maduros distintos; los casos abiertos sin 14 días observados quedan censurados.';
        }
        $hash = hash('sha256', $csv);
        $path = 'prediction/'.$user->id.'/'.$hash.'.csv';
        if (! Storage::disk('local')->put($path, $csv)) {
            throw new InvalidArgumentException('No se pudo preservar el archivo de entrenamiento.');
        }

        return PredictionRun::create(['user_id' => $user->id, 'data_kind' => $kind, 'status' => $status, 'dataset_hash' => $hash, 'report' => $report, 'model' => $model, 'created_at' => now()]);
    }

    /**
     * @return list<float>
     */
    public function features(int $cents, int $views, int $messages, CarbonImmutable $opened, CarbonImmutable $at, ?CarbonImmutable $lastMessage, ?CarbonImmutable $lastContact): array
    {
        return [log1p($cents / 100), log1p($views), log1p($messages), min(365, $opened->diffInSeconds($at) / 86400),
            $lastMessage ? min(365, $lastMessage->diffInSeconds($at) / 86400) : 0.0,
            $lastContact ? min(365, $lastContact->diffInSeconds($at) / 86400) : 0.0,
            $lastMessage ? 0.0 : 1.0, $lastContact ? 0.0 : 1.0];
    }

    /**
     * @return array<string, mixed>
     */
    public function predict(CommercialCase $case): array
    {
        $set = DataSet::findOrFail($case->data_set_id);
        $run = PredictionRun::latest('id')->first();
        if ($case->amount_cents === null) {
            return ['available' => false, 'reason' => 'La solicitud aún no tiene una cotización con importe.'];
        }
        if ($set->mode !== 'live' || ! $run || $run->status !== 'experimental_ready' || ! $run->model) {
            return ['available' => false, 'reason' => 'No hay un modelo con historial real que haya superado los criterios de evaluación.'];
        }
        if ($run->created_at->lessThan(now()->subDays(30))) {
            return ['available' => false, 'reason' => 'La evaluación del modelo necesita renovarse; han pasado más de 30 días.'];
        }
        $s = app(CaseProjector::class)->snapshot($case);
        if ($s['integrity_blocked'] || $s['state']['status'] !== 'open') {
            return ['available' => false, 'reason' => 'El caso no es elegible para predicción.'];
        }
        $opened = null;
        foreach ($s['events'] as $event) {
            if ($event['event_type'] === 'case.opened') {
                $opened = Utc::parse($event['occurred_at']);
                break;
            }
        }
        if (! $opened) {
            return ['available' => false, 'reason' => 'Falta la apertura del caso.'];
        }
        $at = Utc::parse($s['evaluation_at']);
        $x = $this->features($s['state']['amount_cents'], $s['metrics']['views'], $s['metrics']['messages'], $opened, $at,
            $s['metrics']['last_message_at'] ? Utc::parse($s['metrics']['last_message_at']) : null,
            $s['metrics']['last_contact_at'] ? Utc::parse($s['metrics']['last_contact_at']) : null);
        if (($run->model['feature_registry'] ?? 'csv-v1') === 'business-history-v1') {
            $x = app(BusinessHistory::class)->features($case, $at);
        }
        // Refuse gross extrapolation; no synthetic probabilities for out-of-domain cases.
        foreach ($x as $i => $v) {
            if (abs($v - $run->model['means'][$i]) > 6 * $run->model['scales'][$i]) {
                return ['available' => false, 'reason' => 'El caso está fuera del rango del historial de entrenamiento.'];
            }
        }

        return ['available' => true, 'probability' => $this->logistic->score($x, $run->model), 'horizon_days' => 14, 'run_id' => $run->id, 'label' => 'Estimación experimental; no autoriza una acción ni demuestra causalidad.'];
    }
}
