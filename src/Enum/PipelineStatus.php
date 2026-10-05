<?php

namespace Base\Forge\Enum;

/**
 * Where a pipeline, or one of its jobs, stands. The values are the states @glitchr/graphjs
 * draws (its `pipeline` preset): a status is given to a node as it is.
 */
enum PipelineStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case FAILED = 'failed';
    case CANCELED = 'canceled';
    case SKIPPED = 'skipped';
    case MANUAL = 'manual';

    /** Nothing more will happen by itself. */
    public function isFinished(): bool
    {
        return \in_array($this, [self::SUCCESS, self::WARNING, self::FAILED, self::CANCELED, self::SKIPPED], true);
    }

    /**
     * The status of a whole from its parts' (a stage from its jobs, a pipeline from its stages):
     * failed as soon as one failed, running while one runs, waiting on a manual step, pending while
     * one has not started; then success - with a warning when a job allowed to fail did.
     *
     * @param iterable<self> $parts
     */
    public static function of(iterable $parts): self
    {
        $seen = [];
        foreach ($parts as $part) {
            $seen[$part->value] = true;
        }
        if (!$seen) {
            return self::PENDING;
        }
        foreach ([self::FAILED, self::RUNNING, self::CANCELED] as $status) {
            if (isset($seen[$status->value])) {
                return $status;
            }
        }
        if (isset($seen[self::PENDING->value])) {
            // Some done, some not started: it is under way.
            return \count($seen) > 1 && array_diff(array_keys($seen), [self::PENDING->value, self::MANUAL->value, self::SKIPPED->value]) ? self::RUNNING : self::PENDING;
        }
        if (isset($seen[self::MANUAL->value])) {
            return self::MANUAL;
        }
        if (isset($seen[self::WARNING->value])) {
            return self::WARNING;
        }

        return isset($seen[self::SUCCESS->value]) ? self::SUCCESS : self::SKIPPED;
    }
}
