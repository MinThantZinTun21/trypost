# Facebook Insights, read from daily snapshots

ADR 0001 removed TryPost's analytics. The Owner now deliberately wants Insights back, for Facebook only: how their Facebook Page grows and reaches people, and how each Post the app published there performs. TikTok and YouTube stay without Insights.

The app does not ask Facebook each time the dashboard opens. A daily job (03:00 UTC, and a 90-day backfill the first time) stores one Insights snapshot per Page per day, and lifetime Post insights for each Post published in the last 30 days. The Owner can ask for a fresh fetch at most once an hour. The dashboard and the Assistant's MCP tools read only what is stored.

## Considered Options

- **Live Graph calls on every page view**: rejected. The dashboard would be slow, it would spend the Page's Business Use Case rate limit (the same limit publishing needs), and history beyond Facebook's 93-day window would be lost.
- **Bring back TryPost's analytics module**: rejected. It covered every platform, depended on removed packages and teams, and read metrics Facebook has since retired.
- **Store raw Graph responses**: rejected. A typed column per Insight keeps queries portable across PostgreSQL and MySQL and makes a retired metric an explicit migration, not silent nulls.

## Consequences

- Insights lag by up to a day unless the Owner uses Refresh now.
- Only the metrics Facebook still answers on Graph v25 are stored. Page follower demographics come back empty and are left out. Comment and share counts need `pages_read_user_content`, which this app does not request, so Post insights do not have them.
- Reach over a range is the sum of daily reach, so a person reached on two days counts twice. The dashboard says so.
- Facebook retires metrics without much notice. When a fetch fails, the dashboard shows the error and keeps the stored history; it does not send a Notification.
