# Unified Conversation View — Phase B Spec

**Status:** Draft — awaiting product review
**Punchlist item:** Messages/Notes UI — user wants unified conversation view instead of separate cards

---

## Current state

Two identical-schema tables (`pq_task_messages`, `pq_task_comments`) served by separate REST endpoints and rendered in separate tabs:

| | Messages | Notes |
|--|----------|-------|
| Table | `pq_task_messages` | `pq_task_comments` |
| Order | ASC (oldest first) | DESC (newest first) |
| @mentions | Yes → notifications + deferred email | No |
| Email on post | Via `notify_mentions()` | None |
| Tab label | "Messages" | "Sticky Notes" |
| UI | Append new items | Prepend new items |

Both tables have identical columns: `id`, `task_id`, `author_id`, `body`, `created_at`.

---

## Proposed design

### Single chronological stream

Merge both item types into one scrollable list, sorted **ascending** (oldest first, like a chat). Each item renders with:

- Author name + avatar initial
- Timestamp (relative: "2h ago", absolute on hover)
- Body text (with @mention highlighting)
- Type badge: small "📌 Note" label on sticky notes (messages have no badge — they're the default)
- `.mine` / `.theirs` alignment (existing pattern)

### Compose area

One textarea at the bottom with a toggle:

```
[Message ▾]  [textarea]  [Send]
```

The dropdown (or segmented toggle) switches between:
- **Message** — sends notifications to @mentioned users (default)
- **Note** — pinned context, no notifications, shown with 📌 badge

### Tab consolidation

Replace the two tabs ("Messages" + "Notes") with a single **"Conversation"** tab. Badge shows total unread count.

The freed tab slot could later house "Activity" (status history log) or be removed to simplify the workspace.

---

## API options

### Option A: Unified endpoint (recommended)

New endpoint: `GET /pq/v1/tasks/{id}/conversation`

Returns a single sorted array:

```json
{
  "items": [
    { "id": 1, "type": "message", "author_id": 4, "author_name": "Read", "body": "...", "created_at": "..." },
    { "id": 2, "type": "note",    "author_id": 4, "author_name": "Read", "body": "...", "created_at": "..." }
  ]
}
```

Server merges both tables with `UNION ALL`, sorts by `created_at ASC, id ASC`, attaches author names. One round-trip.

POST stays split (different behavior):
- `POST /tasks/{id}/messages` — existing, triggers @mention notifications
- `POST /tasks/{id}/notes` — existing, no notifications

Both return the new item with `type` field so the client can append it to the unified stream.

### Option B: Client-side merge

Keep existing GET endpoints. Client fetches both, merges, sorts. Two round-trips but zero API changes.

**Recommendation:** Option A. Cleaner, one round-trip, server controls sort order.

---

## Data model

**No schema migration needed.** Both tables stay as-is. The unified endpoint reads from both via `UNION ALL`. No data loss, full backward compatibility.

If we later want to consolidate into one table, add a `type ENUM('message','note')` column — but that's optional cleanup, not required for this feature.

---

## Decisions needed (for product review)

1. **Stream order:** Ascending (chat-style, oldest first) or descending (feed-style, newest first)?
   → Recommendation: ascending (matches Messages today, natural conversation flow)

2. **Note visibility:** Should notes appear inline in the stream, or in a collapsible "pinned" section at the top?
   → Recommendation: inline with badge — simpler, one mental model

3. **Compose default:** Message (with notifications) or Note (silent)?
   → Recommendation: Message as default — most common action

4. **@mentions in notes:** Should notes gain @mention support (notifications), or stay silent?
   → Recommendation: stay silent — preserves the "scratchpad" intent

5. **Existing data:** Merge historical messages + notes into one stream immediately, or only show unified view for new items?
   → Recommendation: merge all history — `UNION ALL` makes this trivial

---

## Implementation sequence

| Step | Work | Touches |
|------|------|---------|
| B1 | This spec — product review | — |
| B2 | `GET /tasks/{id}/conversation` endpoint | class-wp-pq-api.php |
| B3 | Unified stream renderer + compose toggle | admin-queue.js |
| B4 | Merge tabs, update portal HTML | class-wp-pq-portal.php |
| B5 | a11y: live region for new items, keyboard focus | admin-queue.js, CSS |

---

## Out of scope

- Activity/history log (separate feature, different data source)
- Threaded replies (future — would need `parent_id` column)
- Rich text / markdown rendering (future)
- File attachments in conversation (removed in v0.27.0)
