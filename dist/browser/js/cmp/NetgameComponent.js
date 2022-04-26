export default class NetgameComponent extends HTMLElement{
	constructor(data = {}){
		super();

		let template = document.querySelector('template[data-name="netgame"]');
		let templateContent = template.content;

		const shadowRoot = this.attachShadow({mode: 'open'})
		  .appendChild(templateContent.cloneNode(true));

		this.updateListener =  this.notifyController.bind(this);
		//this.updateListener = this.shadowRoot.querySelector('[name="update"]').addEventListener("click",));
		this.update(data);
	}

	init(){
		customElements.define('sb-netgame', NetgameComponent);
	}

	connectedCallback(){
		this.shadowRoot.querySelector('[name="update"]').addEventListener("click", this.updateListener);
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
				if(i == "players_list"){
					this.playerlist = data[i];
					continue;
				}
				this.dataset[i] = data[i];
				//console.log(i, data[i]);
			}
		}
		//console.log("Updated Netgame: ",this);
	}

	async render(){
		this.innerHTML = ''
		for(let i in this.dataset){
			if(i == "players_list"){continue;}
			let newNG = document.createElement('span');
			newNG.slot = i;
			newNG.innerHTML = this.dataset[i];
			this.appendChild(newNG);
		}

		for(let i in this.playerlist){
			if(i == "players_list"){continue;}
			let player = document.createElement('details');
			player.classList.add('player');
			player.classList.add(this.playerlist[i].team);

			player.slot = "players_list";
			let timespan = new Date(this.playerlist[i].seconds * 1000).toISOString().substr(11, 8);
			player.innerHTML = `
			<summary>${this.playerlist[i].name}</summary>
			<ul>
			<li>Team: ${this.playerlist[i].team}</li>
			<li>Score: ${this.playerlist[i].score}</li>
			<li>Online: ${timespan}</li>
			</ul>`;
			this.appendChild(player);
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

