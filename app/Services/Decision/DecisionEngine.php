<?php

namespace App\Services\Decision;

use App\Domain\Contracts\DecisionEngineInterface;
use App\Domain\Contracts\DecisionRuleInterface;
use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionContext;
use RuntimeException;

class DecisionEngine implements DecisionEngineInterface
{
    /** @var DecisionRuleInterface[] */
    private readonly array $rules;

    /**
     * @param  DecisionRuleInterface[]  $rules
     */
    public function __construct(array $rules)
    {
        $this->rules = collect($rules)
            ->sortBy(fn (DecisionRuleInterface $rule) => $rule->priority())
            ->values()
            ->all();
    }

    public function decide(DecisionContext $context): Decision
    {
        foreach ($this->rules as $rule) {
            if ($rule->applies($context)) {
                return $rule->decide($context);
            }
        }

        throw new RuntimeException(
            'No decision rule applied — register a fallback rule whose applies() always returns true.'
        );
    }
}
