// Invoice form: add and remove line rows. Names are re-indexed so the server gets a dense array.
const table = document.getElementById('lines');
if (table) {
    const reindex = () => {
        table.querySelectorAll('tbody tr.line').forEach((row, i) => {
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/lines\[\d+\]/, `lines[${i}]`);
            });
        });
    };
    document.getElementById('add-line').addEventListener('click', () => {
        const rows = table.querySelectorAll('tbody tr.line');
        const clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('input').forEach((input) => {
            if (input.name.endsWith('[description]') || input.name.endsWith('[unit_price]')) input.value = '';
            if (input.name.endsWith('[quantity]')) input.value = '1';
        });
        table.querySelector('tbody').appendChild(clone);
        reindex();
    });
    table.addEventListener('click', (event) => {
        if (!event.target.matches('.remove-line')) return;
        if (table.querySelectorAll('tbody tr.line').length === 1) return;
        event.target.closest('tr').remove();
        reindex();
    });
}

// Payment reminder: open the event stream, append each delta to the box, stop on done,
// error, or the Stop button. EventSource reconnects on its own after a drop, which would
// start a second reminder, so every exit path closes it explicitly.
const reminder = document.getElementById('reminder');
if (reminder) {
    const text = document.getElementById('reminder-text');
    const status = document.getElementById('reminder-status');
    const write = document.getElementById('reminder-write');
    const stop = document.getElementById('reminder-stop');
    const copy = document.getElementById('reminder-copy');
    let source = null;

    const finish = (note) => {
        source?.close();
        source = null;
        status.textContent = note;
        stop.classList.add('hidden');
        write.disabled = false;
        if (text.value) copy.classList.remove('hidden');
    };

    write.addEventListener('click', () => {
        text.value = '';
        text.classList.remove('hidden');
        copy.classList.add('hidden');
        stop.classList.remove('hidden');
        write.disabled = true;
        status.textContent = 'Thinking…';

        source = new EventSource(reminder.dataset.url);
        source.addEventListener('delta', (event) => {
            if (!text.value) status.textContent = 'Writing…';
            text.value += event.data;
        });
        source.addEventListener('done', (event) => {
            const { stopReason, cost } = JSON.parse(event.data);
            finish(stopReason === 'end_turn' ? `Done, ${cost}.` : `Stopped early (${stopReason}), ${cost}.`);
        });
        source.addEventListener('error', (event) => {
            // Our own error event carries data; a dropped connection does not.
            finish(event.data ?? 'Connection lost.');
        });
    });
    stop.addEventListener('click', () => finish('Stopped.'));
    copy.addEventListener('click', () => navigator.clipboard.writeText(text.value).then(() => { status.textContent = 'Copied.'; }));
}

