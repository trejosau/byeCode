<?php

namespace App\Domain\Cases;

use App\Domain\Evaluation\EvaluationService;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\HumanReview;
use App\Models\Recommendation;
use App\Models\RecommendationFeedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class RecommendationReviewService
{
    public function __construct(private Workspace $workspace, private EvaluationService $evaluations) {}

    /** @param array<string, mixed> $input */
    public function review(User $user, Recommendation $recommendation, array $input): void
    {
        abort_unless($user->role === 'commerce', 403);
        $case = CommercialCase::findOrFail($recommendation->commercial_case_id);
        $this->workspace->ownCase($user, $case);
        $data = Validator::make($input, ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();
        DB::transaction(function () use ($case, $recommendation, $user, $data) {
            DataSet::whereKey($case->data_set_id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $recommendation = Recommendation::whereKey($recommendation->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->evaluations->isCurrent($case, $recommendation), 409, 'La recomendación ya no está vigente.');
            abort_unless($recommendation->publication_status === 'awaiting_review', 409, 'La revisión ya fue resuelta.');
            HumanReview::create(['recommendation_id' => $recommendation->id, 'user_id' => $user->id, ...$data, 'created_at' => now()]);
            $recommendation->update(['publication_status' => $data['decision'] === 'approved' ? 'published' : 'rejected']);
            app(CustomerJourney::class)->record($case, $user, 'recommendation.reviewed', ['recommendation_id' => $recommendation->id, ...$data]);
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function feedback(User $user, Recommendation $recommendation, array $input): void
    {
        abort_unless($user->role === 'commerce', 403);
        $case = CommercialCase::findOrFail($recommendation->commercial_case_id);
        $this->workspace->ownCase($user, $case);
        $data = Validator::make($input, ['verdict' => ['required', Rule::in(['useful', 'not_useful'])], 'reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();
        RecommendationFeedback::create(['recommendation_id' => $recommendation->id, 'user_id' => $user->id, ...$data, 'created_at' => now()]);
        app(CustomerJourney::class)->record($case, $user, 'recommendation.feedback', ['recommendation_id' => $recommendation->id, ...$data]);
    }
}
