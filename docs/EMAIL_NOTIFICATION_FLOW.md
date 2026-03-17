# Priority Portal Email Notification Flow

This diagram maps the current workflow to the email moments we need to audit.

Current implementation already sends workflow emails for:

- `task_created`
- `task_approved`
- `task_rejected`
- `task_revision_requested`
- `task_delivered`
- `task_completed`
- `retention_day_300`

Recipients currently fan out to:

- submitter
- assigned owners

Each recipient only receives the email if that event is enabled in their notification preferences.

## Workflow Audit Diagram

```mermaid
flowchart TD
    A["Submitter creates request<br/>Uploads files, describes needs,<br/>sets requested deadline"] --> B{"Needs meeting?"}
    B -->|Yes| C["Offer meeting scheduling option"]
    B -->|No| D["Request enters pending review queue"]
    C --> D

    D --> E["Email: task_created<br/>To: submitter + assigned owners"]
    E --> F{"Manager approval?"}

    F -->|Approved| G["Status: approved"]
    G --> H["Email: task_approved<br/>To: submitter + assigned owners"]
    H --> I["Worker starts task<br/>Status: in_progress"]

    F -->|Needs clarification| J["Status: not_approved"]
    J --> K["Email: task_rejected<br/>To: submitter + assigned owners"]
    K --> L["Submitter revises request"]
    L --> M{"Needs meeting now?"}
    M -->|Yes| C
    M -->|No| D

    I --> N["Worker delivers work product<br/>Status: delivered"]
    N --> O["Email: task_delivered<br/>To: submitter + assigned owners"]
    O --> P{"Submitter accepts?"}

    P -->|Needs changes| Q["Status: revision_requested"]
    Q --> R["Email: task_revision_requested<br/>To: submitter + assigned owners"]
    R --> I

    P -->|Accepted| S["Status: completed"]
    S --> T["Email: task_completed<br/>To: submitter + assigned owners"]
    T --> U["Archive after retention window"]
    U --> V["Day 300 reminder email<br/>To: submitter if enabled"]
```

## Audit Notes

- `task_created` currently goes to both submitter and owners. If you want reviewer-only emails here, we should split recipients by event.
- `task_rejected` is the clarification loop email. That matches your workflow where the client goes back to the meeting-or-revise step.
- `task_delivered` and `task_revision_requested` currently notify both sides. If you want client-only or worker-only routing, we should add recipient rules per event.
- There is no separate email yet for `in_progress` or meeting scheduling. We can add those once you confirm the audit.
