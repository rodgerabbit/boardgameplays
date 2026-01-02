---
name: Groups CRUD with Audit Logging
overview: Implement complete CRUD functionality for groups with role-based access control, rate limiting, soft deletes, audit logging, and admin restore capabilities. Includes database migrations, models, services, controllers, policies, form requests, API resources, and comprehensive tests.
todos: []
---

# Groups CRUD Implementation Plan



## Overview

This plan implements a complete groups management system with CRUD operations, role-based authorization, rate limiting, soft deletes, comprehensive audit logging, and admin restore functionality.

## Architecture

### Database Schema

1. **`groups` table**

- `id` (primary key)
- `friendly_name` (string, required)
- `description` (text, nullable)
- `group_location` (string, nullable)
- `website_link` (string, nullable, URL)
- `discord_link` (string, nullable, URL)
- `slack_link` (string, nullable, URL)
- `created_at`, `updated_at`, `deleted_at` (soft deletes)
- Indexes on `deleted_at`, `created_at`

2. **`group_members` table** (pivot with role)

- `id` (primary key)
- `group_id` (foreign key to groups)
- `user_id` (foreign key to users)
- `role` (enum: 'group_admin', 'group_member')
- `joined_at` (timestamp)
- `created_at`, `updated_at`
- Unique constraint on (`group_id`, `user_id`)
- Indexes on `group_id`, `user_id`, `role`

3. **`group_audit_logs` table**

- `id` (primary key)
- `group_id` (foreign key to groups)
- `user_id` (foreign key to users, nullable - for system actions)
- `action` (string: 'created', 'updated', 'deleted', 'restored', 'member_joined', 'member_left', 'member_promoted', 'member_demoted')
- `changes` (jsonb - stores before/after values for updates)
- `metadata` (jsonb - additional context)
- `created_at`
- Indexes on `group_id`, `user_id`, `action`, `created_at`

4. **`roles` table** (for system-wide roles)

- `id` (primary key)
- `name` (string, unique: 'admin', 'user')
- `display_name` (string)
- `created_at`, `updated_at`

5. **`user_roles` table** (pivot)

- `id` (primary key)
- `user_id` (foreign key to users)
- `role_id` (foreign key to roles)
- `created_at`, `updated_at`
- Unique constraint on (`user_id`, `role_id`)
- Indexes on `user_id`, `role_id`

### Models

1. **`Group` model** (`app/Models/Group.php`)

- Soft deletes trait
- Relationships: `members()`, `groupMembers()`, `auditLogs()`, `groupAdmins()`
- Scopes: `withMembers()`, `withAuditLogs()`
- Accessors for member counts

2. **`GroupMember` model** (`app/Models/GroupMember.php`)

- Relationships: `group()`, `user()`
- Constants for roles: `ROLE_GROUP_ADMIN`, `ROLE_GROUP_MEMBER`

3. **`GroupAuditLog` model** (`app/Models/GroupAuditLog.php`)

- Relationships: `group()`, `user()`
- Constants for actions
- Casts for `changes` and `metadata` (array)

4. **`Role` model** (`app/Models/Role.php`)

- Relationships: `users()`
- Constants for role names

5. **Update `User` model**

- Relationships: `groups()`, `groupMemberships()`, `roles()`, `groupAuditLogs()`
- Helper methods: `isAdmin()`, `hasRole()`, `isGroupAdmin()`

### Services

1. **`GroupService`** (`app/Services/GroupService.php`)

- `createGroup()` - Creates group and adds creator as admin
- `updateGroup()` - Updates group properties (with rate limiting check)
- `deleteGroup()` - Soft deletes group
- `restoreGroup()` - Restores soft-deleted group (admin only)
- `addMemberToGroup()` - Adds user to group
- `removeMemberFromGroup()` - Removes user from group
- `promoteMemberToAdmin()` - Changes member role to admin
- `demoteAdminToMember()` - Changes admin role to member
- `checkCreateRateLimit()` - Validates 5-minute rate limit
- `checkUpdateRateLimit()` - Validates 10-second rate limit

2. **`GroupAuditLogService`** (`app/Services/GroupAuditLogService.php`)

- `logGroupAction()` - Creates audit log entry
- `getGroupAuditLogs()` - Retrieves audit logs for a group
- Helper methods for different action types

### Controllers

1. **`GroupController`** (`app/Http/Controllers/Api/V1/GroupController.php`)

- `index()` - List groups (with pagination, filtering)
- `store()` - Create group (rate limited: 1 per 5 minutes)
- `show()` - Get group details (with members, audit logs optional)
- `update()` - Update group (rate limited: 1 per 10 seconds, admin only)
- `destroy()` - Delete group (soft delete, admin only)

2. **`AdminGroupController`** (`app/Http/Controllers/Api/V1/AdminGroupController.php`)

- `restore()` - Restore soft-deleted group (admin only)
- `indexDeleted()` - List soft-deleted groups (admin only)

3. **`GroupAuditLogController`** (`app/Http/Controllers/Api/V1/GroupAuditLogController.php`)

- `index()` - Get audit logs for a group (with pagination, filtering)

### Policies

1. **`GroupPolicy`** (`app/Policies/GroupPolicy.php`)

- `view()` - Any authenticated user can view groups
- `create()` - Any authenticated user can create (rate limit enforced separately)
- `update()` - Only group admins can update
- `delete()` - Only group admins can delete
- `restore()` - Only system admins can restore
- `viewAuditLogs()` - Group admins and system admins can view

### Form Requests

1. **`StoreGroupRequest`** (`app/Http/Requests/Group/StoreGroupRequest.php`)

- Validation: `friendly_name` (required, string, max:255), optional fields with URL validation

2. **`UpdateGroupRequest`** (`app/Http/Requests/Group/UpdateGroupRequest.php`)

- Same validation as store, all fields optional

### API Resources

1. **`GroupResource`** (`app/Http/Resources/GroupResource.php`)

- Includes: id, friendly_name, description, group_location, website_link, discord_link, slack_link, created_at, updated_at
- Optional: member_count, members (when requested), audit_log_count

2. **`GroupMemberResource`** (`app/Http/Resources/GroupMemberResource.php`)

- Includes: id, user (nested UserResource), role, joined_at

3. **`GroupAuditLogResource`** (`app/Http/Resources/GroupAuditLogResource.php`)

- Includes: id, action, changes, metadata, user (nested, nullable), created_at

### Rate Limiting

1. **Custom Middleware** (`app/Http/Middleware/ThrottleGroupCreation.php`)

- Enforces 1 group creation per 5 minutes per user
- Uses Redis cache with key: `group_creation:{user_id}`

2. **Custom Middleware** (`app/Http/Middleware/ThrottleGroupUpdate.php`)

- Enforces 1 group update per 10 seconds per user
- Uses Redis cache with key: `group_update:{user_id}:{group_id}`

### Scheduled Commands

1. **`HardDeleteExpiredGroupsCommand`** (`app/Console/Commands/HardDeleteExpiredGroupsCommand.php`)

- Runs daily (via Laravel Scheduler)
- Hard deletes groups soft-deleted more than 12 months ago
- Logs deletion actions

### Routes

Add to `routes/api.php`:

- `GET /api/v1/groups` - List groups
- `POST /api/v1/groups` - Create group (rate limited)
- `GET /api/v1/groups/{id}` - Get group
- `PUT/PATCH /api/v1/groups/{id}` - Update group (rate limited, admin only)
- `DELETE /api/v1/groups/{id}` - Delete group (admin only)
- `GET /api/v1/groups/{id}/audit-logs` - Get audit logs (admin only)
- `POST /api/v1/admin/groups/{id}/restore` - Restore group (system admin only)
- `GET /api/v1/admin/groups/deleted` - List deleted groups (system admin only)

### Testing

1. **Feature Tests** (`tests/Feature/Api/V1/GroupControllerTest.php`)

- Test group creation with rate limiting
- Test group update with rate limiting and authorization
- Test group deletion (soft delete)
- Test group restore (admin only)
- Test audit log creation for all actions
- Test member joining/leaving/promoting

2. **Unit Tests**

- `GroupServiceTest.php` - Test all service methods
- `GroupAuditLogServiceTest.php` - Test audit logging
- `GroupPolicyTest.php` - Test authorization rules

3. **Integration Tests**

- Test rate limiting with Redis
- Test soft delete and hard delete workflow
- Test audit log querying

### Configuration

1. **`config/groups.php`**

- Rate limit settings (create: 5 minutes, update: 10 seconds)
- Hard delete retention period (12 months)
- Group member role constants

## Implementation Order

1. Database migrations (groups, group_members, group_audit_logs, roles, user_roles)
2. Models with relationships
3. Role system (Role model, user_roles pivot, User model updates)
4. GroupService with business logic
5. GroupAuditLogService
6. Policies for authorization
7. Form Requests for validation
8. API Resources
9. Controllers
10. Rate limiting middleware
11. Routes
12. Scheduled command for hard deletes
13. Comprehensive tests
14. Update API documentation (OpenAPI/Scribe annotations)

## Key Design Decisions

1. **Role System**: Using a flexible role-based system for system admins, with group-specific roles stored in the pivot table
2. **Audit Logging**: JSONB columns for flexible change tracking, separate table for queryability