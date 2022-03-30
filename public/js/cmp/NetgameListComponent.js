import NetgameComponent from './NetgameComponent.js';

export default class NetgameListComponent extends HTMLElement{
	constructor(model, data = {}){
		super();
		let template = document.querySelector('template[data-name="netgamelist"]');
		let templateContent = template.content;
		this.netgames = {};

		const shadowRoot = this.attachShadow({mode: 'open'})
		  .appendChild(templateContent.cloneNode(true));

		this.addEventListener('click', this.handleEvent);
		ServerBrowser.eventbus.attach("refresh", (message, data) => {
			this.handleBus(message, data);
		});
	}

	connectedCallback(){ this.update(); this.render(); }
	disconnectedCallback(){}
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
		console.log("Updated List: ",this);
	}

	async render(){
		if(this.querySelector('[slot="netgames"]') == undefined){
			let newspan = document.createElement('span');
			newspan.slot = 'netgames';
			this.appendChild(newspan);
		}
		this.querySelector('[slot="netgames"]').innerHTML = '';
		for(let i in this.servers){
			this.querySelector('[slot="netgames"]').appendChild(this.servers[i]);
		}
		console.log("Rendered List: ",this);
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

