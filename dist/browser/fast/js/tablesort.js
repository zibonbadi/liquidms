const tables = document.querySelectorAll('table');

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
