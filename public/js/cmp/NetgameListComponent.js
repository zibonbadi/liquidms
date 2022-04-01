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
		this.eb_conn = function(){console.error("No eventbus hook registered yet!", this);};
	}

	init(){
		customElements.define('sb-netgamelist', NetgameListComponent);
	}

	notifyController(){
		try{
			ServerBrowser.netgamecon.fetchServers();
		}catch(error){
			console.error("Error updating netgames(maybe just try again?):", error);
		}
	}

	connectToEventbus(){
		try{
			this.eb_conn = ServerBrowser.eventbus.attach("refresh", (message, data) => {
				this.handleBus(message, data);
			})
			.then( this.notifyController );
		}catch(error){
			if(this.querySelector('[slot="netgames"]') != undefined){
				this.removeChild(this.querySelector('[slot="netgames"]'));
			}
			console.error("Error connecting to Eventbus (will retry in 3s):", error);
			setTimeout(this.connectToEventbus.bind(this), 3000);
		}
	}

	connectedCallback(){
		this.eHdl_ngcon = this.shadowRoot.querySelector('input[name="update"]').addEventListener('click', this.notifyController);
		//this.eHdl_ngcon = setInterval(this.notifyController, 5000)
		this.connectToEventbus();
		this.update();
		this.render();
	}
	disconnectedCallback(){
		this.removeEventListener('click', this.notifyController);
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
	}

	async render(){
		if(this.querySelector('[slot="netgames"]') == undefined){
			let newspan = document.createElement('span');
			newspan.slot = 'netgames';
			this.appendChild(newspan);
		}
		for(let i in this.netgames){
			this.querySelector('[slot="netgames"]').appendChild(this.netgames[i]);
		}
		//console.log("Rendered List: ",this);
	}

	handleBus(message, data){
		switch(message){
			case "refresh":{
				console.log("Caught refresh", this);
				this.update(data);
				this.render(data);
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

