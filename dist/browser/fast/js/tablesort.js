const tables = document.querySelectorAll('table');
const searchbar = document.querySelector('#search');

for (let tab of tables) {
    const tbody = tab.tBodies[0];
    const rows = Array.from(tbody.rows);
    const headerCells = tab.tHead.rows[0].cells;

    for (let th of headerCells) {
        const column = th.cellIndex;

        th.addEventListener('click', () => {
            rows.sort((tr1, tr2) => {
                const tr1Text = tr1.cells[column].textContent;
                const tr2Text = tr2.cells[column].textContent;
                return tr1Text.localeCompare(tr2Text);
            });

            tbody.append(...rows);
        });
    }
}

searchbar.addEventListener('change', (e) => {
    console.info("New filter: ", event.target.value);
    for (let tab of tables) {
        const rows = tab.rows;
        for (let el of rows) {
            // Skip header
            if (el === tab.tHead.rows[0]) {
                continue;
            }
            el.classList.remove("hidden");
            if (event.target.value !== "") {
                el.classList.add("hidden");
                for (let column of el.cells) {
                    if (column.innerText.match(new RegExp(event.target.value, 'i'))) {
                        el.classList.remove("hidden");
                    }
                }
            }
        }
        ;
    }
});

