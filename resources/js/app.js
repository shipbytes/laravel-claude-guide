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
