
## Requirements

- PHP 8.3+
- Composer
- Node.js and npm ,or MySQL/PostgreSQL if you update `.env`

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


Frontend entry files:

- `resources/js/app.js` AI assistant UI
- `resources/js/dashboard-charts.js` renders the dashboard charts

The project currently uses SQLite:

```env
DB_CONNECTION=sqlite

OPENAI_API_KEY="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"
OPENAI_MODEL=gpt-4o-mini
OPENAI_BASE_URL=https://api.openai.com/v1
```

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
