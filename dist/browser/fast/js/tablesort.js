document.querySelector('thead').addEventListener('click', (e) => {
	var table, rows, has_switched, i, x, y, to_switch, sortRow;
	sortRow = event.target.cellIndex;
	table = document.querySelector("table");
	has_switched = true;
	/*Make a loop that will continue until
	no has_switched has been done:*/
	while (has_switched) {
	//start by saying: no switching is done:
	has_switched = false;
	rows = table.rows;
	// Iterare rows
	for (i = 1; i < (rows.length - 1); i++) {
	  //start by saying there should be no has_switched:
	  to_switch = false;
	  /*Get the two elements you want to compare,
	  one from current row and one from the next:*/
	  x = rows[i].querySelectorAll("TD")[sortRow];
	  y = rows[i + 1].querySelectorAll("TD")[sortRow];
	  //check if the two rows should switch place:
	  if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
		//if so, mark as a switch and break the loop:
		to_switch = true;
		break;
	  }
	}
	if (to_switch) {
	  /*If a switch has been marked, make the switch
	  and mark that a switch has been done:*/
	  rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
	  has_switched = true;
	}
	}

});
