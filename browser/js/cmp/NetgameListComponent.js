import NetgameComponent from './NetgameComponent.js';

export default class NetgameListComponent extends HTMLElement{
	constructor(model, data = {}){
		super();
		let template = document.querySelector('template[data-name="netgamelist"]');
		let templateContent = template.content;
		this.netgames = {};

		const shadowRoot = this.attachShadow({mode: 'open'})
		  .appendChild(templateContent.cloneNode(true));

		this.eHdl_ngcon = function(){console.error("No event handler registered yet!", this);};
		this.eHdl_sort = function(){console.error("No event handler registered yet!", this);};
		this.eb_conn = function(){console.error("No eventbus hook registered yet!", this);};
	}

	init(){
		customElements.define('sb-netgamelist', NetgameListComponent);
	}

	notifyController(){
		try{
			ServerBrowser.netgamecon.fetchServers().then( (response) => {
				this.shadowRoot.querySelector('[name="update"]').classList.remove("error");
				this.shadowRoot.querySelector('[name="update"]').value = "Update all";
			}).catch( (error) => {
				this.shadowRoot.querySelector('[name="update"]').classList.add("error");
				this.shadowRoot.querySelector('[name="update"]').value = "Update failed!";
			});
		}catch(error){
			console.error("Error updating netgames(maybe just try again?):", error);
			this.shadowRoot.querySelector('[name="update"]').classList.add("error");
			this.shadowRoot.querySelector('[name="update"]').value = "Update failed!";
		}
	}

	connectToEventbus(){
		try{
			this.eb_conn = ServerBrowser.eventbus.attach("refresh", (message, data) => {
				this.handleBus(message, data);
			})
			.then( this.notifyController.bind(this) );
		}catch(error){
			if(this.querySelector('[slot="netgames"]') != undefined){
				this.removeChild(this.querySelector('[slot="netgames"]'));
			}
			console.error("Error connecting to Eventbus (will retry in 2s):", error);
			setTimeout(this.connectToEventbus.bind(this), 2000);
		}
	}

	sort(netgames, sort){
		console.log(`Sort by: ${sort} (${typeof netgames})`, netgames);
		switch(sort){
		case "maxplayers":
		case "minplayers":{
			sort = "players";
		}
		case "ping":{
			// Numeric sort
			return netgames.sort( (a,b) => {
				return( Number(a.dataset[sort]) - Number(b.dataset[sort]) );
			});
			break;
		}
		case "updated_at":{
			// Timestamp sort
			return netgames.sort( (a,b) => {
				return ( Date.parse(a.dataset[sort]) - Date.parse(b.dataset[sort]) );
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
				return a.dataset[sort].localeCompare(b.dataset[sort] );
			});
			break;
		}
		}
	}

	connectedCallback(){
		this.eHdl_ngcon = this.shadowRoot.querySelector('[name="update"]').addEventListener('click', this.notifyController.bind(this));
		this.eHdl_sort = this.shadowRoot.querySelector('[name="sort"]').addEventListener('change', this.render.bind(this));
		//this.eHdl_ngcon = setInterval(this.notifyController, 5000)
		this.connectToEventbus();
		this.update();
		this.render();
	}
	disconnectedCallback(){
		this.shadowRoot.querySelector('[name="update"]').removeEventListener('click', eHdl_ngcon);
		this.shadowRoot.querySelector('[name="sort"]').removeEventListener('change', eHdl_sort);
		//clearInterval(this.eHdl_ngcon);
		ServerBrowser.eventbus.detach("refresh", this.eb_conn);
	}
	adoptedCallback(){ this.render(); }
	attributesChangedCallback(){ this.update(); this.render(); }

	update(data = {}){
		//let modelFetch = ServerBrowser.db.servers;
		for(let i in data){
			if(this.netgames[i] == undefined){
				let newNG = new NetgameComponent(data[i]);
				this.netgames[i] = newNG;
			}
			this.netgames[i].update(data[i]);
			this.netgames[i].render();
		}
		//console.log("Updated List: ",this);
		this.render();
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

	handleBus(message, data){
		switch(message){
			case "refresh":{
				//console.log("Caught refresh", this);
				this.update(data);
				//this.render(data);
				/*
				for(let sv in data){
					console.log(sv);
					this.netgames[sv].update(data[sv]);
					this.netgames[sv].render(data[sv]);
				}
				*/
				break;
			}
			default:{
				break;
			}
		}
	}
}

