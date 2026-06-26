function boot() {
    const widget = document.querySelector('#assistant-widget');
    const page = document.querySelector('#assistant-page-app');

    if (widget) {
        bootAssistantWidget(widget);
    }

    if (page) {
        bootAssistantPage();
    }
}

function bootAssistantWidget(widget) {
    const drawer = widget.querySelector('#assistant-drawer');
    const input = widget.querySelector('#assistant-question');
    const messagesBox = widget.querySelector('#assistant-messages');

    widget.querySelector('#assistant-toggle')?.addEventListener('click', () => drawer.classList.toggle('hidden'));
    widget.querySelector('#assistant-close')?.addEventListener('click', () => drawer.classList.add('hidden'));
    widget.querySelector('#assistant-new-chat')?.addEventListener('click', () => {
        localStorage.removeItem('activeAssistantSessionKey');
        messagesBox.innerHTML = '';
        appendAssistantMessage(messagesBox, 'assistant', 'New chat started. Ask me about the current page or dashboard data.');
    });

    widget.querySelector('#assistant-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        askAssistant({
            input,
            messagesBox,
            department: assistantDepartment(widget),
            context: assistantContext(),
        });
    });

    widget.querySelectorAll('.ai-suggestion').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.textContent.trim();
            askAssistant({
                input,
                messagesBox,
                department: assistantDepartment(widget),
                context: assistantContext(),
            });
        });
    });

    appendAssistantMessage(messagesBox, 'assistant', 'Ask me about the current page. I can explain the metrics and suggest next steps.');
}

async function bootAssistantPage() {
    setAssistantPageWelcome();
    await loadAssistantSessions();

    document.querySelector('#assistant-page-new-chat')?.addEventListener('click', async () => {
        localStorage.removeItem('activeAssistantSessionKey');
        renderAssistantMessages(document.querySelector('#assistant-page-messages'), []);
        await loadAssistantSessions();
    });

    document.querySelector('#assistant-page-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = document.querySelector('#assistant-page-question').value;
        const departmentInput = document.querySelector('#assistant-page-department');
        const department = inferDepartmentFromQuestion(question, departmentInput?.value || 'operations');
        if (departmentInput) {
            departmentInput.value = department;
        }

        askAssistant({
            input: document.querySelector('#assistant-page-question'),
            messagesBox: document.querySelector('#assistant-page-messages'),
            department,
            context: await buildAssistantPageContext(department),
            afterAnswer: loadAssistantSessions,
        });
    });
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
        if (afterAnswer) {
            await afterAnswer();
        }
    } catch (error) {
        removePendingAssistantMessage(messagesBox);
        appendAssistantMessage(messagesBox, 'assistant', error.message || 'I could not answer that right now.');
    }
}

function assistantDepartment(widget) {
    return widget.dataset.defaultDepartment || 'operations';
}

function assistantContext() {
    const script = document.querySelector('#assistant-context');

    if (! script) {
        return {
            source: window.location.pathname,
        };
    }

    try {
        return JSON.parse(script.textContent || '{}');
    } catch {
        return { source: window.location.pathname };
    }
}

async function buildAssistantPageContext(department) {
    if (department === 'marketing') {
        const [overview, trends, campaigns, meta, google] = await Promise.all([
            getJson('/api/marketing/overview'),
            getJson('/api/marketing/trends'),
            getJson('/api/marketing/campaigns'),
            getJson('/api/marketing/platform?platform=meta'),
            getJson('/api/marketing/platform?platform=google'),
        ]);

        return {
            source: 'assistant_page',
            selected_department: department,
            marketing: { overview, trends, campaigns, platforms: [meta, google] },
        };
    }

    const [overview, trends, couriers, rto, lostCases] = await Promise.all([
        getJson('/api/ops/overview'),
        getJson('/api/ops/trends'),
        getJson('/api/ops/couriers'),
        getJson('/api/ops/rto'),
        getJson('/api/ops/lost-cases'),
    ]);

    return {
        source: 'assistant_page',
        selected_department: department,
        operations: { overview, trends, couriers, rto, lostCases },
    };
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
    if (!box) return;
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
    appendAssistantMessage(box, 'assistant', 'Ask a question about Marketing or Operations. I will use the latest dashboard data as context.');
}

async function getJson(path, withDates = true) {
    const url = new URL(path, window.location.origin);

    if (withDates) {
        const { from, to } = currentFiltersForAssistantPage();
        url.searchParams.set('from', from);
        url.searchParams.set('to', to);
    }

    const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
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

function appendAssistantMessage(box, role, content, pending = false) {
    if (!box) return;

    box.insertAdjacentHTML('beforeend', `
        <div ${pending ? 'data-pending-assistant' : ''} class="rounded-lg ${role === 'user' ? 'ml-10 bg-stone-100' : 'mr-10 bg-emerald-50 border border-emerald-100'} p-3">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] ${role === 'user' ? 'text-stone-500' : 'text-emerald-700'}">${role === 'user' ? 'You' : 'Assistant'}</p>
            <div class="assistant-message-body mt-2 leading-6 text-stone-700">${formatAssistantContent(content)}</div>
        </div>
    `);
    box.scrollTop = box.scrollHeight;
}

function removePendingAssistantMessage(box) {
    const messages = box?.querySelectorAll('[data-pending-assistant]');
    messages?.[messages.length - 1]?.remove();
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

function toInputDate(date) {
    return date.toISOString().slice(0, 10);
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
    const escaped = escapeHtml(content);
    const blocks = escaped.split(/\n{2,}/).filter(Boolean);

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

boot();
