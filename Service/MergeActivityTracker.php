<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Service;

/**
 * Tracks contacts that received their first activity through a merge operation.
 * This is needed because when an inactive contact is merged with an active anonymous contact,
 * we need to treat it as the contact's first activity for triggering purposes.
 */
class MergeActivityTracker
{
    /**
     * @var array<int, bool>
     */
    private array $contactsWithFirstActivityAfterMerge = [];

    /**
     * Mark a contact as having received first activity through merge.
     */
    public function trackFirstActivityAfterMerge(int $contactId): void
    {
        $this->contactsWithFirstActivityAfterMerge[$contactId] = true;
    }

    /**
     * Check if a contact received first activity through merge.
     */
    public function hasFirstActivityAfterMerge(int $contactId): bool
    {
        return isset($this->contactsWithFirstActivityAfterMerge[$contactId]);
    }

    /**
     * Clear tracking for a specific contact (after processing).
     */
    public function clearTracking(int $contactId): void
    {
        unset($this->contactsWithFirstActivityAfterMerge[$contactId]);
    }

    /**
     * Clear all tracking (e.g., at the end of request).
     */
    public function clearAll(): void
    {
        $this->contactsWithFirstActivityAfterMerge = [];
    }
}
