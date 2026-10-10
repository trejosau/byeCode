<?php

namespace App\Domain\Events;

use App\Domain\Support\Money;
use App\Domain\Support\Utc;
use InvalidArgumentException;

final class EventProfile
{
    public const OFFICIAL = 'official-kit-v1';

    public const BUSINESS = 'business-v1';

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public function normalize(array $event, string $profile): array
    {
        if (! in_array($profile, [self::OFFICIAL, self::BUSINESS], true)) {
            throw new InvalidArgumentException('Perfil de entrada desconocido.');
        }
        $this->keys($event, ['event_id', 'case_id', 'event_type', 'occurred_at', 'data'], ['event_id', 'case_id', 'event_type', 'occurred_at', 'data']);
        foreach (['event_id', 'case_id'] as $field) {
            if (! is_string($event[$field]) || ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,119}$/D', $event[$field])) {
                throw new InvalidArgumentException($field.': identificador inválido.');
            }
        }
        Utc::parse($event['occurred_at']);
        if (! is_array($event['data'])) {
            throw new InvalidArgumentException('data debe ser un objeto.');
        }
        $data = $event['data'];
        switch ($event['event_type']) {
            case 'case.opened':
                $this->keys($data, ['status', 'proposal_amount_mxn'], ['status', 'proposal_amount_mxn']);
                if ($data['status'] !== 'open') {
                    throw new InvalidArgumentException('La apertura debe tener status=open.');
                }
                if ($profile === self::BUSINESS && $data['proposal_amount_mxn'] === null) {
                    $data['amount_cents'] = null;
                    break;
                }
                if (! is_int($data['proposal_amount_mxn']) && ! is_float($data['proposal_amount_mxn'])) {
                    throw new InvalidArgumentException('proposal_amount_mxn debe ser numérico.');
                }
                $data['amount_cents'] = Money::cents($data['proposal_amount_mxn']);
                break;
            case 'proposal.viewed':
                $this->keys($data, ['section'], ['section']);
                $this->text($data['section'], 100);
                break;
            case 'message.received':
                $this->keys($data, ['message'], ['message']);
                $this->text($data['message'], 8000);
                break;
            case 'action.executed':
                $allowed = $profile === self::BUSINESS ? ['action', 'execution_reference'] : ['action'];
                $this->keys($data, $allowed, $allowed);
                if (! in_array($data['action'], ['contact', 'request_information'], true)) {
                    throw new InvalidArgumentException('Acción ejecutada no admitida por este perfil.');
                }
                if ($profile === self::BUSINESS) {
                    $this->text($data['execution_reference'], 500);
                }
                $data['action_type'] = $data['action'];
                break;
            case 'constraint.updated':
                $this->keys($data, ['contact_allowed', 'contact_not_before'], []);
                if ($data === []) {
                    throw new InvalidArgumentException('El parche de restricciones está vacío.');
                }
                if (array_key_exists('contact_allowed', $data) && ! is_bool($data['contact_allowed'])) {
                    throw new InvalidArgumentException('contact_allowed debe ser booleano.');
                }
                if (array_key_exists('contact_not_before', $data)) {
                    if ($data['contact_not_before'] !== null || $profile === self::OFFICIAL) {
                        Utc::parse($data['contact_not_before']);
                    }
                }
                break;
            case 'case.closed':
                $this->keys($data, ['status'], ['status']);
                if (! in_array($data['status'], ['accepted', 'rejected', 'cancelled'], true)) {
                    throw new InvalidArgumentException('Estado de cierre inválido.');
                }
                break;
            case 'quote.published':
                if ($profile !== self::BUSINESS) {
                    throw new InvalidArgumentException('Evento fuera del perfil oficial.');
                }
                $this->keys($data, ['version', 'total_cents'], ['version', 'total_cents']);
                if (! is_int($data['version']) || $data['version'] < 1 || ! is_int($data['total_cents']) || $data['total_cents'] < 0) {
                    throw new InvalidArgumentException('Versión o importe inválidos.');
                }
                break;
            case 'observation.recorded':
                if ($profile !== self::BUSINESS) {
                    throw new InvalidArgumentException('Evento fuera del perfil oficial.');
                }
                $this->keys($data, ['topic', 'note', 'source', 'verified_until'], ['topic', 'note', 'source', 'verified_until']);
                foreach (['topic', 'note', 'source'] as $field) {
                    $this->text($data[$field], 3000);
                }
                Utc::parse($data['verified_until']);
                break;
            default: throw new InvalidArgumentException('Tipo de evento desconocido.');
        }

        return [...$event, 'data' => $data];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $allowed
     * @param  list<string>  $required
     */
    private function keys(array $data, array $allowed, array $required): void
    {
        if (array_diff(array_keys($data), $allowed) || array_diff($required, array_keys($data))) {
            throw new InvalidArgumentException('Campos desconocidos o requeridos ausentes.');
        }
    }

    private function text(mixed $text, int $max): void
    {
        if (! is_string($text) || trim($text) === '' || mb_strlen($text) > $max) {
            throw new InvalidArgumentException('Texto vacío, inválido o demasiado largo.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    public static function hash(array $value): string
    {
        $sort = function (array $item) use (&$sort): array {
            if (! array_is_list($item)) {
                ksort($item);
            }
            foreach ($item as &$v) {
                if (is_array($v)) {
                    $v = $sort($v);
                }
            }

            return $item;
        };

        return hash('sha256', json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
