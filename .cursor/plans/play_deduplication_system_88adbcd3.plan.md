---
name: Play Deduplication System
overview: Implement a deduplication system for board game plays that identifies duplicate plays (same boardgame, same played_at date, different creators, same participants) and marks one as leading while excluding others. The system will efficiently handle updates and new plays without excessive recalculation.
todos:
  - id: migration
    content: Create migration to add is_excluded, leading_play_id, excluded_at, exclusion_reason columns and indexes to board_game_plays table
    status: completed
  - id: deduplication_service
    content: Create BoardGamePlayDeduplicationService with methods for finding duplicates, determining leading play, and marking excluded plays
    status: completed
    dependencies:
      - migration
  - id: model_updates
    content: Update BoardGamePlay model with new fillable fields, casts, scopes (excluded, notExcluded, leading), and helper methods
    status: completed
    dependencies:
      - migration
  - id: integrate_service
    content: Integrate deduplication service into BoardGamePlayService create/update/delete methods
    status: completed
    dependencies:
      - deduplication_service
      - model_updates
  - id: update_statistics
    content: Update all statistics queries (DashboardController, API controllers, etc.) to filter excluded plays using notExcluded scope
    status: completed
    dependencies:
      - model_updates
  - id: unit_tests
    content: Write comprehensive unit tests for BoardGamePlayDeduplicationService covering all duplicate detection and leading play selection scenarios
    status: completed
    dependencies:
      - deduplication_service
  - id: integration_tests
    content: Write feature tests for API endpoints and statistics to ensure excluded plays are filtered correctly
    status: completed
    dependencies:
      - integrate_service
      - update_statistics
---

# Play Deduplication System Implementation

## Overview

Implement a deduplication system that identifies duplicate board game plays within groups and marks one as "leading" while excluding others from statistics. The system will efficiently handle new plays and updates without recalculating all plays.

## Requirements Summary

- **Duplicate Criteria**: Same boardgame, same `played_at` date, logged by different users, same participants
- **Leading Play Selection**: Earliest `created_at`, then lowest `bgg_play_id` if both have it
- **Details Preference**: Prefer play with more details (comments, scores) as leading
- **Efficiency**: Only recalculate affected plays when new/updated plays are added

## Implementation Plan

### 1. Database Schema Changes

**Migration**: `database/migrations/YYYY_MM_DD_HHMMSS_add_deduplication_fields_to_board_game_plays_table.php`

Add columns to `board_game_plays`:

- `is_excluded` (boolean, default false) - Marks excluded duplicate plays
- `leading_play_id` (foreignId nullable) - References the leading play in a duplicate group
- `excluded_at` (timestamp nullable) - When the play was marked as excluded
- `exclusion_reason` (text nullable) - Reason for exclusion (for debugging/auditing)

Add indexes:

- Index on `is_excluded` for efficient filtering
- Index on `leading_play_id` for reverse lookups
- Composite index on `(board_game_id, played_at, group_id)` for duplicate detection

### 2. Service Layer: Deduplication Service

**New File**: `app/Services/BoardGamePlayDeduplicationService.php`

This service will handle:

- **`findDuplicatePlays(BoardGamePlay $play): Collection`** - Find all potential duplicates for a play
- **`identifyDuplicateGroup(Collection $plays): array`** - Group plays that are actual duplicates (same participants)
- **`determineLeadingPlay(Collection $duplicatePlays): BoardGamePlay`** - Select leading play based on priority rules
- **`markExcludedPlays(BoardGamePlay $leadingPlay, Collection $excludedPlays): void`** - Mark plays as excluded
- **`syncDeduplicationForPlay(BoardGamePlay $play): void`** - Main entry point that handles deduplication for a single play
- **`syncDeduplicationForGroup(?int $groupId, ?int $boardGameId = null, ?\Carbon\Carbon $playedAt = null): void`** - Recalculate deduplication for a specific scope (efficient updates)

**Key Logic**:

- Compare participants by normalizing player identifiers (user_id, bgg_username, guest_name)
- Use set comparison to ensure exact same participants
- Prefer play with more details (has comment, has scores) when `created_at` and `bgg_play_id` are equal
- Only process plays within the same group

### 3. Model Updates

**File**: `app/Models/BoardGamePlay.php`

Add:

- `is_excluded` and `leading_play_id` to fillable array
- Cast `is_excluded` to boolean
- **`scopeExcluded($query)`** - Filter excluded plays
- **`scopeNotExcluded($query)`** - Filter non-excluded plays (default for statistics)
- **`scopeLeading($query)`** - Filter only leading plays
- **`isExcluded(): bool`** - Check if play is excluded
- **`isLeading(): bool`** - Check if play is leading (not excluded and no leading_play_id set)
- **`getLeadingPlay(): ?BoardGamePlay`** - Get the leading play if this is excluded
- **`getExcludedPlays(): Collection`** - Get all excluded plays that point to this as leading

### 4. Integration with Play Service

**File**: `app/Services/BoardGamePlayService.php`

Update:

- **`createBoardGamePlay()`**: After creating play, call `syncDeduplicationForPlay()`
- **`updateBoardGamePlay()`**: After updating play, call `syncDeduplicationForPlay()` (may affect other plays)
- **`deleteBoardGamePlay()`**: Before deletion, if play is leading, promote another from excluded group; if excluded, just delete

### 5. Statistics Query Updates

Update all statistics queries to exclude duplicate plays:

**Files to update**:

- `app/Http/Controllers/DashboardController.php` - Add `->notExcluded()` to play queries
- `app/Http/Controllers/Api/V1/BoardGamePlayController.php` - Add `->notExcluded()` to index query (or make it optional via query param)
- Any other places that query plays for statistics

**File**: `app/Models/BoardGamePlayPlayer.php`

- Update statistics queries to join with plays and filter `is_excluded = false`

### 6. Efficient Update Strategy

**Optimization Approach**:

- When a play is created/updated, only recalculate deduplication for:
  - Plays with same `board_game_id`, `played_at`, and `group_id`
  - Limit scope to avoid full table scans
- Use database transactions to ensure consistency
- Consider using a background job for bulk recalculations if needed

**Method**: `syncDeduplicationForPlay()` will:

1. Find potential duplicates (same boardgame, date, group)
2. Filter to actual duplicates (same participants, different creators)
3. Determine leading play
4. Update excluded flags efficiently
5. Handle edge cases (leading play deleted, play updated to no longer be duplicate)

### 7. Background Job (Optional)

**New File**: `app/Jobs/SyncPlayDeduplicationJob.php`

For bulk operations or scheduled maintenance:

- Recalculate deduplication for all plays in a group
- Recalculate for specific date ranges
- Can be queued for async processing

### 8. Testing

**New File**: `tests/Unit/Services/BoardGamePlayDeduplicationServiceTest.php`

Test cases:

- Duplicate detection with same participants
- Leading play selection (created_at priority)
- Leading play selection (bgg_play_id fallback)
- Prefer play with more details
- Exclude plays correctly
- Handle updates that break/create duplicates
- Handle deletions
- Edge cases (no duplicates, all excluded, etc.)

**Update**: `tests/Unit/Services/BoardGamePlayServiceTest.php`

- Test that deduplication is triggered on create/update

**New File**: `tests/Feature/Api/V1/BoardGamePlayDeduplicationTest.php`

- Test API endpoints return correct plays (excluded filtered)
- Test statistics exclude duplicates

## File Structure

```
database/migrations/
  YYYY_MM_DD_HHMMSS_add_deduplication_fields_to_board_game_plays_table.php

app/Services/
  BoardGamePlayDeduplicationService.php (NEW)
  BoardGamePlayService.php (UPDATE)

app/Models/
  BoardGamePlay.php (UPDATE)

app/Http/Controllers/
  DashboardController.php (UPDATE)
  Api/V1/BoardGamePlayController.php (UPDATE)

app/Jobs/
  SyncPlayDeduplicationJob.php (NEW, optional)

tests/Unit/Services/
  BoardGamePlayDeduplicationServiceTest.php (NEW)
  BoardGamePlayServiceTest.php (UPDATE)

tests/Feature/Api/V1/
  BoardGamePlayDeduplicationTest.php (NEW)
```

## Key Implementation Details

### Participant Matching Logic

```php
// Normalize participants to a comparable format
private function normalizeParticipants(BoardGamePlay $play): array
{
    return $play->players->map(function ($player) {
        return [
            'user_id' => $player->user_id,
            'bgg_username' => $player->board_game_geek_username,
            'guest_name' => $player->guest_name,
        ];
    })->sortBy('user_id')->sortBy('bgg_username')->sortBy('guest_name')->values()->toArray();
}

// Compare if two plays have same participants
private function hasSameParticipants(BoardGamePlay $play1, BoardGamePlay $play2): bool
{
    $participants1 = $this->normalizeParticipants($play1);
    $participants2 = $this->normalizeParticipants($play2);
    
    return $participants1 === $participants2;
}
```

### Leading Play Selection

```php
private function determineLeadingPlay(Collection $plays): BoardGamePlay
{
    // Sort by created_at, then by bgg_play_id
    $sorted = $plays->sortBy(function ($play) {
        return [
            $play->created_at->timestamp,
            $play->bgg_play_id ?? PHP_INT_MAX,
        ];
    });
    
    // Prefer play with more details if dates/IDs are equal
    $leading = $sorted->first();
    $samePriority = $sorted->filter(function ($play) use ($leading) {
        return $play->created_at->equalTo($leading->created_at) &&
               ($play->bgg_play_id ?? PHP_INT_MAX) === ($leading->bgg_play_id ?? PHP_INT_MAX);
    });
    
    if ($samePriority->count() > 1) {
        // Prefer play with more details
        $leading = $samePriority->max(function ($play) {
            $detailScore = 0;
            if (!empty($play->comment)) $detailScore += 10;
            if ($play->players->whereNotNull('score')->count() > 0) $detailScore += 5;
            return $detailScore;
        });
    }
    
    return $leading;
}
```

## Migration Strategy

1. Add new columns with defaults (existing plays are not excluded)
2. Run a one-time migration job to deduplicate existing plays
3. Enable automatic deduplication for new/updated plays
4. Update all statistics queries to filter excluded plays

## Performance Considerations

- Index on `(board_game_id, played_at, group_id)` for fast duplicate lookups
- Index on `is_excluded` for statistics queries
- Only process affected plays (same boardgame/date/group) on create/update
- Consider caching participant normalization if needed