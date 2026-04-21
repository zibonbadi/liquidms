import EventBus from '../ctrl/Eventbus.js';
import RequestController from '../ctrl/RequestController.js';
import NetgameModel from '../mdl/NetgameModel.js';

export default class SRB2HTTPTableComponent extends HTMLElement{
	static get observedAttributes(){
		return [
			'data-sort',
			'data-service',
		];
	}
	
	constructor(model, data = {}){
		super();
		
		/* Event handlers */
		this.eHdl_sort = this.setSort.bind(this);
		this.eb_conn = function(){console.error("No eventbus hook registered yet!", this);};
		document.querySelector('input[name="search"]').addEventListener( "change", this.filter.bind(this));

	}

	init(){
		customElements.define('liquidms-srb2httptable', SRB2HTTPTableComponent);
	}

	SRB2query(netgame){
		let query_url = `api/srb2query?hostname=${netgame.ip}&port=${netgame.port}`;
		console.log(`Querying ${query_url}`);

		try{
			this.shadowRoot.querySelector(`[data-address="${netgame.ip}:${netgame.port}"]`).classList.remove('error');
			RequestController.get(query_url)
				.then( (response) => {
					let query = JSON.parse(response, ',');
					let hostkey = `${netgame.ip}:${netgame.port}`;
					NetgameModel.populateOne(hostkey, query);
				}).catch( (error) => {
					this.shadowRoot.querySelector(`[data-address="${netgame.ip}:${netgame.port}"]`).classList.add('error');
					throw error;
			});
		}catch(error){
			console.error(`SRB2Query error @ ${query_url}: `, error);
		}
	}

	queryAllNetgames(){
		console.log("Querying netgames for ", this);

		// Filter hostname and port directly out of the table
		let netgames = [];
		let netgame_rows = this.shadowRoot.querySelectorAll('tbody');
		console.log(netgame_rows);
		for(let row of netgame_rows){
			netgames.push({
				"ip"	: row.querySelector('td[data-category="ip"]').innerText,
				"port"	: row.querySelector('td[data-category="port"]').innerText,
			});
		}
		
		
		for(let netgame of netgames){
			try{
				this.SRB2query(netgame);
			}catch(error){
				console.error('Failed to update NetgameModel: ', netgame.ip, error);
				throw error;
			}
		}
	}

	async connectToEventbus(){
		try{
			this.eb_conn = await EventBus.attach("refresh", this.handleBus.bind(this));
		}catch(error){
			if(this.querySelector('[slot="netgame"]') != undefined){
				this.removeChild(this.querySelector('[slot="netgame"]'));
			}
			console.error("Error connecting to Eventbus (will retry in 2s):", error);
			setTimeout(this.connectToEventbus.bind(this), 2000);
		}
	}

	sort(netgames, sort){
		let rVal = netgames;
		switch(sort){
		case "players":{
			rVal = rVal.sort( (a,b) => {

				if(a.hasAttribute(sort) && !b.hasAttribute(sort)){
					return -1;
				}else if(!a.hasAttribute(sort) && b.hasAttribute(sort)){
					return 1;
				}else if(!a.hasAttribute(sort) && !b.hasAttribute(sort)){
					return 0;
				}
				return( Number(a.getAttribute(sort)) - Number(b.getAttribute(sort)) );
			});
		}
		case "ping":{
			// Numeric sort
			rVal = rVal.sort( (a,b) => {
				if(a.hasAttribute(sort) && !b.hasAttribute(sort)){
					return -1;
				}else if(!a.hasAttribute(sort) && b.hasAttribute(sort)){
					return 1;
				}else if(!a.hasAttribute(sort) && !b.hasAttribute(sort)){
					return 0;
				}
				return( Number(b.getAttribute(sort)) - Number(a.getAttribute(sort)) );
			});
			break;
		}
		case "updated_at":{
			// Timestamp sort
			rVal = rVal.sort( (a,b) => {
				let aVal = (a.hasAttribute(sort))?Date.parse(a.getAttribute(sort)):-1;
				let bVal = (b.hasAttribute(sort))?Date.parse(b.getAttribute(sort)):-1;
				return ( bVal - aVal );
			});
			break;
		}
		case "version":
		case "roomname":
		case "origin":
		case "name":
		default:{
			// Lexical sort
			rVal = rVal.sort( (a,b) => {
				if(a.hasAttribute(sort) && !b.hasAttribute(sort)){
					return -1;
				}else if(!a.hasAttribute(sort) && b.hasAttribute(sort)){
					return 1;
				}else if(!a.hasAttribute(sort) && !b.hasAttribute(sort)){
					return 0;
				}
				return a.getAttribute(sort).localeCompare(b.getAttribute(sort) );
			});
			break;
		}
		}
		console.debug("Criteria sorted NetgameList:", rVal);
		// Bury unreachable netgames
		/*
		rVal = rVal.sort( (a,b) => {
			if(a.classList.contains("error") && !b.classList.contains("error")){
				return 1;
			}else if(!a.classList.contains("error") && b.classList.contains("error")){
				return -1;
			}
			return 0;
		});
		*/
		console.debug("Availability sorted NetgameList:", rVal);
		return rVal;
	}

	connectedCallback(){
		for(let tab of this.shadowRoot.querySelectorAll('table')){
			for(let th of tab.tHead.rows[0].cells){
				th.addEventListener('click', this.eHdl_sort );
			}
		}
		this.connectToEventbus();
		this.queryAllNetgames();
	}

	disconnectedCallback(){
		for(let tab of this.shadowRoot.querySelectorAll('table')){
			for(let th of tab.tHead.rows[0].cells){
				th.addEventListener('change', eHdl_sort );
			}
		}
		EventBus.detach("refresh", this.eb_conn);
	}
	adoptedCallback(){ this.render(); }
	attributesChangedCallback(){ this.update(); }

	update(data = {}){
		for(let i in data){
			let mainRow = this.shadowRoot.querySelector(`tbody[data-address="${i}"] > tr`);
			let detailsRow = this.shadowRoot.querySelector(`tbody[data-address="${i}"] > tr.details`);
			
			// Are we interested?
			if(mainRow == null){ continue;	}

			if(data[i]._meta == null || data[i]._meta == null){
				tRow.classList.add('error');
				continue;
			}

			// Layout and metadata basics
			let metadata = data[i]._meta
			let new_element = document.createElement('td');
			new_element.colSpan = 7;

			// Set player count
			this.shadowRoot.querySelector(`tbody[data-address="${i}"] td[data-category="players"]`).textContent = `${metadata.players.count} / ${metadata.players.max}`;
			this.shadowRoot.querySelector(`tbody[data-address="${i}"] td[data-category="servername"]`).innerHTML = metadata.servername_color;

			// Write details section
			let details = `<h1>${metadata.servername_color}</h1>`;
			
			if(metadata.dedicated){ details += `[Dedicated server]`; }
			if(metadata.cheats){ details += `[Cheats enabled]`; }
			if(metadata.httpsource){ details += `<p>HTTP source: <a href="${metadata.httpsource}" target="_blank">${metadata.httpsource}</a></p>`; }
			details += `<h2>${metadata.level.title}</h2><p>Game type: ${metadata.gametype}</p>`;
			
			//// Current Players ////
			if(metadata.players.count > 0){
				details += '<details><summary>Players</summary><ul>';
				for(let p of metadata.players.list){
					details += `<li>[${p.team}] ${p.name} (Score: ${p.score})</li>`;
				}
				details += '</ul></details>';
			}

			//// Required addons ////
			if(metadata.fileinfo.length > 0){
				details += `<details><summary>Addons</summary><ul>`;
				for(let file of metadata.fileinfo){
					if(metadata.httpsource){ details += `<li><a href="${metadata.httpsource}${file.name}" target="_blank">${file.name}</a></li>`; }
					else{ details += `<li>${file.name}</li>`; }
				}
				details += `</ul>`;
			}

			// Render final details row
			new_element.innerHTML = details;
			detailsRow.innerHTML = '';
			detailsRow.append(new_element);

		}

		// Accumulate 1s of updates into one render call
		if(this.nextUpdate == undefined){
			this.nextUpdate = setTimeout(() => {
				this.render();
				this.nextUpdate = undefined;
			}, 1000);
		}
	}
	
	setSort(e){
		let new_sort = e.target.getAttribute('data-category')
		let tbl = e.target.parentNode.parentNode.parentNode;
		this.setAttribute('data-sort', new_sort);
		
		// Netgames are grouped by tbodies
		// to keep the details row in place.
		// We're sorting the bodies.
		let bodies = Array.from(tbl.querySelectorAll('tbody'));
		
		console.log(bodies);

		bodies.sort((tr1, tr2) => {
			const tr1Text = tr1.querySelector(`[data-category="${new_sort}"`).textContent;
			const tr2Text = tr2.querySelector(`[data-category="${new_sort}"`).textContent;
			return tr1Text.localeCompare(tr2Text);
		});
		
		tbl.append(...bodies);

		this.render()
	}

	filter(e){

		let filter = e;
		if(typeof(e) == "object"){ filter = e.target.value; }

		console.info(this, "New filter: ", filter);
		let tables = this.shadowRoot.querySelectorAll(`table`);
		for (let tab of tables) {
			const rows = tab.querySelectorAll('tbody');
			for(let el of rows){
				el.classList.remove("hidden");
				if(filter !== ""){
					el.classList.add("hidden");
					let cells = el.querySelectorAll('tr:not(.details) > td');
					console.log(cells);
					for (let column of cells) {
						// Check for simple text matches
						if(column.innerText.match(new RegExp(filter,'i'))){el.classList.remove("hidden"); break;}
					}
					// Now check for metadata matches
					if(NetgameModel.match(el.getAttribute("data-address"), new RegExp(filter,'i'))){el.classList.remove("hidden");}
				}
			};
		}
	}


	async render(){
		let sort = this.getAttribute('data-sort');
		let netgames_ordered = [];
		for(let i in this.netgames){
			// Parse netgames into array
			netgames_ordered.push(this.netgames[i]);
		}

		let toRender = this.sort(netgames_ordered, sort);
		for(let i in toRender){
			this.appendChild(toRender[i]);
		}
	}

	async handleBus(message, data){
		switch(message){
			case "refresh":{
				this.update(data);
				break;
			}
			default:{
				break;
			}
		}
	}
}

