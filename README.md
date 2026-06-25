# D2C Brand Department Metrics Dashboard

Laravel project for a D2C brand analytics assignment covering Marketing and Operations metrics. It includes seeded analytics data, API endpoints, separate Blade dashboards, charts, date filters, operations controls, and an embedded AI assistant.

## Current Scope

- Marketing tables: `ad_platforms`, `campaigns`, `campaign_daily_metrics`
- Operations tables: `couriers`, `rto_reasons`, `orders`, `shipments`, `lost_cases`
- Seeder: three months of daily D2C data across 2 ad platforms, 6 campaigns, 4 couriers, RTO reasons, delivered/RTO/lost shipments, claim values, spend, revenue, impressions, clicks, and conversions
- Separate light UI pages for Marketing and Operations
- Order control page for filtering orders and updating status
- Detail pages for campaigns, shipments, RTO reasons, and lost cases
- RTO reason controls to add, edit, and delete reason labels
- Embedded AI assistant that receives the current dashboard data as context and stores short session history
- Floating AI chat drawer available across dashboard/detail pages
- Full AI Assistant page with saved chat sessions
- Chat persistence tables: `ai_chat_sessions`, `ai_chat_messages`
- Local database: SQLite by default

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm
- SQLite for local development, or MySQL/PostgreSQL if you update `.env`

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open:

- `http://127.0.0.1:8000/marketing`
- `http://127.0.0.1:8000/operations`
- `http://127.0.0.1:8000/orders`
- `http://127.0.0.1:8000/campaigns`
- `http://127.0.0.1:8000/shipments`
- `http://127.0.0.1:8000/rto-reasons`
- `http://127.0.0.1:8000/lost-cases`
- `http://127.0.0.1:8000/assistant`

The project currently uses SQLite:

```env
DB_CONNECTION=sqlite
```

For OpenRouter-powered assistant responses, add:

```env
OPENAI_API_KEY=your_openrouter_key
OPENAI_MODEL=openai/gpt-4o-mini
OPENAI_BASE_URL=https://openrouter.ai/api/v1
```

The assistant uses the OpenAI-compatible `/chat/completions` format, so you can also point `OPENAI_BASE_URL` to another compatible provider:

```env
"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"
OPENAI_MODEL=gpt-4o-mini
OPENAI_BASE_URL=https://api.openai.com/v1
```

If `OPENAI_API_KEY` is empty, the assistant still returns a local fallback answer based on the current dashboard context so the demo remains functional.

## Seeded Data Design

The seeder is intentionally analytics-friendly:

- Marketing data is stored daily at campaign level, which makes date-range filters, platform filters, ROAS, CAC, CPM, CTR, CPC, and trend charts easy to calculate.
- Campaigns belong to ad platforms, keeping Meta and Google reporting normalized.
- Orders are separate from shipments so revenue/order dimensions can be filtered independently from logistics performance.
- Shipments carry courier, delivery, RTO, and shipping speed fields for OTD %, RTO %, average ship time, and courier scorecards.
- Lost cases are tracked separately with claim status and recovered amount for the `/api/ops/lost-cases` endpoint.

## API Endpoints

Marketing:

- `GET /api/marketing/overview`
- `GET /api/marketing/platform?platform=meta|google&from=&to=`
- `GET /api/marketing/campaigns`
- `GET /api/marketing/trends`

Operations:

- `GET /api/ops/overview`
- `GET /api/ops/couriers`
- `GET /api/ops/rto?reason=`
- `GET /api/ops/lost-cases`
- `GET /api/ops/trends`
- `GET /api/ops/shipments`
- `GET /api/ops/orders`
- `PATCH /api/ops/orders/{order}`
- `GET /api/ops/rto-reasons`
- `POST /api/ops/rto-reasons`
- `PATCH /api/ops/rto-reasons/{reason}`
- `DELETE /api/ops/rto-reasons/{reason}`

AI:

- `POST /assistant/query`
- `GET /assistant/sessions`
- `POST /assistant/sessions`
- `GET /assistant/sessions/{sessionKey}/messages`

## UI Decisions

- The UI uses a light, minimal SaaS dashboard style instead of a dark generated-looking layout.
- Marketing and Operations are separate routes so the department split is clear to reviewers.
- Cards show top-level KPIs, charts show trends, and tables expose campaign/courier details.
- The Operations page includes RTO reason management because RTO analysis often requires maintaining clean reason labels.
- The Orders page gives backend-style controls for filtering records and updating shipment status.
- Campaigns, Shipments, RTO Reasons, and Lost Cases have separate detail pages so the project feels like a real internal tool rather than only a KPI screen.
- The AI assistant is a floating chat drawer plus a separate full chat page.
- Chat sessions are stored in database tables instead of only browser memory, so conversations can be revisited and used as short-term memory.
- Responses are rendered with paragraph and bullet formatting for readability.

## Example Metric Queries

Marketing overview:

```sql
SELECT
    SUM(spend) AS total_spend,
    SUM(revenue) AS revenue,
    ROUND(SUM(revenue) / NULLIF(SUM(spend), 0), 2) AS blended_roas,
    ROUND(SUM(spend) / NULLIF(SUM(conversions), 0), 2) AS blended_cac,
    SUM(conversions) AS conversions
FROM campaign_daily_metrics
WHERE metric_date BETWEEN '2026-04-01' AND '2026-06-24';
```

Courier scorecard:

```sql
SELECT
    couriers.name,
    COUNT(shipments.id) AS orders,
    ROUND(100.0 * SUM(CASE WHEN shipments.delivered_on <= shipments.expected_delivery_on THEN 1 ELSE 0 END) / COUNT(shipments.id), 2) AS otd_percent,
    ROUND(100.0 * SUM(CASE WHEN shipments.status = 'rto' THEN 1 ELSE 0 END) / COUNT(shipments.id), 2) AS rto_percent,
    SUM(CASE WHEN shipments.status = 'lost' THEN 1 ELSE 0 END) AS lost_count
FROM shipments
JOIN couriers ON couriers.id = shipments.courier_id
WHERE shipments.shipped_on BETWEEN '2026-04-01' AND '2026-06-24'
GROUP BY couriers.id, couriers.name;
```

## Optimised Query Note

Target query: courier performance filtered by date range.

Before adding the composite index, a database may scan the full `shipments` table and then group by courier:

```text
EXPLAIN: SCAN shipments; SEARCH couriers USING INTEGER PRIMARY KEY; USE TEMP B-TREE FOR GROUP BY
```

After adding the migration index:

```php
$table->index(['courier_id', 'shipped_on']);
```

Expected explain shape:

```text
EXPLAIN: SEARCH shipments USING INDEX shipments_courier_id_shipped_on_index; SEARCH couriers USING INTEGER PRIMARY KEY
```

Why this helps: the dashboard repeatedly filters operations metrics by date and courier. The composite index keeps the scorecard query aligned with that access pattern.

## Verification

```bash
php artisan test
npm run build
```
