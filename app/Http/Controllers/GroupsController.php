<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BoardGamePlay;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Services\GroupAuditLogService;
use App\Services\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web controller for the Groups page, create wizard, and join-by-invite.
 */
class GroupsController extends Controller
{
    public function __construct(
        private readonly GroupService $groupService,
        private readonly GroupAuditLogService $groupAuditLogService
    ) {
    }

    /**
     * Display the Groups page (my groups + activity feed).
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $groups = Group::query()
            ->whereHas('groupMembers', fn ($q) => $q->where('user_id', $user->id))
            ->with(['groupMembers.user'])
            ->withLastActiveAt()
            ->withCount('groupMembers')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($groups as $group) {
            $membership = $group->groupMembers->firstWhere('user_id', $user->id);
            $group->current_user_is_admin = $membership?->role === GroupMember::ROLE_GROUP_ADMIN;
        }

        $activityPaginator = $this->groupAuditLogService->getActivityForUserGroups(
            $user,
            (int) $request->get('activity_per_page', 15)
        );
        $activityPaginator->appends($request->only('activity_per_page'));

        return Inertia::render('Groups/Index', [
            'groups' => $groups,
            'activityPaginator' => $activityPaginator,
        ]);
    }

    /**
     * Display the group detail page. Only group members can view.
     */
    public function show(Request $request, string $id): Response|RedirectResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('view', $group);

        $user = Auth::user();
        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        $currentUserRole = $membership?->role === GroupMember::ROLE_GROUP_ADMIN
            ? 'group_admin'
            : 'group_member';

        $group->loadCount('groupMembers');
        $group->load('groupMembers.user');

        $playsQuery = BoardGamePlay::where('group_id', $group->id)->notExcluded();
        $totalPlays = $playsQuery->count();
        $totalHoursPlayed = round((float) (clone $playsQuery)->sum('game_length_minutes') / 60, 1);
        $uniqueGamesPlayed = (clone $playsQuery)->selectRaw('count(distinct board_game_id) as cnt')->value('cnt') ?? 0;

        return Inertia::render('Groups/Show', [
            'group' => $group,
            'currentUserRole' => $currentUserRole,
            'groupSummary' => [
                'total_plays' => $totalPlays,
                'total_hours_played' => $totalHoursPlayed,
                'group_members_count' => $group->group_members_count,
                'unique_games_played' => (int) $uniqueGamesPlayed,
            ],
        ]);
    }

    /**
     * Show the create group wizard (step 1, 2, or 3).
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $step = (int) $request->get('step', 1);
        $groupId = $request->get('group_id');
        $group = null;
        $inviteUrl = null;

        if ($groupId) {
            $group = Group::find((int) $groupId);
            if ($group === null || ! Auth::user()?->isGroupAdmin($group->id)) {
                return redirect()->route('groups.index');
            }
            if ($step === 3) {
                $invite = $group->groupInvites()->valid()->first();
                if ($invite === null) {
                    $invite = $this->groupService->createInvite($group, Auth::user());
                }
                $inviteUrl = url('/groups/join/' . $invite->token);
            }
        }

        $visibilityOptions = [
            ['value' => Group::VISIBILITY_PRIVATE, 'label' => 'Private', 'description' => 'Not listed in Browse Groups. Join only via invite link.'],
            ['value' => Group::VISIBILITY_VIEWABLE, 'label' => 'Viewable', 'description' => 'Listed in Browse Groups. Join only via invite link.'],
            ['value' => Group::VISIBILITY_PUBLICLY_JOINABLE, 'label' => 'Publicly joinable', 'description' => 'Listed in Browse Groups. Anyone can join.'],
        ];

        return Inertia::render('Groups/Create', [
            'step' => $step,
            'group' => $group,
            'inviteUrl' => $inviteUrl,
            'visibilityOptions' => $visibilityOptions,
        ]);
    }

    /**
     * Store step 1: create the group (base info + photo + visibility).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'friendly_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'group_location' => ['nullable', 'string', 'max:255'],
            'website_link' => ['nullable', 'url', 'max:255'],
            'discord_link' => ['nullable', 'url', 'max:255'],
            'slack_link' => ['nullable', 'url', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'visibility' => ['nullable', 'string', 'in:private,viewable,publicly_joinable'],
        ]);

        $user = Auth::user();
        $validated = $request->only([
            'friendly_name', 'description', 'group_location', 'website_link', 'discord_link', 'slack_link', 'visibility',
        ]);
        $validated['visibility'] = $validated['visibility'] ?? Group::VISIBILITY_PRIVATE;

        $group = $this->groupService->createGroup($validated, $user);
        $this->groupAuditLogService->logGroupCreated($group, $user);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('groups/' . $group->id, 'public');
            $group->update(['photo_path' => $path]);
        }

        return redirect()->route('groups.create', ['step' => 2, 'group_id' => $group->id]);
    }

    /**
     * Store step 2: add members (by user id or BGG username).
     */
    public function storeMembers(Request $request): RedirectResponse
    {
        $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'members' => ['nullable', 'array'],
            'members.*.type' => ['required', 'string', 'in:user,bgg'],
            'members.*.user_id' => ['required_if:members.*.type,user', 'nullable', 'integer', 'exists:users,id'],
            'members.*.bgg_username' => ['required_if:members.*.type,bgg', 'nullable', 'string', 'max:255'],
            'members.*.display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $group = Group::findOrFail($request->input('group_id'));
        $this->authorize('update', $group);

        $members = $request->input('members', []);
        foreach ($members as $member) {
            if (($member['type'] ?? '') === 'user' && ! empty($member['user_id'])) {
                $memberUser = \App\Models\User::find($member['user_id']);
                if ($memberUser && ! $group->groupMembers()->where('user_id', $memberUser->id)->exists()) {
                    $this->groupService->addMemberToGroup(
                        $group,
                        $memberUser,
                        GroupMember::ROLE_GROUP_MEMBER,
                        $member['display_name'] ?? null
                    );
                }
            }
            if (($member['type'] ?? '') === 'bgg' && ! empty(trim($member['bgg_username'] ?? ''))) {
                try {
                    $this->groupService->addMemberByBggUsername(
                        $group,
                        trim($member['bgg_username']),
                        $user,
                        $member['display_name'] ?? null
                    );
                } catch (\Throwable $e) {
                    // Skip duplicate or invalid; continue with other members
                }
            }
        }

        return redirect()->route('groups.create', ['step' => 3, 'group_id' => $group->id]);
    }

    /**
     * Join a group via invite token (authenticated user).
     */
    public function joinByToken(string $token): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Please log in to join the group.');
        }

        $invite = GroupInvite::where('token', $token)->valid()->with('group')->first();
        if ($invite === null) {
            return redirect()->route('groups.index')->with('error', 'This invite link is invalid or has expired.');
        }

        $group = $invite->group;
        if ($group->groupMembers()->where('user_id', $user->id)->exists()) {
            return redirect()->route('groups.index')->with('success', 'You are already a member of this group.');
        }

        $this->groupService->addMemberToGroup($group, $user);
        $invite->increment('times_used');

        return redirect()->route('groups.index')->with('success', 'You have joined the group.');
    }

    /**
     * Browse groups (viewable or publicly joinable, excluding groups user is in).
     */
    public function browse(Request $request): Response
    {
        $user = Auth::user();
        $groups = Group::query()
            ->whereIn('visibility', [Group::VISIBILITY_VIEWABLE, Group::VISIBILITY_PUBLICLY_JOINABLE])
            ->whereDoesntHave('groupMembers', fn ($q) => $q->where('user_id', $user->id))
            ->withCount('groupMembers')
            ->withLastActiveAt()
            ->orderBy('created_at', 'desc')
            ->paginate(12, ['*'], 'page');

        return Inertia::render('Groups/Browse', [
            'groupsPaginator' => $groups,
        ]);
    }
}
