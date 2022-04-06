export default class NetgameComponent extends HTMLElement{
	constructor(data = {}){
		super();

		let template = document.querySelector('template[data-name="netgame"]');
		let templateContent = template.content;

		const shadowRoot = this.attachShadow({mode: 'open'})
		  .appendChild(templateContent.cloneNode(true));

		this.updateListener = null;
		this.update(data);
	}

	init(){
		customElements.define('sb-netgame', NetgameComponent);
	}

	connectedCallback(){
		this.updateListener = this.shadowRoot.querySelector('[name="update"]').addEventListener("click", this.notifyController.bind(this));
		this.update(); 
		this.render();
	}
	disconnectedCallback(){
		this.shadowRoot.querySelector('[name="update"]').removeEventListener("click", this.updateListener);
	}
	adoptedCallback(){
		this.render();
	}
	attributesChangedCallback(){
		this.update();
		this.render();
	}

	update(data = {}){
		if(Object.entries(data).length > 0){
			for(let i in data){
				this.dataset[i] = data[i];
				//console.log(i, data[i]);
			}
		}
		//console.log("Updated Netgame: ",this);
	}

	async render(){
		this.innerHTML = ''
		for(let i in this.dataset){
			let newNG = document.createElement('span');
			newNG.slot = i;
			newNG.innerHTML = this.dataset[i];
			this.appendChild(newNG);
		}
		//console.log("Rendered Netgame: ",this);
	}

	notifyController(event){
		ServerBrowser.netgamecon.updateOne(this.dataset);
		event.preventDefault();
	}

	handleBus(message, data = {}){
		switch(message){
			case "refresh":{
				console.log("Caught refresh");
				for(let sv in data){
					if(data[sv].hostname == this.dataset.hostname){ console.log("Caught refresh on: ", this.dataset.hostname); }
				}
				break;
			}
			default:{
				break;
			}
		}
	}
};

