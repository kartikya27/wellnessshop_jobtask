import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

const state = {
    department: 'marketing',
    charts: {},
    dashboardContext: {},
};

const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });
const number = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

const dashboardApp = document.querySelector('#dashboard-app');
const ordersApp = document.querySelector('#orders-app');
const detailPageApp = document.querySelector('#detail-page-app');
const assistantWidget = document.querySelector('#assistant-widget');
const assistantPageApp = document.querySelector('#assistant-page-app');

if (dashboardApp) bootDashboard();
if (ordersApp) bootOrders();
if (detailPageApp) bootDetailPage();
if (assistantWidget) bootAssistantWidget();
if (assistantPageApp) bootAssistantPage();

function bootDashboard() {
    state.department = dashboardApp.dataset.department || 'marketing';
    setDefaultDates('#from-date', '#to-date');
    bindDashboardEvents();
    switchDepartment();
    loadDashboard();
}

function bootOrders() {
    setDefaultDates('#orders-from-date', '#orders-to-date');
    document.querySelector('#order-filter').addEventListener('submit', (event) => {
        event.preventDefault();
        loadOrders();
    });
    loadOrders();
}

function bootDetailPage() {
    setDefaultDates('#detail-from-date', '#detail-to-date');
    document.querySelector('#detail-filter').addEventListener('submit', (event) => {
        event.preventDefault();
        loadDetailPage();
    });
    document.querySelector('#detail-rto-reason-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        saveDetailRtoReason();
    });
    loadDetailPage();
}

function bindDashboardEvents() {
    document.querySelector('#date-filter').addEventListener('submit', (event) => {
        event.preventDefault();
        loadDashboard();
    });

    document.querySelector('#rto-reason-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        saveRtoReason();
    });

    document.querySelector('#rto-reason-reset')?.addEventListener('click', resetRtoReasonForm);
}

function bootAssistantWidget() {
    const drawer = document.querySelector('#assistant-drawer');
    const input = document.querySelector('#assistant-question');
    const messagesBox = document.querySelector('#assistant-messages');
    document.querySelector('#assistant-toggle').addEventListener('click', () => drawer.classList.toggle('hidden'));
    document.querySelector('#assistant-close').addEventListener('click', () => drawer.classList.add('hidden'));
    document.querySelector('#assistant-new-chat').addEventListener('click', () => {
        localStorage.removeItem('activeAssistantSessionKey');
        messagesBox.innerHTML = '';
        appendAssistantMessage(messagesBox, 'assistant', 'New chat started. Ask me about the current page or dashboard data.');
    });

    document.querySelector('#assistant-form').addEventListener('submit', (event) => {
        event.preventDefault();
        askAssistant({
            input,
            messagesBox,
            department: assistantDepartment(),
            context: assistantContext(),
        });
    });

    assistantWidget.querySelectorAll('.ai-suggestion').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.textContent.trim();
            askAssistant({
                input,
                messagesBox,
                department: assistantDepartment(),
                context: assistantContext(),
            });
        });
    });

    appendAssistantMessage(messagesBox, 'assistant', 'Ask me about the current page. I can explain metrics, spot weak areas, and suggest next actions.');
}

async function bootAssistantPage() {
    setAssistantPageWelcome();
    await loadAssistantSessions();

    document.querySelector('#assistant-page-new-chat').addEventListener('click', async () => {
        localStorage.removeItem('activeAssistantSessionKey');
        renderAssistantMessages(document.querySelector('#assistant-page-messages'), []);
        await loadAssistantSessions();
    });

    document.querySelector('#assistant-page-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = document.querySelector('#assistant-page-question').value;
        const department = inferDepartmentFromQuestion(question, document.querySelector('#assistant-page-department').value);
        document.querySelector('#assistant-page-department').value = department;

        askAssistant({
            input: document.querySelector('#assistant-page-question'),
            messagesBox: document.querySelector('#assistant-page-messages'),
            department,
            context: await buildAssistantPageContext(department),
            afterAnswer: loadAssistantSessions,
        });
    });
}

function switchDepartment() {
    document.querySelectorAll('[data-panel]').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.panel !== state.department);
    });
}

async function loadDashboard() {
    showAlert('Loading dashboard metrics...', 'neutral');

    try {
        if (state.department === 'marketing') {
            await loadMarketing();
        } else {
            await loadOperations();
        }
    } catch (error) {
        showAlert(error.message || 'Unable to load dashboard data.', 'danger');
    }
}

async function loadMarketing() {
    const [overview, trends, campaigns, meta, google] = await Promise.all([
        getJson('/api/marketing/overview'),
        getJson('/api/marketing/trends'),
        getJson('/api/marketing/campaigns'),
        getJson('/api/marketing/platform?platform=meta'),
        getJson('/api/marketing/platform?platform=google'),
    ]);

    state.dashboardContext = { overview, trends, campaigns, platforms: [meta, google], filters: currentFilters() };

    renderCards('#marketing-cards', [
        ['Spend', money.format(overview.data.total_spend), 'Total media investment'],
        ['Revenue', money.format(overview.data.revenue), 'Attributed sales'],
        ['ROAS', `${overview.data.blended_roas}x`, 'Revenue per rupee spent'],
        ['CAC', money.format(overview.data.blended_cac), 'Cost per conversion'],
        ['Conversions', number.format(overview.data.conversions), 'Total purchases'],
    ]);
    renderPlatformCards([meta.data, google.data]);
    renderCampaigns(campaigns.data);
    renderMarketingChart(trends.data);

    const alerts = [];
    if (overview.alerts.roas_below_threshold) alerts.push('Blended ROAS is below the 2.0x threshold.');
    if (overview.alerts.cac_above_threshold) alerts.push('Blended CAC is above the INR 800 threshold.');
    showAlert(alerts.length ? alerts.join(' ') : 'Marketing metrics are healthy for the selected range.', alerts.length ? 'warning' : 'success');
}

async function loadOperations() {
    const [overview, trends, couriers, rto, lostCases, reasons] = await Promise.all([
        getJson('/api/ops/overview'),
        getJson('/api/ops/trends'),
        getJson('/api/ops/couriers'),
        getJson('/api/ops/rto'),
        getJson('/api/ops/lost-cases'),
        getJson('/api/ops/rto-reasons', false),
    ]);

    state.dashboardContext = { overview, trends, couriers, rto, lostCases, rtoReasons: reasons, filters: currentFilters() };

    renderCards('#ops-cards', [
        ['Orders', number.format(overview.data.total_orders), 'Shipped orders'],
        ['Delivered', number.format(overview.data.delivered), 'Completed deliveries'],
        ['RTO', `${overview.data.rto_rate}%`, 'Return to origin rate'],
        ['OTD', `${overview.data.otd_percent}%`, 'On-time delivery'],
        ['Lost', number.format(overview.data.lost_cases), 'Lost shipments'],
        ['Avg Ship', `${overview.data.avg_ship_time_hours}h`, 'Order to shipment'],
    ]);
    renderCouriers(couriers.data);
    renderLostCases(lostCases.data);
    renderOpsChart(trends.data);
    renderRtoChart(rto.data);
    renderRtoReasons(reasons.data);

    const alerts = [];
    if (overview.alerts.rto_above_threshold) alerts.push('RTO is above the 10% threshold.');
    if (overview.alerts.otd_below_threshold) alerts.push('OTD is below the 90% threshold.');
    showAlert(alerts.length ? alerts.join(' ') : 'Operations metrics are within service thresholds.', alerts.length ? 'warning' : 'success');
}

async function getJson(path, withDates = true) {
    let url = path;

    if (withDates) {
        const params = new URLSearchParams(currentFilters());
        url = `${path}${path.includes('?') ? '&' : '?'}${params.toString()}`;
    }

    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Request failed: ${path}`);

    return response.json();
}

async function sendJson(path, method, payload) {
    const response = await fetch(path, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        throw new Error(body.message || `Request failed: ${path}`);
    }

    return response.json();
}

function renderCards(selector, cards) {
    document.querySelector(selector).innerHTML = cards.map(([label, value, helper]) => `
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-400">${escapeHtml(label)}</p>
            <p class="mt-3 text-2xl font-semibold text-stone-950">${escapeHtml(String(value))}</p>
            <p class="mt-2 text-sm text-stone-500">${escapeHtml(helper)}</p>
        </article>
    `).join('');
}

function renderPlatformCards(platforms) {
    document.querySelector('#platform-cards').innerHTML = platforms.map((platform) => `
        <article class="rounded-lg border border-stone-200 bg-stone-50 p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-stone-950">${escapeHtml(platform.platform_name)}</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-stone-400">${escapeHtml(platform.platform)}</p>
                </div>
                <span class="${Number(platform.roas) < 2 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'} rounded-md px-2 py-1 text-xs font-semibold">${platform.roas}x ROAS</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-stone-500">Spend</dt><dd class="font-medium text-stone-950">${money.format(platform.spend)}</dd></div>
                <div><dt class="text-stone-500">CAC</dt><dd class="font-medium text-stone-950">${money.format(platform.cac)}</dd></div>
                <div><dt class="text-stone-500">CTR</dt><dd class="font-medium text-stone-950">${platform.ctr}%</dd></div>
                <div><dt class="text-stone-500">CPC</dt><dd class="font-medium text-stone-950">${money.format(platform.cpc)}</dd></div>
            </dl>
        </article>
    `).join('');
}

function renderCampaigns(campaigns) {
    document.querySelector('#campaign-table').innerHTML = campaigns.map((campaign) => `
        <tr>
            <td class="font-medium text-stone-950">${escapeHtml(campaign.name)}</td>
            <td class="uppercase text-stone-500">${escapeHtml(campaign.platform)}</td>
            <td>${statusPill(campaign.status)}</td>
            <td class="text-right">${money.format(campaign.spend)}</td>
            <td class="text-right ${Number(campaign.roas) < 2 ? 'text-rose-700' : 'text-emerald-700'}">${campaign.roas}x</td>
        </tr>
    `).join('');
}

function renderCouriers(couriers) {
    document.querySelector('#courier-table').innerHTML = couriers.map((courier) => `
        <tr>
            <td><div class="font-medium text-stone-950">${escapeHtml(courier.name)}</div><div class="text-xs text-stone-400">${escapeHtml(courier.code)}</div></td>
            <td class="text-right">${number.format(courier.orders)}</td>
            <td class="text-right ${Number(courier.otd_percent) < 90 ? 'text-amber-700' : 'text-emerald-700'}">${courier.otd_percent}%</td>
            <td class="text-right ${Number(courier.rto_percent) > 10 ? 'text-rose-700' : 'text-stone-700'}">${courier.rto_percent}%</td>
            <td class="text-right">${number.format(courier.lost_count)}</td>
            <td class="text-right font-semibold text-stone-950">${courier.performance_score}</td>
        </tr>
    `).join('');
}

function renderLostCases(cases) {
    document.querySelector('#lost-case-table').innerHTML = cases.slice(0, 12).map((lostCase) => `
        <tr>
            <td class="font-medium text-stone-950">${escapeHtml(lostCase.case_number)}</td>
            <td>${escapeHtml(lostCase.order_number)}</td>
            <td>${escapeHtml(lostCase.courier)}</td>
            <td>${statusPill(lostCase.status)}</td>
            <td class="text-right">${lostCase.claim_filed ? money.format(lostCase.claim_amount) : 'Not filed'}</td>
            <td class="text-right text-emerald-700">${money.format(lostCase.amount_recovered)}</td>
        </tr>
    `).join('') || tableEmpty(6, 'No lost cases in this date range.');
}

function renderRtoReasons(reasons) {
    const list = document.querySelector('#rto-reason-list');
    if (!list) return;

    list.innerHTML = reasons.map((reason) => `
        <article class="flex items-center justify-between gap-3 rounded-lg border border-stone-200 bg-stone-50 p-3">
            <div>
                <p class="text-sm font-semibold text-stone-950">${escapeHtml(reason.reason)}</p>
                <p class="text-xs text-stone-500">${escapeHtml(reason.category)} · ${reason.is_controllable ? 'Controllable' : 'Courier/External'}</p>
            </div>
            <div class="flex gap-2">
                <button class="btn-secondary !px-2 !py-1 text-xs" type="button" data-edit-rto="${reason.id}">Edit</button>
                <button class="btn-secondary !px-2 !py-1 text-xs" type="button" data-delete-rto="${reason.id}">Delete</button>
            </div>
        </article>
    `).join('');

    list.querySelectorAll('[data-edit-rto]').forEach((button) => {
        button.addEventListener('click', () => {
            const reason = reasons.find((item) => String(item.id) === button.dataset.editRto);
            document.querySelector('#rto-reason-id').value = reason.id;
            document.querySelector('#rto-reason-name').value = reason.reason;
            document.querySelector('#rto-reason-category').value = reason.category;
            document.querySelector('#rto-reason-controllable').checked = Boolean(reason.is_controllable);
        });
    });

    list.querySelectorAll('[data-delete-rto]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!confirm('Delete this RTO reason? Existing shipments will keep working without this label.')) return;
            await fetch(`/api/ops/rto-reasons/${button.dataset.deleteRto}`, { method: 'DELETE', headers: { Accept: 'application/json' } });
            resetRtoReasonForm();
            loadDashboard();
        });
    });
}

async function saveRtoReason() {
    const id = document.querySelector('#rto-reason-id').value;
    const payload = {
        reason: document.querySelector('#rto-reason-name').value,
        category: document.querySelector('#rto-reason-category').value,
        is_controllable: document.querySelector('#rto-reason-controllable').checked,
    };

    const path = id ? `/api/ops/rto-reasons/${id}` : '/api/ops/rto-reasons';
    const method = id ? 'PATCH' : 'POST';
    await sendJson(path, method, payload);
    resetRtoReasonForm();
    loadDashboard();
}

function resetRtoReasonForm() {
    document.querySelector('#rto-reason-id').value = '';
    document.querySelector('#rto-reason-name').value = '';
    document.querySelector('#rto-reason-category').value = '';
    document.querySelector('#rto-reason-controllable').checked = false;
}

async function askAssistant({ input, messagesBox, department, context, afterAnswer = null }) {
    const question = input.value.trim();
    if (!question) return;

    appendAssistantMessage(messagesBox, 'user', question);
    input.value = '';
    appendAssistantMessage(messagesBox, 'assistant', 'Thinking through the current dashboard data...', true);

    try {
        const response = await sendJson('/assistant/query', 'POST', {
            department,
            question,
            context,
            session_key: localStorage.getItem('activeAssistantSessionKey'),
        });

        localStorage.setItem('activeAssistantSessionKey', response.session_key);
        removePendingAssistantMessage(messagesBox);
        appendAssistantMessage(messagesBox, 'assistant', response.answer);
        if (afterAnswer) await afterAnswer();
    } catch (error) {
        removePendingAssistantMessage(messagesBox);
        appendAssistantMessage(messagesBox, 'assistant', error.message || 'I could not answer that right now.');
    }
}

function appendAssistantMessage(box, role, content, pending = false) {
    box.insertAdjacentHTML('beforeend', `
        <div ${pending ? 'data-pending-assistant' : ''} class="rounded-lg ${role === 'user' ? 'ml-10 bg-stone-100' : 'mr-10 bg-emerald-50 border border-emerald-100'} p-3">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] ${role === 'user' ? 'text-stone-500' : 'text-emerald-700'}">${role === 'user' ? 'You' : 'Assistant'}</p>
            <div class="assistant-message-body mt-2 leading-6 text-stone-700">${formatAssistantContent(content)}</div>
        </div>
    `);
    box.scrollTop = box.scrollHeight;
}

function removePendingAssistantMessage(box) {
    const messages = box.querySelectorAll('[data-pending-assistant]');
    messages[messages.length - 1]?.remove();
}

async function loadAssistantSessions() {
    const list = document.querySelector('#assistant-session-list');
    if (!list) return;

    const body = await getJson('/assistant/sessions', false);
    list.innerHTML = body.data.map((session) => `
        <button class="rounded-lg border border-stone-200 bg-white p-3 text-left hover:bg-stone-50" data-assistant-session="${session.session_key}" type="button">
            <span class="block text-sm font-semibold text-stone-950">${escapeHtml(session.title)}</span>
            <span class="mt-1 block text-xs capitalize text-stone-500">${escapeHtml(session.department)} · ${escapeHtml(session.last_message_at || 'No messages')}</span>
        </button>
    `).join('') || '<p class="text-sm text-stone-500">No chats yet.</p>';

    list.querySelectorAll('[data-assistant-session]').forEach((button) => {
        button.addEventListener('click', async () => {
            const sessionKey = button.dataset.assistantSession;
            localStorage.setItem('activeAssistantSessionKey', sessionKey);
            const body = await getJson(`/assistant/sessions/${sessionKey}/messages`, false);
            renderAssistantMessages(document.querySelector('#assistant-page-messages'), body.messages);
        });
    });
}

function renderAssistantMessages(box, messages) {
    box.innerHTML = '';
    if (!messages.length) {
        setAssistantPageWelcome();
        return;
    }
    messages.forEach((message) => appendAssistantMessage(box, message.role, message.content));
}

function setAssistantPageWelcome() {
    const box = document.querySelector('#assistant-page-messages');
    if (!box) return;
    box.innerHTML = '';
    appendAssistantMessage(box, 'assistant', 'Ask a question about Marketing or Operations. I will load the latest dashboard metrics before answering.');
}

async function buildAssistantPageContext(department) {
    const [marketing, operations] = await Promise.all([
        fetchMarketingContext(),
        fetchOperationsContext(),
    ]);

    return {
        source: 'assistant_page',
        selected_department: department,
        marketing,
        operations,
    };
}

async function fetchMarketingContext() {
    const [overview, trends, campaigns, meta, google] = await Promise.all([
        getJson('/api/marketing/overview'),
        getJson('/api/marketing/trends'),
        getJson('/api/marketing/campaigns'),
        getJson('/api/marketing/platform?platform=meta'),
        getJson('/api/marketing/platform?platform=google'),
    ]);

    return { overview, trends, campaigns, platforms: [meta, google], filters: currentFiltersForAssistantPage() };
}

async function fetchOperationsContext() {
    const [overview, trends, couriers, rto, lostCases] = await Promise.all([
        getJson('/api/ops/overview'),
        getJson('/api/ops/trends'),
        getJson('/api/ops/couriers'),
        getJson('/api/ops/rto'),
        getJson('/api/ops/lost-cases'),
    ]);

    return { overview, trends, couriers, rto, lostCases, filters: currentFiltersForAssistantPage() };
}

function inferDepartmentFromQuestion(question, fallback) {
    const text = question.toLowerCase();

    if (['platform', 'meta', 'google', 'campaign', 'roas', 'cac', 'cpc', 'ctr', 'spend', 'profitable'].some((word) => text.includes(word))) {
        return 'marketing';
    }

    if (['courier', 'rto', 'shipment', 'delivery', 'lost', 'order', 'otd'].some((word) => text.includes(word))) {
        return 'operations';
    }

    return fallback;
}

function currentFiltersForAssistantPage() {
    const to = new Date();
    to.setDate(to.getDate() - 1);
    const from = new Date(to);
    from.setDate(from.getDate() - 30);

    return {
        from: toInputDate(from),
        to: toInputDate(to),
    };
}

function assistantDepartment() {
    if (dashboardApp) return state.department;

    return assistantWidget.dataset.defaultDepartment || 'operations';
}

function assistantContext() {
    if (dashboardApp) return state.dashboardContext;

    return {
        source: window.location.pathname,
        note: 'Question asked from a detail or operations control page. Use the general project context and available chat history.',
    };
}

async function loadOrders() {
    const params = new URLSearchParams({
        from: document.querySelector('#orders-from-date').value,
        to: document.querySelector('#orders-to-date').value,
        status: document.querySelector('#order-status').value,
        search: document.querySelector('#order-search').value,
    });

    const response = await fetch(`/api/ops/orders?${params.toString()}`, { headers: { Accept: 'application/json' } });
    const body = await response.json();

    document.querySelector('#orders-table').innerHTML = body.data.map((order) => `
        <tr>
            <td><div class="font-medium text-stone-950">${escapeHtml(order.order_number)}</div><div class="text-xs text-stone-400">${escapeHtml(order.tracking_number)}</div></td>
            <td><div>${escapeHtml(order.customer_city)}</div><div class="text-xs text-stone-400">${escapeHtml(order.customer_state)}</div></td>
            <td><div>${escapeHtml(order.courier)}</div><div class="text-xs text-stone-400">${escapeHtml(order.courier_code)}</div></td>
            <td>${statusPill(order.status)}</td>
            <td>${escapeHtml(order.rto_reason || '-')}</td>
            <td class="text-right">${money.format(order.order_value)}</td>
            <td class="text-right">
                <select class="field !w-36 !py-1" data-order-status="${order.id}">
                    ${['delivered', 'rto', 'lost'].map((status) => `<option value="${status}" ${status === order.status ? 'selected' : ''}>${status}</option>`).join('')}
                </select>
            </td>
        </tr>
    `).join('') || tableEmpty(7, 'No orders match these filters.');

    document.querySelectorAll('[data-order-status]').forEach((select) => {
        select.addEventListener('change', async () => {
            await sendJson(`/api/ops/orders/${select.dataset.orderStatus}`, 'PATCH', { status: select.value });
            loadOrders();
        });
    });
}

async function loadDetailPage() {
    const page = detailPageApp.dataset.page;
    const params = new URLSearchParams({
        from: document.querySelector('#detail-from-date').value,
        to: document.querySelector('#detail-to-date').value,
        search: document.querySelector('#detail-search').value,
    });

    if (page === 'campaigns') {
        const body = await getJson(`/api/marketing/campaigns?${params.toString()}`, false);
        renderDetailTable(['Campaign', 'Platform', 'Status', 'Spend', 'ROAS'], body.data.map((row) => [
            `<span class="font-medium text-stone-950">${escapeHtml(row.name)}</span>`,
            `<span class="uppercase text-stone-500">${escapeHtml(row.platform)}</span>`,
            statusPill(row.status),
            money.format(row.spend),
            `<span class="${Number(row.roas) < 2 ? 'text-rose-700' : 'text-emerald-700'}">${row.roas}x</span>`,
        ]), [3, 4]);
        return;
    }

    if (page === 'shipments') {
        const body = await getJson(`/api/ops/shipments?${params.toString()}`, false);
        renderDetailTable(['Tracking', 'Order', 'Courier', 'Status', 'Ship Date', 'Expected', 'Delivered/RTO', 'RTO Reason', 'Cost'], body.data.map((row) => [
            `<span class="font-medium text-stone-950">${escapeHtml(row.tracking_number)}</span>`,
            escapeHtml(row.order_number),
            `${escapeHtml(row.courier)} <span class="text-xs text-stone-400">(${escapeHtml(row.courier_code)})</span>`,
            statusPill(row.status),
            escapeHtml(row.shipped_on),
            escapeHtml(row.expected_delivery_on),
            escapeHtml(row.delivered_on || row.rto_on || '-'),
            escapeHtml(row.rto_reason || '-'),
            money.format(row.shipping_cost),
        ]), [8]);
        return;
    }

    if (page === 'lost-cases') {
        const body = await getJson(`/api/ops/lost-cases?${params.toString()}`, false);
        renderDetailTable(['Case', 'Order', 'Courier', 'Reported', 'Status', 'Claim Filed', 'Claim Amount', 'Recovered'], body.data.map((row) => [
            `<span class="font-medium text-stone-950">${escapeHtml(row.case_number)}</span>`,
            escapeHtml(row.order_number),
            escapeHtml(row.courier),
            escapeHtml(row.reported_on),
            statusPill(row.status),
            row.claim_filed ? 'Yes' : 'No',
            money.format(row.claim_amount),
            `<span class="text-emerald-700">${money.format(row.amount_recovered)}</span>`,
        ]), [6, 7]);
        return;
    }

    const body = await getJson('/api/ops/rto-reasons', false);
    renderDetailTable(['Reason', 'Category', 'Control Type', 'Actions'], body.data.map((row) => [
        `<span class="font-medium text-stone-950">${escapeHtml(row.reason)}</span>`,
        escapeHtml(row.category),
        row.is_controllable ? '<span class="text-emerald-700">Controllable</span>' : '<span class="text-stone-500">Courier/External</span>',
        `<div class="flex justify-end gap-2">
            <button class="btn-secondary !px-2 !py-1 text-xs" data-detail-edit-rto="${row.id}" type="button">Edit</button>
            <button class="btn-secondary !px-2 !py-1 text-xs" data-detail-delete-rto="${row.id}" type="button">Delete</button>
        </div>`,
    ]), [3]);

    document.querySelectorAll('[data-detail-edit-rto]').forEach((button) => {
        button.addEventListener('click', () => {
            const reason = body.data.find((item) => String(item.id) === button.dataset.detailEditRto);
            document.querySelector('#detail-rto-reason-id').value = reason.id;
            document.querySelector('#detail-rto-reason-name').value = reason.reason;
            document.querySelector('#detail-rto-reason-category').value = reason.category;
            document.querySelector('#detail-rto-reason-controllable').checked = Boolean(reason.is_controllable);
        });
    });

    document.querySelectorAll('[data-detail-delete-rto]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!confirm('Delete this RTO reason?')) return;
            await fetch(`/api/ops/rto-reasons/${button.dataset.detailDeleteRto}`, { method: 'DELETE', headers: { Accept: 'application/json' } });
            loadDetailPage();
        });
    });
}

function renderDetailTable(headers, rows, rightAlignedColumns = []) {
    document.querySelector('#detail-table-head').innerHTML = `<tr>${headers.map((header, index) => `<th class="${rightAlignedColumns.includes(index) ? 'text-right' : ''}">${escapeHtml(header)}</th>`).join('')}</tr>`;
    document.querySelector('#detail-table-body').innerHTML = rows.map((row) => `
        <tr>${row.map((cell, index) => `<td class="${rightAlignedColumns.includes(index) ? 'text-right' : ''}">${cell}</td>`).join('')}</tr>
    `).join('') || tableEmpty(headers.length, 'No records match the selected filters.');
}

async function saveDetailRtoReason() {
    const id = document.querySelector('#detail-rto-reason-id').value;
    const payload = {
        reason: document.querySelector('#detail-rto-reason-name').value,
        category: document.querySelector('#detail-rto-reason-category').value,
        is_controllable: document.querySelector('#detail-rto-reason-controllable').checked,
    };
    await sendJson(id ? `/api/ops/rto-reasons/${id}` : '/api/ops/rto-reasons', id ? 'PATCH' : 'POST', payload);
    document.querySelector('#detail-rto-reason-id').value = '';
    document.querySelector('#detail-rto-reason-name').value = '';
    document.querySelector('#detail-rto-reason-category').value = '';
    document.querySelector('#detail-rto-reason-controllable').checked = false;
    loadDetailPage();
}

function renderMarketingChart(rows) {
    replaceChart('marketingTrend', '#marketing-trend-chart', {
        type: 'line',
        data: {
            labels: rows.map((row) => shortDate(row.metric_date)),
            datasets: [
                lineDataset('Spend', rows.map((row) => row.spend), '#0891b2', true),
                lineDataset('Revenue', rows.map((row) => row.revenue), '#059669', true),
                lineDataset('ROAS', rows.map((row) => row.roas), '#d97706', false, 'y1'),
            ],
        },
        options: chartOptions({ y: { title: 'INR' }, y1: { title: 'ROAS', position: 'right', grid: { drawOnChartArea: false } } }),
    });
}

function renderOpsChart(rows) {
    replaceChart('opsTrend', '#ops-trend-chart', {
        type: 'bar',
        data: {
            labels: rows.map((row) => shortDate(row.metric_date)),
            datasets: [
                barDataset('Orders', rows.map((row) => row.orders), '#0284c7'),
                lineDataset('OTD %', rows.map((row) => row.otd_percent), '#059669', false, 'y1'),
                lineDataset('RTO %', rows.map((row) => row.rto_rate), '#e11d48', false, 'y1'),
            ],
        },
        options: chartOptions({ y: { title: 'Orders' }, y1: { title: 'Percent', position: 'right', grid: { drawOnChartArea: false }, min: 0, max: 100 } }),
    });
}

function renderRtoChart(rows) {
    replaceChart('rtoBreakdown', '#rto-chart', {
        type: 'bar',
        data: {
            labels: rows.map((row) => row.reason),
            datasets: [barDataset('RTO Count', rows.map((row) => row.rto_count), '#d97706')],
        },
        options: chartOptions({ y: { title: 'RTO cases' } }),
    });
}

function replaceChart(key, selector, config) {
    const canvas = document.querySelector(selector);
    if (!canvas) return;
    state.charts[key]?.destroy();
    state.charts[key] = new Chart(canvas, config);
}

function lineDataset(label, data, color, fill = false, axis = 'y') {
    return {
        type: 'line',
        label,
        data,
        yAxisID: axis,
        borderColor: color,
        backgroundColor: `${color}18`,
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 4,
        tension: 0.35,
        fill,
    };
}

function barDataset(label, data, color) {
    return { type: 'bar', label, data, backgroundColor: `${color}bf`, borderColor: color, borderWidth: 1, borderRadius: 5 };
}

function chartOptions(scales) {
    const configuredScales = {};
    Object.entries(scales).forEach(([key, value]) => {
        configuredScales[key] = {
            border: { color: '#e7e5e4' },
            grid: { color: '#f1f0ee', ...(value.grid || {}) },
            ticks: { color: '#78716c', maxTicksLimit: 6 },
            position: value.position || 'left',
            min: value.min,
            max: value.max,
            title: { display: true, text: value.title, color: '#78716c' },
        };
    });

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { labels: { color: '#44403c', boxWidth: 10, boxHeight: 10 } },
            tooltip: { backgroundColor: '#1c1917', borderColor: '#292524', borderWidth: 1 },
        },
        scales: configuredScales,
    };
}

function statusPill(status) {
    const normalized = String(status).replaceAll('_', ' ');
    const color = {
        active: 'bg-emerald-100 text-emerald-700',
        delivered: 'bg-emerald-100 text-emerald-700',
        recovered: 'bg-emerald-100 text-emerald-700',
        learning: 'bg-cyan-100 text-cyan-700',
        paused: 'bg-stone-200 text-stone-700',
        open: 'bg-amber-100 text-amber-700',
        under_review: 'bg-amber-100 text-amber-700',
        approved: 'bg-cyan-100 text-cyan-700',
        rto: 'bg-rose-100 text-rose-700',
        lost: 'bg-rose-100 text-rose-700',
    }[status] || 'bg-stone-200 text-stone-700';

    return `<span class="${color} inline-flex rounded-md px-2 py-1 text-xs font-semibold capitalize">${escapeHtml(normalized)}</span>`;
}

function showAlert(message, type) {
    const alert = document.querySelector('#alert-strip');
    if (!alert) return;
    alert.className = 'rounded-lg border px-4 py-3 text-sm';
    alert.classList.add(...alertClasses(type));
    alert.textContent = message;
    alert.classList.remove('hidden');
}

function alertClasses(type) {
    if (type === 'success') return ['border-emerald-200', 'bg-emerald-50', 'text-emerald-800'];
    if (type === 'warning') return ['border-amber-200', 'bg-amber-50', 'text-amber-800'];
    if (type === 'danger') return ['border-rose-200', 'bg-rose-50', 'text-rose-800'];
    return ['border-stone-200', 'bg-white', 'text-stone-600'];
}

function currentFilters() {
    const from = document.querySelector('#from-date');
    const to = document.querySelector('#to-date');

    if (!from || !to) {
        return currentFiltersForAssistantPage();
    }

    return { from: from.value, to: to.value };
}

function setDefaultDates(fromSelector, toSelector) {
    const to = new Date();
    to.setDate(to.getDate() - 1);
    const from = new Date(to);
    from.setDate(from.getDate() - 30);

    document.querySelector(fromSelector).value = toInputDate(from);
    document.querySelector(toSelector).value = toInputDate(to);
}

function tableEmpty(colspan, message) {
    return `<tr><td colspan="${colspan}" class="px-4 py-8 text-center text-stone-500">${message}</td></tr>`;
}

function toInputDate(date) {
    return date.toISOString().slice(0, 10);
}

function shortDate(value) {
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short' }).format(new Date(value));
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatAssistantContent(content) {
    const blocks = escapeHtml(content).split(/\n{2,}/).filter(Boolean);

    return blocks.map((block) => {
        const lines = block.split('\n').filter(Boolean);
        if (lines.every((line) => line.trim().startsWith('- '))) {
            return `<ul class="list-disc space-y-1 pl-5">${lines.map((line) => `<li>${line.trim().slice(2)}</li>`).join('')}</ul>`;
        }

        if (lines.length > 1) {
            return `<p>${lines.join('<br>')}</p>`;
        }

        return `<p>${lines[0] || ''}</p>`;
    }).join('');
}
