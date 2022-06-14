import NetgameComponent from './NetgameComponent.js';

export default class NetgameListComponent extends HTMLElement{
	constructor(model, data = {}){
		super();
		let template = document.querySelector('template[data-name="netgamelist"]');
		let templateContent = template.content;
		this.netgames = {};

		const shadowRoot = this.attachShadow({mode: 'open'})
		  .appendChild(templateContent.cloneNode(true));

		this.eHdl_ngcon = this.notifyController.bind(this);
		this.eHdl_sort = this.render.bind(this);
		this.eHdl_view = this.update.bind(this, {});
		this.eb_conn = function(){console.error("No eventbus hook registered yet!", this);};
		this.shadowRoot.querySelector('a.checkbox').addEventListener( "click", (event) => {
			this.classList.toggle("reverse");
			event.preventDefault();
		});
	}

	init(){
		customElements.define('sb-netgamelist', NetgameListComponent);
	}

	notifyController(){
		try{
			ServerBrowser.netgamecon.fetchServers().then( (response) => {
				this.shadowRoot.querySelector('[name="update"]').classList.remove("error");
				this.shadowRoot.querySelector('[name="update"]').value = "Update all";
				for(let i in response){
					this.netgames[i].classList.add("locked");
					this.netgames[i].updateListener().then();
				}
			}).catch( (error) => {
				this.shadowRoot.querySelector('[name="update"]').classList.add("error");
				this.shadowRoot.querySelector('[name="update"]').value = "Update failed!";
			});
		}catch(error){
			console.error("Error updating netgames(maybe just try again?):", error);
			this.shadowRoot.querySelector('[name="update"]').classList.add("error");
			this.shadowRoot.querySelector('[name="update"]').value = "Update failed!";
		}
		//event.preventDefault();
	}

	async connectToEventbus(){
		try{
			this.eb_conn = await ServerBrowser.eventbus.attach("refresh", this.handleBus.bind(this));
			//.then( this.notifyController.bind(this) );
			this.notifyController();
		}catch(error){
			if(this.querySelector('[slot="netgames"]') != undefined){
				this.removeChild(this.querySelector('[slot="netgames"]'));
			}
			console.error("Error connecting to Eventbus (will retry in 2s):", error);
			setTimeout(this.connectToEventbus.bind(this), 2000);
		}
	}

	sort(netgames, sort){
		//console.log(`Sort by: ${sort} (${typeof netgames})`, netgames);
		switch(sort){
		case "maxplayers":{
		//case "minplayers":{
			//sort = "players";
			return netgames.sort( (a,b) => {
				return( Number(b.getAttribute(sort)) - Number(a.getAttribute(sort)) );
			});
		}
		case "ping":{
			// Numeric sort
			return netgames.sort( (a,b) => {
				return( Number(a.getAttribute(sort)) - Number(b.getAttribute(sort)) );
			});
			break;
		}
		case "updated_at":{
			// Timestamp sort
			return netgames.sort( (a,b) => {
				return ( Date.parse(b.getAttribute(sort)) - Date.parse(a.getAttribute(sort)) );
			});
			break;
		}
		case "version":
		case "roomname":
		case "origin":
		case "name":
		default:{
			// Lexical sort
			return netgames.sort( (a,b) => {
				return a.getAttribute(sort).localeCompare(b.getAttribute(sort) );
			});
			break;
		}
		}
	}

	connectedCallback(){
		this.shadowRoot.querySelector('[name="update"]').addEventListener('click', this.eHdl_ngcon);
		this.shadowRoot.querySelector('[name="sort"]').addEventListener('change', this.eHdl_sort );
		this.shadowRoot.querySelector('[name="view"]').addEventListener('change', this.eHdl_view );
		//this.eHdl_ngcon = setInterval(this.notifyController, 5000)
		this.connectToEventbus();
		this.update();
	}
	disconnectedCallback(){
		this.shadowRoot.querySelector('[name="update"]').removeEventListener('click', eHdl_ngcon);
		this.shadowRoot.querySelector('[name="sort"]').removeEventListener('change', eHdl_sort);
		this.shadowRoot.querySelector('[name="view"]').removeEventListener('change', eHdl_view);
		//clearInterval(this.eHdl_ngcon);
		ServerBrowser.eventbus.detach("refresh", this.eb_conn);
	}
	adoptedCallback(){ this.render(); }
	attributesChangedCallback(){ this.update(); }

	update(data = {}){
		//let modelFetch = ServerBrowser.db.servers;
		for(let i in data){
			if(this.netgames[i] == undefined){
				let newNG = new NetgameComponent(data[i]);
				this.netgames[i] = newNG;
			}
			//this.netgames[i].classList.add("locked");
			//this.netgames[i].updateListener();
			this.netgames[i].update(data[i]);
			this.netgames[i].render();
		}
		// Update view type
		console.log("new viewtype: ", this.shadowRoot.querySelector('[name="view"]').value);
		this.setAttribute("view", this.shadowRoot.querySelector('[name="view"]').value);
		//console.log("Updated List: ",this);

		// Accumulate 1s of updates into one render call
		if(this.nextUpdate == undefined){
			this.nextUpdate = setTimeout(() => {
				this.render();
				this.nextUpdate = undefined;
			}, 1000);
		}
	}

	async render(){
		if(this.querySelector('[slot="netgames"]') == undefined){
			let newspan = document.createElement('span');
			newspan.slot = 'netgames';
			this.appendChild(newspan);
		}
		let sort = this.shadowRoot.querySelector('[name="sort"]').value;
		let netgames_ordered = [];
		for(let i in this.netgames){
			// Parse netgames into array
			netgames_ordered.push(this.netgames[i]);
		}
		let toRender = this.sort(netgames_ordered, sort);
		for(let i in toRender){
			this.querySelector('[slot="netgames"]').appendChild(toRender[i]);
		}
		//console.log("Rendered List: ",this);
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

