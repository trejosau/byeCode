<?php

namespace App\Domain\Cases;

use App\Domain\Support\Utc;
use App\Models\CaseEvent;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\DataSet;
use App\Models\QuoteVersion;
use Carbon\CarbonImmutable;

final class CaseProjector
{
    public function clock(DataSet $set): CarbonImmutable
    {
        return $set->mode === 'kit' ? $set->evaluation_at ?? Utc::parse(config('byecode.kit_clock')) : CarbonImmutable::now('UTC');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(CommercialCase $case, ?CarbonImmutable $at = null): array
    {
        $set = DataSet::findOrFail($case->data_set_id);
        $at ??= $this->clock($set);
        $events = CaseEvent::where('commercial_case_id', $case->id)->orderBy('id')->get();
        if ($set->mode === 'live') {
            $events = $events->filter(fn ($event) => $event->ingested_at->lessThanOrEqualTo($at));
        }
        $state = ['status' => 'open', 'contact_allowed' => true, 'contact_not_before' => null, 'amount_cents' => 0, 'stage' => 'proposal_sent'];
        $included = [];
        $lastContact = null;
        $lastMessage = null;
        $opened = null;
        $views = 0;
        $lastActivity = null;
        $nextBoundary = null;
        $evidence = ['closure' => null, 'permission' => null, 'date' => null, 'contact' => null, 'message' => null];
        foreach ($events as $event) {
            if ($event->occurred_at->greaterThan($at)) {
                $nextBoundary = $nextBoundary ? $nextBoundary->min($event->occurred_at) : $event->occurred_at;

                continue;
            }
            if ($event->event_type === 'observation.recorded' && ! empty($event->data['verified_until'])) {
                $expiry = Utc::parse($event->data['verified_until']);
                if ($expiry->greaterThan($at)) {
                    $nextBoundary = $nextBoundary ? $nextBoundary->min($expiry) : $expiry;
                }
            }
            $included[] = ['event_id' => $event->event_id, 'event_type' => $event->event_type, 'occurred_at' => Utc::iso($event->occurred_at), 'sequence' => $event->id, 'data' => $event->data];
            if ($lastActivity === null || $event->occurred_at->greaterThan($lastActivity)) {
                $lastActivity = $event->occurred_at;
            }
            if ($event->event_type === 'action.executed' && ($lastContact === null || $event->occurred_at->greaterThanOrEqualTo($lastContact))) {
                $lastContact = $event->occurred_at;
                $evidence['contact'] = $event->event_id;
            }
            if ($event->event_type === 'message.received' && ($lastMessage === null || $event->occurred_at->greaterThanOrEqualTo($lastMessage))) {
                $lastMessage = $event->occurred_at;
                $evidence['message'] = $event->event_id;
            }
            if ($event->event_type === 'proposal.viewed') {
                $views++;
            }
            if ($state['status'] !== 'open') {
                continue;
            }
            switch ($event->event_type) {
                case 'case.opened': $opened = $event->occurred_at;
                    $state['amount_cents'] = $event->data['amount_cents'];
                    if ($state['amount_cents'] === null) {
                        $state['stage'] = 'request_received';
                    }
                    break;
                case 'constraint.updated':
                    foreach (['contact_allowed', 'contact_not_before'] as $field) {
                        if (array_key_exists($field, $event->data)) {
                            $state[$field] = $event->data[$field];
                            $evidence[$field === 'contact_allowed' ? 'permission' : 'date'] = $event->event_id;
                        }
                    }
                    break;
                case 'case.closed': $state['status'] = $event->data['status'];
                    $state['stage'] = 'closed';
                    $evidence['closure'] = $event->event_id;
                    break;
                case 'proposal.viewed': if ($state['stage'] === 'proposal_sent') {
                    $state['stage'] = 'proposal_viewed';
                } break;
                case 'message.received': $state['stage'] = 'conversation';
                    break;
                case 'quote.published': $state['amount_cents'] = $event->data['total_cents'];
                    $state['stage'] = 'proposal_sent';
                    break;
            }
        }
        $quote = QuoteVersion::where('commercial_case_id', $case->id)->where('created_at', '<=', $at)->orderByDesc('version')->first();

        return [
            'case_id' => $case->external_id, 'case_pk' => $case->id, 'data_set_id' => $set->id, 'mode' => $set->mode,
            'case_revision' => $case->revision, 'evaluation_at' => Utc::iso($at), 'knowledge_cutoff' => $events->max('id') ?? 0,
            'next_boundary_at' => $nextBoundary ? Utc::iso($nextBoundary) : null,
            'state' => $state, 'events' => $included, 'rule_evidence' => $evidence,
            'integrity_blocked' => $case->integrity_blocked || $set->integrity_blocked || $opened === null,
            'metrics' => [
                'views' => $views, 'messages' => count(array_filter($included, fn ($e) => $e['event_type'] === 'message.received')),
                'age_hours' => $opened ? round($opened->diffInSeconds($at) / 3600, 2) : null,
                'hours_since_last_contact' => $lastContact ? round($lastContact->diffInSeconds($at) / 3600, 4) : null,
                'hours_since_last_activity' => $lastActivity ? round($lastActivity->diffInSeconds($at) / 3600, 2) : null,
                'last_contact_at' => $lastContact ? Utc::iso($lastContact) : null,
                'last_message_at' => $lastMessage ? Utc::iso($lastMessage) : null,
                'new_message_after_contact' => $lastContact && $lastMessage && $lastMessage->greaterThan($lastContact),
            ],
            'quote' => $quote ? ['version' => $quote->version, 'lines' => $quote->lines, 'total_cents' => $quote->total_cents, 'terms' => $quote->terms, 'valid_until' => Utc::iso($quote->valid_until), 'is_expired' => $quote->valid_until->lessThanOrEqualTo($at) || ($quote->getAttribute('withdrawn_at') && CarbonImmutable::parse($quote->getAttribute('withdrawn_at'))->lessThanOrEqualTo($at))] : null,
            'customer_context' => $set->mode === 'live' && $case->customer_id ? app(CustomerProfile::class)->context(Customer::findOrFail($case->customer_id), $at) : null,
        ];
    }
}
